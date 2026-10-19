<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisorFieldSheet;
use App\Enums\SupervisorAlarmKind;
use App\Enums\SupervisorAlarmResult;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorFieldSheetKind;
use App\Enums\SupervisorRiskImpact;
use App\Enums\SupervisorRiskLevel;
use App\Enums\SupervisorRiskLikelihood;
use App\Enums\SupervisorTrainingKind;
use App\Enums\SupervisorTrainingModality;
use App\Enums\SupervisorWeaponPermitKind;
use App\Models\SecurityCompany;
use App\Models\SupervisorAlarmType;
use App\Models\SupervisorControlBookType;
use App\Models\SupervisorDocumentType;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use App\Support\Supervision\RecommendationEvidencePhotos;
use App\Support\Supervision\SupervisorFieldSheetIntro;
use App\Support\Supervision\UniformInspection;
use App\Support\Supervision\WeaponInspectionPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

final class BuildSupervisorFieldSheetService
{
    public function __construct(
        private readonly BuildSupervisorShiftSheetService $shiftSheets,
    ) {}

    public function forCompany(int $companyId, SupervisorFieldSheetKind $kind, int $id): ?SupervisorFieldSheet
    {
        return $this->build($companyId, $kind, $id, null);
    }

    public function forSupervisor(int $companyId, int $userId, SupervisorFieldSheetKind $kind, int $id): ?SupervisorFieldSheet
    {
        return $this->build($companyId, $kind, $id, $userId);
    }

    private function build(int $companyId, SupervisorFieldSheetKind $kind, int $id, ?int $userId): ?SupervisorFieldSheet
    {
        if ($kind === SupervisorFieldSheetKind::Shift) {
            return $userId === null
                ? $this->shiftSheets->forCompany($companyId, $id)
                : $this->shiftSheets->forSupervisor($companyId, $userId, $id);
        }

        if ($kind === SupervisorFieldSheetKind::Review) {
            $review = SupervisorShiftReview::query()
                ->whereKey($id)
                ->whereHas('shift', function ($query) use ($companyId, $userId): void {
                    $query->where('security_company_id', $companyId);
                    if ($userId !== null) {
                        $query->where('user_id', $userId);
                    }
                })
                ->with([
                    'client:id,name',
                    'supervisorPost:id,name,installation_id',
                    'supervisorPost.installation:id,name',
                    'employee:id,first_names,last_name_paternal,last_name_maternal,document_number',
                    'shift.user:id,name,username',
                    'shift.zone:id,name',
                    'zone:id,name',
                    'shift.securityCompany',
                    'fieldLogs',
                ])
                ->first();

            return $review === null ? null : $this->fromReview($review);
        }

        $module = $kind->standaloneModule();
        if ($module === null) {
            return null;
        }

        $log = SupervisorFieldLog::query()
            ->whereKey($id)
            ->where('security_company_id', $companyId)
            ->where('module', $module)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->with(['client:id,name', 'user:id,name,username', 'shift.zone:id,name', 'zone:id,name', 'shift.securityCompany', 'company'])
            ->first();

        return $log === null ? null : $this->fromLog($log);
    }

    private function fromReview(SupervisorShiftReview $review): SupervisorFieldSheet
    {
        $shift = $review->shift;
        $kind = SupervisorFieldSheetKind::Review;
        $recordedAt = $review->recorded_at ?? now();
        $sections = [];
        $summary = [];

        foreach ($review->fieldLogs->sortBy(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Uniform ? 0 : 1) as $log) {
            $section = $this->sectionFromLog($log);
            $sections[] = $section;
            $summary[] = ['label' => $section['title'], 'novelty' => (bool) $section['novelty']];
        }

        $company = $shift?->securityCompany;
        $brand = $this->branding($company);
        $guardName = $review->employee?->fullName();
        $hasNovelty = (bool) $review->has_novelty || collect($summary)->contains('novelty', true);
        $tz = (string) config('app.timezone');
        $heroFields = array_values(array_filter([
            ['label' => 'Vigilante', 'value' => $guardName ?: '—'],
            $review->employee?->document_number ? ['label' => 'Cédula', 'value' => (string) $review->employee->document_number] : null,
            ['label' => 'Cliente', 'value' => $review->client?->name ?? '—'],
            ['label' => 'Instalación', 'value' => $review->supervisorPost?->installation?->name ?? '—'],
            ['label' => 'Puesto', 'value' => $review->supervisorPost?->name ?? '—'],
            ['label' => 'Supervisor', 'value' => ($shift?->user?->name ?? 'Supervisor').($shift?->user?->username ? ' ('.$shift->user->username.')' : '')],
            ['label' => 'Zona / turno', 'value' => trim(($review->zone?->name ?? $shift?->zone?->name ?? '—').($shift?->schedule_label ? ' · '.$shift->schedule_label : ''))],
            ['label' => 'Fecha y hora', 'value' => CarbonImmutable::parse($recordedAt)->timezone($tz)->format('d/m/Y H:i')],
            $review->latitude !== null && $review->longitude !== null
                ? ['label' => 'GPS', 'value' => number_format((float) $review->latitude, 6).', '.number_format((float) $review->longitude, 6)]
                : null,
        ]));

        return new SupervisorFieldSheet(
            kind: $kind,
            id: (int) $review->id,
            folio: $kind->folio((int) $review->id, $recordedAt),
            companyName: $brand['display'],
            companyLegalName: $brand['legal'],
            companyTaxId: $brand['taxId'],
            companyLogoSrc: $brand['logo'],
            intro: SupervisorFieldSheetIntro::resolve($review->sheet_intro ?? $company?->field_sheet_intro),
            supervisorName: $shift?->user?->name ?? 'Supervisor',
            username: $shift?->user?->username,
            zoneName: $review->zone?->name ?? $shift?->zone?->name,
            shiftLabel: $shift?->schedule_label,
            recordedAt: $recordedAt,
            clientName: $review->client?->name,
            installationName: $review->supervisorPost?->installation?->name,
            postName: $review->supervisorPost?->name,
            guardName: $guardName !== null && $guardName !== '' ? $guardName : null,
            hasNovelty: $hasNovelty,
            notes: $review->notes,
            latitude: $review->latitude !== null ? (float) $review->latitude : null,
            longitude: $review->longitude !== null ? (float) $review->longitude : null,
            guardPhotoSrc: $this->dataUri($review->guard_photo_path),
            sections: $sections,
            hero: [
                'photos' => [['label' => 'Foto del vigilante', 'src' => $this->dataUri($review->guard_photo_path), 'pending' => 'Sin foto']],
                'fields' => $heroFields,
            ],
            moduleSummary: $summary,
            signatures: ['Supervisor', 'Vigilante'],
        );
    }

    private function fromLog(SupervisorFieldLog $log): SupervisorFieldSheet
    {
        $kind = SupervisorFieldSheetKind::fromStandaloneModule($log->module);
        if ($kind === null) {
            $kind = SupervisorFieldSheetKind::Document;
        }

        $recordedAt = $log->recorded_at ?? now();
        $shift = $log->shift;
        $company = $shift?->securityCompany ?? $log->company;
        $brand = $this->branding($company);

        return new SupervisorFieldSheet(
            kind: $kind,
            id: (int) $log->id,
            folio: $kind->folio((int) $log->id, $recordedAt),
            companyName: $brand['display'],
            companyLegalName: $brand['legal'],
            companyTaxId: $brand['taxId'],
            companyLogoSrc: $brand['logo'],
            intro: SupervisorFieldSheetIntro::resolve($company?->field_sheet_intro),
            supervisorName: $log->user?->name ?? $shift?->user?->name ?? 'Supervisor',
            username: $log->user?->username ?? $shift?->user?->username,
            zoneName: $log->zone?->name ?? $shift?->zone?->name,
            shiftLabel: $shift?->schedule_label,
            recordedAt: $recordedAt,
            clientName: $log->client?->name,
            installationName: null,
            postName: null,
            guardName: null,
            hasNovelty: $log->outcome !== null && $log->outcome->value !== 'ok',
            notes: $log->notes,
            latitude: $log->latitude !== null ? (float) $log->latitude : null,
            longitude: $log->longitude !== null ? (float) $log->longitude : null,
            guardPhotoSrc: null,
            sections: [$this->sectionFromLog($log)],
            signatures: ['Supervisor'],
        );
    }

    /**
     * @return array{title: string, rows: list<string>, photos: list<array{label: string, src: string}>}
     */
    private function sectionFromLog(SupervisorFieldLog $log): array
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $companyId = (int) $log->security_company_id;
        $text = $this->textSection($log);

        $block = match ($log->module) {
            SupervisorFieldModule::Inventory => $this->inventoryBlock($payload),
            SupervisorFieldModule::ControlBooks => $this->controlBookBlock($payload, $companyId),
            SupervisorFieldModule::Folders => $this->folderBlock($payload),
            SupervisorFieldModule::Weapons => $this->weaponBlock($payload, $text['rows']),
            SupervisorFieldModule::Recommendations => $this->recommendationBlock($payload, $companyId),
            SupervisorFieldModule::Uniform => $this->uniformBlock($payload),
            SupervisorFieldModule::Trainings => $this->trainingBlock($payload),
            SupervisorFieldModule::Alarms => ['fields' => $this->rowsAsFields($text['rows'])],
            SupervisorFieldModule::Supports => ['fields' => $this->rowsAsFields($text['rows'])],
            SupervisorFieldModule::Documents => $this->documentBlock($payload, $companyId),
        };

        return [
            'title' => $text['title'],
            'rows' => [],
            'photos' => match ($log->module) {
                SupervisorFieldModule::Weapons => $this->weaponPhotos($payload),
                SupervisorFieldModule::Recommendations => $this->recommendationPhotos($payload),
                SupervisorFieldModule::Uniform, SupervisorFieldModule::Trainings => $this->evidencePhotos($payload),
                default => [],
            },
            'novelty' => $this->logHasNovelty($log),
        ] + $block;
    }

    public function logHasNovelty(SupervisorFieldLog $log): bool
    {
        if ($log->module === SupervisorFieldModule::Trainings) {
            return false;
        }
        if ($log->outcome !== null && $log->outcome !== SupervisorFieldOutcome::Ok) {
            return true;
        }
        $payload = is_array($log->payload) ? $log->payload : [];
        if ($log->module === SupervisorFieldModule::Uniform) {
            foreach ($payload['items'] ?? [] as $item) {
                if (is_array($item) && UniformInspection::hasNovelty((string) ($item['status'] ?? ''))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * «Tipo: X» → etiqueta / valor; las líneas sin «:» quedan como «Detalle».
     *
     * @param  list<string>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function rowsAsFields(array $rows): array
    {
        return array_map(static function (string $row): array {
            $parts = explode(': ', $row, 2);

            return count($parts) === 2
                ? ['label' => $parts[0], 'value' => $parts[1] !== '' ? $parts[1] : '—']
                : ['label' => 'Detalle', 'value' => $row];
        }, $rows);
    }

    /** @param  array<string, mixed>  $payload */
    private function inventoryBlock(array $payload): array
    {
        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $status = (string) ($item['status'] ?? '');
            $rows[] = [
                'cells' => [
                    trim((string) ($item['type'] ?? 'Elemento')),
                    match ($status) { 'bad' => 'Malo', 'regular' => 'Regular', default => 'Bueno' },
                    trim((string) ($item['notes'] ?? '')) ?: '—',
                ],
                'tone' => in_array($status, ['bad', 'regular'], true) ? 'nov' : null,
            ];
        }

        return $rows === []
            ? ['rows' => ['Sin elementos.']]
            : ['table' => ['head' => ['Elemento', 'Estado', 'Observación'], 'rows' => $rows]];
    }

    /** @param  array<string, mixed>  $payload */
    private function controlBookBlock(array $payload, int $companyId): array
    {
        $ids = collect($payload['items'] ?? [])->pluck('control_book_type_id')->filter()->unique()->all();
        $names = $ids === []
            ? []
            : SupervisorControlBookType::query()->where('security_company_id', $companyId)->whereKey($ids)->pluck('name', 'id')->all();

        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $novelty = ($item['novelty'] ?? '') === 'yes';
            $rows[] = [
                'cells' => [
                    (string) ($item['control_book_type'] ?? $names[(int) ($item['control_book_type_id'] ?? 0)] ?? 'Libro'),
                    $novelty ? 'Sí' : 'No',
                    trim((string) ($item['notes'] ?? '')) ?: '—',
                ],
                'tone' => $novelty ? 'nov' : null,
            ];
        }

        return $rows === []
            ? ['rows' => ['Sin libros.']]
            : ['table' => ['head' => ['Libro', 'Novedad', 'Observación'], 'rows' => $rows, 'center' => [1]]];
    }

    /** @param  array<string, mixed>  $payload */
    private function folderBlock(array $payload): array
    {
        $missing = ($payload['status'] ?? '') === 'missing';

        return [
            'fields' => [['label' => 'Estado', 'value' => $missing ? 'Con faltantes' : 'Completa']],
            'note' => $missing && ! empty($payload['missing_items']) ? 'Faltantes: '.$payload['missing_items'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $rows
     */
    private function weaponBlock(array $payload, array $rows): array
    {
        $notes = trim((string) ($payload['notes'] ?? ''));
        $fields = $this->rowsAsFields(array_values(array_filter($rows, static fn (string $row): bool => $row !== $notes)));

        return ['fields' => $fields, 'note' => $notes !== '' ? 'Observación: '.$notes : null];
    }

    /** @param  array<string, mixed>  $payload */
    private function recommendationBlock(array $payload, int $companyId): array
    {
        $ids = collect($payload['items'] ?? [])->pluck('risk_type_id')->filter()->unique()->all();
        $names = $ids === []
            ? []
            : SupervisorRiskType::query()->where('security_company_id', $companyId)->whereKey($ids)->pluck('name', 'id')->all();

        $rows = [];
        foreach ($payload['items'] ?? [] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $level = SupervisorRiskLevel::tryFrom((string) ($item['risk_level'] ?? ''));
            $likelihood = SupervisorRiskLikelihood::tryFrom((string) ($item['likelihood'] ?? ''));
            $impact = SupervisorRiskImpact::tryFrom((string) ($item['impact'] ?? ''));
            $rows[] = [
                'cells' => [
                    ($index + 1).'. '.($item['risk_type'] ?? $names[(int) ($item['risk_type_id'] ?? 0)] ?? 'Riesgo')
                        ."\n".($level?->label() ?? '—').' · P: '.($likelihood?->label() ?? '—').' · I: '.($impact?->label() ?? '—'),
                    trim((string) ($item['risk'] ?? '')).(! empty($item['consequence']) ? "\nConsecuencia: ".$item['consequence'] : ''),
                    trim((string) ($item['treatment'] ?? '')) ?: '—',
                ],
                'tone' => 'nov',
            ];
        }

        return $rows === []
            ? ['rows' => ['Sin recomendaciones.']]
            : ['table' => ['head' => ['Riesgo', 'Descripción', 'Recomendación'], 'rows' => $rows]];
    }

    /** @param  array<string, mixed>  $payload */
    private function uniformBlock(array $payload): array
    {
        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $status = UniformInspection::normalize((string) ($item['status'] ?? ''));
            $rows[] = [
                'cells' => [
                    trim((string) ($item['item'] ?? 'Prenda')),
                    $status === 'yes' ? '✓' : '',
                    $status === 'no' ? '✓' : '',
                    $status === 'na' ? '✓' : '',
                ],
                'tone' => $status === 'yes' ? 'nov' : null,
            ];
        }
        $notes = trim((string) ($payload['notes'] ?? ''));

        return [
            'table' => ['head' => ['Prenda', 'Novedad: Sí', 'Novedad: No', 'No aplica'], 'rows' => $rows, 'center' => [1, 2, 3]],
            'note' => $notes !== '' ? 'Observación del supervisor: '.$notes : null,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function trainingBlock(array $payload): array
    {
        $kind = SupervisorTrainingKind::tryFrom((string) ($payload['kind'] ?? ''));
        $modality = SupervisorTrainingModality::tryFrom((string) ($payload['modality'] ?? ''));
        $fields = [
            ['label' => 'Tema', 'value' => (string) ($payload['topic_name'] ?? '—')],
            ['label' => 'Tipo', 'value' => $kind?->label() ?? '—'],
            ['label' => 'Modalidad', 'value' => $modality?->label() ?? '—'],
        ];
        if (! empty($payload['minutes'])) {
            $tz = (string) config('app.timezone');
            $from = ! empty($payload['started_at']) ? CarbonImmutable::parse((string) $payload['started_at'])->timezone($tz)->format('H:i') : null;
            $to = ! empty($payload['ended_at']) ? CarbonImmutable::parse((string) $payload['ended_at'])->timezone($tz)->format('H:i') : null;
            $fields[] = ['label' => 'Duración', 'value' => $payload['minutes'].' min'.($from && $to ? ' ('.$from.' – '.$to.')' : '')];
        }
        if (! empty($payload['reason'])) {
            $fields[] = ['label' => 'Motivo / falencia', 'value' => (string) $payload['reason']];
        }
        if (! empty($payload['description'])) {
            $fields[] = ['label' => 'Descripción', 'value' => (string) $payload['description']];
        }

        return ['fields' => $fields];
    }

    /** @param  array<string, mixed>  $payload */
    private function documentBlock(array $payload, int $companyId): array
    {
        $ids = collect($payload['items'] ?? [])->pluck('document_type_id')->filter()->unique()->all();
        $names = $ids === []
            ? []
            : SupervisorDocumentType::query()->where('security_company_id', $companyId)->whereKey($ids)->pluck('name', 'id')->all();

        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $pending = (int) ($item['pending'] ?? 0);
            $rows[] = [
                'cells' => [
                    (string) ($item['document_type'] ?? $names[(int) ($item['document_type_id'] ?? 0)] ?? 'Documento'),
                    (string) ((int) ($item['delivered'] ?? 0)),
                    (string) $pending,
                    trim((string) ($item['notes'] ?? '')) ?: '—',
                ],
                'tone' => $pending > 0 ? 'warn' : null,
            ];
        }

        return $rows === []
            ? ['rows' => ['Sin documentos.']]
            : ['table' => ['head' => ['Documento', 'Entregados', 'Pendientes', 'Observación'], 'rows' => $rows, 'center' => [1, 2]]];
    }

    /**
     * Título y renglones de un registro, sin fotos (correo de novedades, ficha).
     *
     * @return array{title: string, rows: list<string>}
     */
    public function textSection(SupervisorFieldLog $log): array
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $companyId = (int) $log->security_company_id;

        return match ($log->module) {
            SupervisorFieldModule::Inventory => ['title' => 'Inventario', 'rows' => $this->inventoryRows($payload)],
            SupervisorFieldModule::ControlBooks => ['title' => 'Libros de control', 'rows' => $this->controlBookRows($payload, $companyId)],
            SupervisorFieldModule::Folders => ['title' => 'Carpetas', 'rows' => $this->folderRows($payload)],
            SupervisorFieldModule::Weapons => ['title' => 'Armamento', 'rows' => $this->weaponRows($payload, $companyId)],
            SupervisorFieldModule::Recommendations => ['title' => 'Recomendaciones', 'rows' => $this->recommendationRows($payload, $companyId)],
            SupervisorFieldModule::Uniform => ['title' => 'Presentación personal', 'rows' => $this->uniformRows($payload)],
            SupervisorFieldModule::Trainings => ['title' => 'Capacitación', 'rows' => $this->trainingRows($payload)],
            SupervisorFieldModule::Alarms => ['title' => 'Alarma', 'rows' => $this->alarmRows($payload, $companyId)],
            SupervisorFieldModule::Supports => ['title' => 'Apoyo', 'rows' => $this->supportRows($payload, $companyId)],
            SupervisorFieldModule::Documents => ['title' => 'Documentos', 'rows' => $this->documentRows($payload, $companyId)],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function inventoryRows(array $payload): array
    {
        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $status = match ($item['status'] ?? '') {
                'bad' => 'Malo',
                'regular' => 'Regular',
                default => 'Bueno',
            };
            $line = trim((string) ($item['type'] ?? 'Elemento')).' · '.$status;
            if (! empty($item['notes'])) {
                $line .= ' — '.$item['notes'];
            }
            $rows[] = $line;
        }

        return $rows !== [] ? $rows : ['Sin elementos.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function controlBookRows(array $payload, int $companyId): array
    {
        $ids = collect($payload['items'] ?? [])->pluck('control_book_type_id')->filter()->unique()->all();
        $names = $ids === []
            ? []
            : SupervisorControlBookType::query()->where('security_company_id', $companyId)->whereKey($ids)->pluck('name', 'id')->all();

        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $item['control_book_type'] ?? $names[(int) ($item['control_book_type_id'] ?? 0)] ?? 'Libro';
            $novelty = ($item['novelty'] ?? '') === 'yes' ? 'Con novedad' : 'Sin novedad';
            $line = $name.' · '.$novelty;
            if (! empty($item['notes'])) {
                $line .= ' — '.$item['notes'];
            }
            $rows[] = $line;
        }

        return $rows !== [] ? $rows : ['Sin libros.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function folderRows(array $payload): array
    {
        $status = ($payload['status'] ?? '') === 'missing' ? 'Con faltantes' : 'Completa';
        $rows = [$status];
        if (! empty($payload['missing_items'])) {
            $rows[] = (string) $payload['missing_items'];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function weaponRows(array $payload, int $companyId): array
    {
        $type = SupervisorWeaponType::query()
            ->where('security_company_id', $companyId)
            ->whereKey((int) ($payload['weapon_type_id'] ?? 0))
            ->value('name');
        $brand = SupervisorWeaponBrand::query()
            ->where('security_company_id', $companyId)
            ->whereKey((int) ($payload['weapon_brand_id'] ?? 0))
            ->value('name');
        $permit = SupervisorWeaponPermitKind::tryFrom((string) ($payload['permit_kind'] ?? ''));

        $rows = [
            'Tipo: '.($type ?? '—'),
            'Marca: '.($brand ?? '—'),
            'Serial: '.($payload['serial'] ?? '—'),
            'Calibre: '.($payload['caliber'] ?? '—'),
            'Permiso: '.($permit?->label() ?? '—').' '.($payload['permit_number'] ?? ''),
            'Vence: '.($payload['permit_expires_at'] ?? '—'),
            'Munición: '.($payload['ammo_quantity'] ?? '—').' · '.($payload['ammo_caliber'] ?? ''),
            'Novedad: '.(($payload['novelty'] ?? '') === 'yes' ? 'Sí' : 'No'),
            'Aseo: '.(($payload['cleaned'] ?? '') === 'yes' ? 'Sí' : 'No'),
        ];
        if (! empty($payload['notes'])) {
            $rows[] = (string) $payload['notes'];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, src: string}>
     */
    private function weaponPhotos(array $payload): array
    {
        $photos = is_array($payload['photos'] ?? null) ? $payload['photos'] : [];
        $out = [];
        foreach (WeaponInspectionPhotos::slots() as $slot) {
            $src = $this->dataUri(isset($photos[$slot['key']]) ? (string) $photos[$slot['key']] : null);
            if ($src !== null) {
                $out[] = ['label' => $slot['label'], 'src' => $src];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function recommendationRows(array $payload, int $companyId): array
    {
        $ids = collect($payload['items'] ?? [])->pluck('risk_type_id')->filter()->unique()->all();
        $names = $ids === []
            ? []
            : SupervisorRiskType::query()->where('security_company_id', $companyId)->whereKey($ids)->pluck('name', 'id')->all();

        $rows = [];
        foreach ($payload['items'] ?? [] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $n = $index + 1;
            $type = $item['risk_type'] ?? $names[(int) ($item['risk_type_id'] ?? 0)] ?? 'Riesgo';
            $level = SupervisorRiskLevel::tryFrom((string) ($item['risk_level'] ?? ''));
            $likelihood = SupervisorRiskLikelihood::tryFrom((string) ($item['likelihood'] ?? ''));
            $impact = SupervisorRiskImpact::tryFrom((string) ($item['impact'] ?? ''));
            $rows[] = $n.'. '.$type.' · '.($level?->label() ?? '');
            $rows[] = 'Riesgo: '.($item['risk'] ?? '');
            $rows[] = 'P: '.($likelihood?->label() ?? '').' · I: '.($impact?->label() ?? '');
            $rows[] = 'Consecuencia: '.($item['consequence'] ?? '');
            $rows[] = 'Tratamiento: '.($item['treatment'] ?? '');
        }

        return $rows !== [] ? $rows : ['Sin recomendaciones.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, src: string}>
     */
    private function recommendationPhotos(array $payload): array
    {
        $out = [];
        foreach ($payload['items'] ?? [] as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $photos = is_array($item['photos'] ?? null) ? $item['photos'] : [];
            foreach (RecommendationEvidencePhotos::slots() as $slot) {
                $src = $this->dataUri(isset($photos[$slot['key']]) ? (string) $photos[$slot['key']] : null);
                if ($src !== null) {
                    $out[] = ['label' => 'Riesgo '.($index + 1).' · '.$slot['label'], 'src' => $src];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function uniformRows(array $payload): array
    {
        $rows = [($payload['novelty'] ?? '') === 'yes' ? 'Con novedad' : 'Sin novedad'];
        foreach ($payload['items'] ?? [] as $item) {
            if (is_array($item)) {
                $rows[] = trim((string) ($item['item'] ?? 'Prenda')).' · '.UniformInspection::label((string) ($item['status'] ?? ''));
            }
        }
        if (! empty($payload['notes'])) {
            $rows[] = 'Novedad: '.$payload['notes'];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function trainingRows(array $payload): array
    {
        $kind = SupervisorTrainingKind::tryFrom((string) ($payload['kind'] ?? ''));
        $modality = SupervisorTrainingModality::tryFrom((string) ($payload['modality'] ?? ''));
        $rows = [
            'Tema: '.($payload['topic_name'] ?? '—'),
            trim(($kind?->label() ?? '—').' · '.($modality?->label() ?? '—')),
        ];
        if (! empty($payload['reason'])) {
            $rows[] = 'Motivo: '.$payload['reason'];
        }
        if (! empty($payload['description'])) {
            $rows[] = 'Descripción: '.$payload['description'];
        }
        if (! empty($payload['minutes'])) {
            $tz = (string) config('app.timezone');
            $from = ! empty($payload['started_at']) ? CarbonImmutable::parse((string) $payload['started_at'])->timezone($tz)->format('H:i') : null;
            $to = ! empty($payload['ended_at']) ? CarbonImmutable::parse((string) $payload['ended_at'])->timezone($tz)->format('H:i') : null;
            $rows[] = 'Duración: '.$payload['minutes'].' min'.($from && $to ? ' ('.$from.' – '.$to.')' : '');
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, src: string}>
     */
    private function evidencePhotos(array $payload): array
    {
        $out = [];
        $n = 1;
        foreach (is_array($payload['photos'] ?? null) ? $payload['photos'] : [] as $path) {
            $src = $this->dataUri(is_string($path) ? $path : null);
            if ($src !== null) {
                $out[] = ['label' => 'Evidencia '.$n++, 'src' => $src];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function alarmRows(array $payload, int $companyId): array
    {
        $type = SupervisorAlarmType::query()
            ->where('security_company_id', $companyId)
            ->whereKey((int) ($payload['alarm_type_id'] ?? 0))
            ->value('name');
        $kind = SupervisorAlarmKind::tryFrom((string) ($payload['kind'] ?? ''));
        $result = SupervisorAlarmResult::tryFrom((string) ($payload['result'] ?? ''));

        return [
            'Tipo: '.($type ?? '—'),
            'Modalidad: '.($kind?->label() ?? '—'),
            'Resultado: '.($result?->label() ?? '—'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function supportRows(array $payload, int $companyId): array
    {
        $type = SupervisorSupportType::query()
            ->where('security_company_id', $companyId)
            ->whereKey((int) ($payload['support_type_id'] ?? 0))
            ->value('name');

        $rows = ['Tipo: '.($type ?? '—')];
        if (isset($payload['novelty'])) {
            $rows[] = $payload['novelty'] === 'no' ? 'Sin novedad' : 'Con novedad';
        }
        if (! empty($payload['reason'])) {
            $rows[] = 'Motivo: '.$payload['reason'];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function documentRows(array $payload, int $companyId): array
    {
        $ids = collect($payload['items'] ?? [])->pluck('document_type_id')->filter()->unique()->all();
        $names = $ids === []
            ? []
            : SupervisorDocumentType::query()->where('security_company_id', $companyId)->whereKey($ids)->pluck('name', 'id')->all();

        $rows = [];
        foreach ($payload['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $item['document_type'] ?? $names[(int) ($item['document_type_id'] ?? 0)] ?? 'Documento';
            $line = $name.' · Entregados: '.((int) ($item['delivered'] ?? 0)).' · Pendientes: '.((int) ($item['pending'] ?? 0));
            if (! empty($item['notes'])) {
                $line .= ' — '.$item['notes'];
            }
            $rows[] = $line;
        }

        return $rows !== [] ? $rows : ['Sin documentos.'];
    }

    /**
     * @return array{display: string, legal: string, taxId: ?string, logo: ?string}
     */
    private function branding(?SecurityCompany $company): array
    {
        $display = $company?->displayName() ?? 'Empresa';

        return [
            'display' => $display,
            'legal' => (string) ($company?->legal_name ?: $display),
            'taxId' => $company?->tax_id,
            'logo' => $this->dataUri($company?->logo_path),
        ];
    }

    private function dataUri(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
