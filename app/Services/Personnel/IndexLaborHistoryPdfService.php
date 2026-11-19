<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Enums\AffiliationDocumentType;
use App\Enums\CertificateDocumentType;
use App\Enums\ContractingDocumentType;
use App\Enums\CourseDocumentType;
use App\Enums\DocumentFolder;
use App\Enums\LaborHistoryDocumentType;
use App\Enums\OtherDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentBatch;
use App\Support\Files\StoredFileResponder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;

final class IndexLaborHistoryPdfService
{
    /**
     * @param  list<array{folder?: DocumentFolder|string, type?: LaborHistoryDocumentType|AffiliationDocumentType|CertificateDocumentType|ContractingDocumentType|CourseDocumentType|OtherDocumentType, type_slug?: string, repeatable?: bool, fallback_name?: string, display_name: string, pages: list<int>, taken_on?: ?string, provider?: ?string, tipo?: ?string}>  $slices
     * @return list<EmployeeDocument>
     */
    public function execute(EmployeeDocumentBatch $batch, Employee $employee, array $slices): array
    {
        $source = StoredFileResponder::absolute($batch->disk_path);
        if (! is_file($source)) {
            throw new RuntimeException('El lote PDF ya no está en disco.');
        }

        $created = [];
        foreach ($slices as $slice) {
            $created[] = $this->storeSlice($batch, $employee, $source, $slice);
        }

        return $created;
    }

    /**
     * @param  array{folder?: DocumentFolder|string, type?: LaborHistoryDocumentType|AffiliationDocumentType|CertificateDocumentType|ContractingDocumentType|CourseDocumentType|OtherDocumentType, type_slug?: string, repeatable?: bool, fallback_name?: string, display_name: string, pages: list<int>, taken_on?: ?string, provider?: ?string, tipo?: ?string}  $slice
     */
    private function storeSlice(EmployeeDocumentBatch $batch, Employee $employee, string $source, array $slice): EmployeeDocument
    {
        $pages = $this->normalizePages($slice['pages'], $batch->page_count);
        $folderValue = $slice['folder'] ?? $batch->folder ?? DocumentFolder::HojaVida->value;
        $folder = $folderValue instanceof DocumentFolder ? $folderValue->value : (string) $folderValue;
        $type = $slice['type'] ?? null;
        $typeSlug = is_object($type) ? $type->value : (string) ($slice['type_slug'] ?? '');
        $repeatable = is_object($type)
            ? method_exists($type, 'isRepeatable') && $type->isRepeatable()
            : (bool) ($slice['repeatable'] ?? false);
        $fallbackName = is_object($type) ? $type->suggestedName($employee) : (string) ($slice['fallback_name'] ?? $typeSlug);

        $relative = sprintf(
            'companies/%d/employees/%d/%s/%s-%s.pdf',
            $employee->security_company_id,
            $employee->id,
            $folder,
            $typeSlug,
            Str::uuid()->toString(),
        );
        $absolute = storage_path('app/'.$relative);
        File::ensureDirectoryExists(dirname($absolute));

        $this->extractPages($source, $absolute, $pages, $batch->page_count);

        $name = $slice['display_name'] !== '' ? $slice['display_name'] : $fallbackName;
        if (! str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }
        $label = trim((string) ($slice['tipo'] ?? ''));
        if ($label === '') {
            $label = $name;
        }

        if (! $repeatable) {
            EmployeeDocument::query()
                ->where('employee_id', $employee->id)
                ->where('folder', $folder)
                ->where('document_type', $typeSlug)
                ->delete();
        }

        return EmployeeDocument::query()->create([
            'security_company_id' => $employee->security_company_id,
            'employee_id' => $employee->id,
            'folder' => $folder,
            'document_type' => $typeSlug,
            'display_name' => $label,
            'page_from' => $pages[0],
            'page_to' => $pages[array_key_last($pages)],
            'pages' => $pages,
            'not_applicable' => false,
            'original_name' => $name,
            'disk_path' => $relative,
            'mime' => 'application/pdf',
            'size_bytes' => is_file($absolute) ? (int) filesize($absolute) : 0,
            'taken_on' => $slice['taken_on'] ?? null,
            'provider' => $slice['provider'] ?? null,
        ]);
    }

    /**
     * @param  list<int>  $pages
     * @return list<int>
     */
    private function normalizePages(array $pages, int $pageCount): array
    {
        $clean = [];
        foreach ($pages as $page) {
            $page = (int) $page;
            if ($page >= 1 && $page <= $pageCount && ! in_array($page, $clean, true)) {
                $clean[] = $page;
            }
        }

        if ($clean === []) {
            throw new RuntimeException('Seleccione al menos una página válida.');
        }

        return $clean;
    }

    /** @param list<int> $pages */
    private function extractPages(string $source, string $destination, array $pages, int $pageCount): void
    {
        if ($pages === range(1, $pageCount)) {
            File::copy($source, $destination);

            return;
        }

        $pdf = new Fpdi;
        $pdf->setSourceFile($source);
        foreach ($pages as $page) {
            $tpl = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'] ?? 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
        $pdf->Output('F', $destination);
    }
}
