<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Enums\DocumentFolder;
use App\Enums\ParafiscalDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\Files\StoredFileResponder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class CommitParafiscalPlanillaService
{
    public const STATE_TTL = 3600;

    public function __construct(
        private readonly PreviewParafiscalPlanillaService $preview,
        private readonly ParsePilaPlanillaService $parser,
        private readonly PilaPlanillaClipper $clipper,
    ) {}

    public function execute(int $companyId, int $userId, ?string $mode = null): int
    {
        $this->start($companyId, $userId, $mode);
        $saved = 0;
        do {
            $tick = $this->tick($companyId, $userId, 80);
            $saved = (int) $tick['saved'];
        } while (! $tick['done']);

        return $saved;
    }

    public function conflictCount(int $companyId, int $userId): int
    {
        $cached = $this->preview->get($companyId, $userId);
        if ($cached === null) {
            return 0;
        }

        $base = $this->periodBase(
            is_string($cached['pension_period'] ?? null) ? $cached['pension_period'] : null,
            is_string($cached['salud_period'] ?? null) ? $cached['salud_period'] : null,
        );
        $ids = Employee::query()
            ->where('security_company_id', $companyId)
            ->where('indexing_enabled', true)
            ->whereIn('id', collect(is_array($cached['matched'] ?? null) ? $cached['matched'] : [])
                ->pluck('employee_id')
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->all())
            ->pluck('id')
            ->all();

        return $this->samePeriodDocuments($ids, $base)->count();
    }

    /** @return array{total: int} */
    public function start(int $companyId, int $userId, ?string $mode = null): array
    {
        @set_time_limit(180);

        $cached = $this->preview->get($companyId, $userId);
        if ($cached === null || ! isset($cached['path'])) {
            throw ValidationException::withMessages([
                'file' => 'No hay una revisión vigente. Vuelve a cargar la planilla.',
            ]);
        }

        $absolute = storage_path('app/'.$cached['path']);
        if (! is_file($absolute)) {
            $this->preview->forget($companyId, $userId);
            throw ValidationException::withMessages([
                'file' => 'El Excel temporal ya no está. Vuelve a cargar la planilla.',
            ]);
        }

        $spreadsheet = IOFactory::load($absolute);
        $parsed = $this->parser->parseSpreadsheet($spreadsheet);
        $spreadsheet->disconnectWorksheets();

        $employees = Employee::query()
            ->where('security_company_id', $companyId)
            ->where('indexing_enabled', true)
            ->get(['id', 'security_company_id', 'document_number']);

        $byDocument = [];
        foreach ($employees as $employee) {
            $key = ParsePilaPlanillaService::normalizeCedula((string) $employee->document_number);
            if ($key !== '') {
                $byDocument[$key] = $employee;
            }
        }

        $dir = storage_path('app/tmp/parafiscales/'.$companyId.'/'.$userId);
        File::ensureDirectoryExists($dir);
        $sourcePath = $dir.'/source.xlsx';
        File::copy($absolute, $sourcePath);

        $items = [];
        foreach ($parsed['groups'] as $document => $group) {
            $employee = $byDocument[$document] ?? null;
            if ($employee === null || $group['rows'] === []) {
                continue;
            }

            $items[] = [
                'employee_id' => $employee->id,
                'company_id' => $employee->security_company_id,
                'rows' => array_map('intval', $group['rows']),
                'total' => (float) $group['total'],
            ];
        }

        $pension = is_string($parsed['pension_period'] ?? null) ? $parsed['pension_period'] : null;
        $salud = is_string($parsed['salud_period'] ?? null) ? $parsed['salud_period'] : null;
        $base = $this->periodBase($pension, $salud);
        $choice = in_array($mode, ['replace', 'version'], true) ? $mode : null;
        $conflicts = $this->samePeriodDocuments(array_column($items, 'employee_id'), $base)->count();
        if ($conflicts > 0 && $choice === null) {
            throw ValidationException::withMessages([
                'file' => 'Ya hay documentos con estas fechas. Elige si se reemplazan o se crea un archivo nuevo.',
            ]);
        }

        $payloadPath = $dir.'/items.json';
        File::put($payloadPath, json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $state = [
            'source' => $sourcePath,
            'payload' => $payloadPath,
            'sheet_index' => (int) $parsed['sheet_index'],
            'cursor' => 0,
            'saved' => 0,
            'total' => count($items),
            'header_row' => (int) $parsed['header_row'],
            'valor_cell' => (string) $parsed['valor_cell'],
            'period_base' => $base,
            'mode' => $conflicts > 0 ? $choice : 'add',
            'taken_on' => $pension !== null
                ? Carbon::createFromFormat('Y-m-d', $pension.'-01')->startOfMonth()->toDateString()
                : ($salud !== null
                    ? Carbon::createFromFormat('Y-m-d', $salud.'-01')->startOfMonth()->toDateString()
                    : now()->startOfMonth()->toDateString()),
            'status' => 'running',
            'message' => '',
        ];
        Cache::put($this->stateKey($companyId, $userId), $state, self::STATE_TTL);

        return ['total' => (int) $state['total']];
    }

    /**
     * @return array{current: int, total: int, saved: int, done: bool, percent: int, message: string}
     */
    public function tick(int $companyId, int $userId, int $limit = 20): array
    {
        @set_time_limit(120);

        $state = Cache::get($this->stateKey($companyId, $userId));
        if (! is_array($state)) {
            throw ValidationException::withMessages([
                'file' => 'No hay una carga en curso. Vuelve a revisar la planilla.',
            ]);
        }

        $items = json_decode((string) File::get($state['payload']), true);
        if (! is_array($items)) {
            throw ValidationException::withMessages(['file' => 'Se perdió el recorte temporal. Vuelve a cargar.']);
        }

        $type = ParafiscalDocumentType::Planilla;
        $folder = DocumentFolder::Parafiscales;
        $end = min(count($items), (int) $state['cursor'] + $limit);

        for ($i = (int) $state['cursor']; $i < $end; $i++) {
            $item = $items[$i];
            $employee = Employee::query()->find((int) $item['employee_id']);
            if ($employee === null) {
                continue;
            }

            $relative = sprintf(
                'companies/%d/employees/%d/%s/%s-%s.xlsx',
                $employee->security_company_id,
                $employee->id,
                $folder->value,
                $type->value,
                Str::uuid()->toString(),
            );
            $dest = storage_path('app/'.$relative);
            File::ensureDirectoryExists(dirname($dest));

            $this->clipper->writeClip(
                (string) $state['source'],
                (int) $state['sheet_index'],
                (int) $state['header_row'],
                array_map('intval', $item['rows'] ?? []),
                (string) $state['valor_cell'],
                (float) $item['total'],
                $dest,
            );

            $base = (string) ($state['period_base'] ?? '');
            if (($state['mode'] ?? '') === 'replace') {
                $this->deleteSamePeriod($employee, $folder, $type, $base);
            }
            $display = $this->storedName($employee, $folder, $type, $base, ($state['mode'] ?? '') === 'version');

            EmployeeDocument::query()->create([
                'security_company_id' => $employee->security_company_id,
                'employee_id' => $employee->id,
                'folder' => $folder,
                'document_type' => $type->value,
                'display_name' => $display,
                'page_from' => null,
                'page_to' => null,
                'pages' => null,
                'not_applicable' => false,
                'original_name' => $display,
                'disk_path' => $relative,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size_bytes' => is_file($dest) ? (int) filesize($dest) : 0,
                'taken_on' => $state['taken_on'],
                'provider' => null,
            ]);
            $state['saved']++;
        }

        $state['cursor'] = $end;
        $done = $end >= count($items);
        if ($done) {
            $state['status'] = 'done';
            $this->preview->forget($companyId, $userId);
            File::delete($state['source'] ?? '');
            File::delete($state['payload'] ?? '');
        }

        Cache::put($this->stateKey($companyId, $userId), $state, self::STATE_TTL);

        $total = max(1, (int) $state['total']);

        return [
            'current' => (int) $state['cursor'],
            'total' => (int) $state['total'],
            'saved' => (int) $state['saved'],
            'done' => $done,
            'percent' => $done ? 100 : (int) floor(100 * $state['cursor'] / $total),
            'message' => $done
                ? ((int) $state['saved'] === 1 ? '1 recorte guardado.' : $state['saved'].' recortes guardados.')
                : 'Guardando recortes…',
        ];
    }

    /** @return array{current: int, total: int, saved: int, done: bool, percent: int, message: string, status: string} */
    public function progress(int $companyId, int $userId): array
    {
        $state = Cache::get($this->stateKey($companyId, $userId));
        if (! is_array($state)) {
            return [
                'current' => 0,
                'total' => 0,
                'saved' => 0,
                'done' => false,
                'percent' => 0,
                'message' => '',
                'status' => 'idle',
            ];
        }

        $total = max(1, (int) $state['total']);
        $done = ($state['status'] ?? '') === 'done';

        return [
            'current' => (int) $state['cursor'],
            'total' => (int) $state['total'],
            'saved' => (int) $state['saved'],
            'done' => $done,
            'percent' => $done ? 100 : (int) floor(100 * $state['cursor'] / $total),
            'message' => (string) ($state['message'] ?? ''),
            'status' => (string) ($state['status'] ?? 'running'),
        ];
    }

    public function abort(int $companyId, int $userId): void
    {
        $state = Cache::get($this->stateKey($companyId, $userId));
        if (is_array($state)) {
            File::delete($state['source'] ?? '');
            File::delete($state['payload'] ?? '');
        }
        Cache::forget($this->stateKey($companyId, $userId));
        $this->preview->forget($companyId, $userId);
    }

    private function stateKey(int $companyId, int $userId): string
    {
        return "parafiscal-commit.{$companyId}.{$userId}";
    }

    private function periodBase(?string $pension, ?string $salud): string
    {
        if ($pension !== null && $salud !== null) {
            return 'Parafiscales pensión '.$pension.' · salud '.$salud;
        }

        if ($pension !== null) {
            return 'Parafiscales pensión '.$pension;
        }

        if ($salud !== null) {
            return 'Parafiscales salud '.$salud;
        }

        return 'Parafiscales '.now()->format('Y-m');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function samePeriodDocuments(array $employeeIds, string $base): \Illuminate\Support\Collection
    {
        if ($employeeIds === [] || $base === '') {
            return collect();
        }

        return EmployeeDocument::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('folder', DocumentFolder::Parafiscales)
            ->where('document_type', ParafiscalDocumentType::Planilla->value)
            ->get()
            ->filter(fn (EmployeeDocument $document): bool => $this->samePeriod((string) $document->display_name, $base))
            ->values();
    }

    private function samePeriod(string $label, string $base): bool
    {
        return $this->versionOf($label, $base) > 0;
    }

    private function versionOf(string $label, string $base): int
    {
        $name = (string) preg_replace('/\.xlsx$/i', '', trim($label));
        if ($name === $base) {
            return 1;
        }

        if (preg_match('/^'.preg_quote($base, '/').' v(\d+)$/', $name, $match) === 1) {
            return max(1, (int) $match[1]);
        }

        return 0;
    }

    private function storedName(
        Employee $employee,
        DocumentFolder $folder,
        ParafiscalDocumentType $type,
        string $base,
        bool $versioned,
    ): string {
        if (! $versioned) {
            return $base.'.xlsx';
        }

        $max = 0;
        $existing = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('folder', $folder)
            ->where('document_type', $type->value)
            ->get();
        foreach ($existing as $document) {
            $max = max($max, $this->versionOf((string) $document->display_name, $base));
        }

        return $max === 0 ? $base.'.xlsx' : $base.' v'.($max + 1).'.xlsx';
    }

    private function deleteSamePeriod(
        Employee $employee,
        DocumentFolder $folder,
        ParafiscalDocumentType $type,
        string $base,
    ): void {
        $previous = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('folder', $folder)
            ->where('document_type', $type->value)
            ->get()
            ->filter(fn (EmployeeDocument $document): bool => $this->samePeriod((string) $document->display_name, $base));

        foreach ($previous as $document) {
            if ($document->hasFile()) {
                $path = StoredFileResponder::absolute((string) $document->disk_path);
                if (is_file($path)) {
                    app(RenderSpreadsheetPdfPreviewService::class)->forget($path);
                    File::delete($path);
                }
            }
            $document->delete();
        }
    }
}
