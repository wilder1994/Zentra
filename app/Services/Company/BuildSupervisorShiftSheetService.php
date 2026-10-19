<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisorFieldSheet;
use App\Enums\SupervisorChecklistKind;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorFieldSheetKind;
use App\Enums\SupervisorShiftStatus;
use App\Models\SecurityCompany;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Support\Supervision\SupervisorFieldSheetIntro;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

final class BuildSupervisorShiftSheetService
{
    public function __construct(
        private readonly BuildShiftRouteMapService $routeMaps,
    ) {}

    public function forCompany(int $companyId, int $shiftId): ?SupervisorFieldSheet
    {
        return $this->build($companyId, $shiftId, null);
    }

    public function forSupervisor(int $companyId, int $userId, int $shiftId): ?SupervisorFieldSheet
    {
        return $this->build($companyId, $shiftId, $userId);
    }

    public function freeze(SupervisorShift $shift): void
    {
        $shift->loadMissing([
            'user',
            'zone',
            'shiftTemplate',
            'fleetVehicle',
            'securityCompany',
            'reviews.client',
            'fieldLogs.client',
            'locations',
        ]);

        $shift->update(['sheet_snapshot' => $this->snapshotArray($shift)]);
    }

    private function build(int $companyId, int $shiftId, ?int $userId): ?SupervisorFieldSheet
    {
        $shift = SupervisorShift::query()
            ->whereKey($shiftId)
            ->where('security_company_id', $companyId)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->with([
                'user',
                'zone',
                'shiftTemplate',
                'fleetVehicle',
                'securityCompany',
                'reviews.client',
                'fieldLogs.client',
                'locations' => fn ($q) => $q->orderBy('recorded_at'),
            ])
            ->first();

        if ($shift === null) {
            return null;
        }

        if ($shift->status === SupervisorShiftStatus::Closed && is_array($shift->sheet_snapshot) && $shift->sheet_snapshot !== []) {
            return $this->fromSnapshot($shift);
        }

        return $this->fromShift($shift);
    }

    private function fromShift(SupervisorShift $shift): SupervisorFieldSheet
    {
        $payload = $this->snapshotArray($shift);

        return $this->sheetFromPayload($shift, $payload, $shift->isOpen());
    }

    private function fromSnapshot(SupervisorShift $shift): SupervisorFieldSheet
    {
        return $this->sheetFromPayload($shift, $shift->sheet_snapshot ?? [], false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sheetFromPayload(SupervisorShift $shift, array $payload, bool $draft): SupervisorFieldSheet
    {
        $kind = SupervisorFieldSheetKind::Shift;
        $recordedAt = isset($payload['recorded_at'])
            ? CarbonImmutable::parse((string) $payload['recorded_at'])
            : ($shift->started_at ?? now());
        $company = $shift->securityCompany;
        $brand = $this->branding($company);
        $lat = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $lng = isset($payload['longitude']) ? (float) $payload['longitude'] : null;

        $sections = [];
        foreach ($payload['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            $photos = [];
            foreach ($section['photos'] ?? [] as $photo) {
                if (! is_array($photo)) {
                    continue;
                }
                $src = $this->dataUri(isset($photo['path']) ? (string) $photo['path'] : null);
                if ($src !== null) {
                    $photos[] = ['label' => (string) ($photo['label'] ?? 'Foto'), 'src' => $src];
                }
            }
            $title = (string) ($section['title'] ?? '');
            $rows = array_values(array_map('strval', $section['rows'] ?? []));
            $sections[] = match ($title) {
                'Preoperacional' => ['title' => $title, 'rows' => [], 'photos' => $photos, 'fields' => $this->checklistFields($rows)],
                'Actividad del turno' => ['title' => $title, 'photos' => $photos] + $this->activityTable($rows),
                default => ['title' => $title, 'rows' => $rows, 'photos' => $photos],
            };
        }

        return new SupervisorFieldSheet(
            kind: $kind,
            id: (int) $shift->id,
            folio: (string) ($payload['folio'] ?? $kind->folio((int) $shift->id, $recordedAt)),
            companyName: $brand['display'],
            companyLegalName: $brand['legal'],
            companyTaxId: $brand['taxId'],
            companyLogoSrc: $brand['logo'],
            intro: (string) ($payload['intro'] ?? SupervisorFieldSheetIntro::resolve($company?->field_sheet_intro)),
            supervisorName: (string) ($payload['supervisor_name'] ?? $shift->user?->name ?? 'Supervisor'),
            username: $payload['username'] ?? $shift->user?->username,
            zoneName: $payload['zone_name'] ?? $shift->zone?->name,
            shiftLabel: $payload['shift_label'] ?? $shift->schedule_label,
            recordedAt: $recordedAt,
            clientName: null,
            installationName: null,
            postName: null,
            guardName: null,
            hasNovelty: (bool) ($payload['has_novelty'] ?? false),
            notes: isset($payload['notes']) ? (string) $payload['notes'] : $shift->notes,
            latitude: $lat,
            longitude: $lng,
            guardPhotoSrc: $this->dataUri(isset($payload['selfie_path']) ? (string) $payload['selfie_path'] : $shift->km_start_selfie_path),
            sections: array_values(array_filter($sections, static fn (array $section): bool => ! in_array($section['title'], ['Inicio de turno', 'Cierre de turno'], true))),
            isDraft: $draft,
            hero: $this->heroBlock($shift, $payload),
            odometer: $this->odometerBlock($shift),
            routeMap: $this->routeBlock($shift),
            signatures: ['Supervisor', 'Coordinación / central'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{photos: list<array{label: string, src: ?string, pending?: ?string}>, fields: list<array{label: string, value: string}>}
     */
    private function heroBlock(SupervisorShift $shift, array $payload): array
    {
        $tz = (string) config('app.timezone');
        $user = (string) ($payload['supervisor_name'] ?? $shift->user?->name ?? 'Supervisor');
        $username = $payload['username'] ?? $shift->user?->username;
        $end = match (true) {
            $shift->isOpen() => 'Turno abierto',
            $shift->ended_at !== null => $shift->ended_at->timezone($tz)->format('d/m/Y H:i').($shift->closed_by_system ? ' · por el sistema' : ''),
            default => '—',
        };

        return [
            'photos' => [
                [
                    'label' => 'Selfie de inicio',
                    'src' => $this->dataUri(isset($payload['selfie_path']) ? (string) $payload['selfie_path'] : $shift->km_start_selfie_path),
                    'pending' => 'Sin foto',
                ],
                [
                    'label' => 'Selfie de cierre',
                    'src' => $this->dataUri($shift->km_end_selfie_path),
                    'pending' => $shift->isOpen() ? 'Pendiente cierre' : ($shift->closed_by_system ? 'Cierre por el sistema' : 'Sin foto'),
                ],
            ],
            'fields' => [
                ['label' => 'Supervisor', 'value' => $user.($username ? ' ('.$username.')' : '')],
                ['label' => 'Turno', 'value' => (string) ($payload['shift_label'] ?? $shift->schedule_label ?? '—')],
                ['label' => 'Zona', 'value' => (string) ($payload['zone_name'] ?? $shift->zone?->name ?? '—')],
                ['label' => 'Placa', 'value' => (string) ($shift->fleetVehicle?->plate ?? '—')],
                ['label' => 'Inicio', 'value' => $shift->started_at?->timezone($tz)->format('d/m/Y H:i') ?? '—'],
                ['label' => 'Cierre', 'value' => $end],
            ],
        ];
    }

    /**
     * @return array{photos: list<array{label: string, src: ?string, pending?: ?string}>, fields: list<array{label: string, value: string, strong?: bool}>}
     */
    private function odometerBlock(SupervisorShift $shift): array
    {
        $traveled = $shift->km_traveled ?? ($shift->km_end !== null && $shift->km_start !== null ? (int) $shift->km_end - (int) $shift->km_start : null);
        $pending = $shift->isOpen() ? 'Pendiente cierre' : 'Sin foto';

        return [
            'photos' => [
                ['label' => 'Odómetro de inicio', 'src' => $this->dataUri($shift->km_start_photo_path), 'pending' => 'Sin foto'],
                ['label' => 'Odómetro final', 'src' => $this->dataUri($shift->km_end_photo_path), 'pending' => $pending],
            ],
            'fields' => [
                ['label' => 'Km inicio', 'value' => $this->km($shift->km_start)],
                ['label' => 'Km final', 'value' => $shift->km_end !== null ? $this->km($shift->km_end) : ($shift->isOpen() ? 'Pendiente' : '—')],
                ['label' => 'Km recorridos', 'value' => $traveled !== null ? $this->km($traveled) : '—', 'strong' => true],
            ],
        ];
    }

    /**
     * @return array{src: ?string, fields: list<array{label: string, value: string}>, note: ?string}
     */
    private function routeBlock(SupervisorShift $shift): array
    {
        $route = $this->routeMaps->execute($shift);
        $start = $shift->started_at;
        $end = $shift->ended_at ?? ($shift->isOpen() ? now() : null);
        $minutes = $start !== null && $end !== null ? max(0, (int) $start->diffInMinutes($end)) : null;
        $standalone = $shift->fieldLogs->filter(fn (SupervisorFieldLog $log) => in_array($log->module, [SupervisorFieldModule::Alarms, SupervisorFieldModule::Supports, SupervisorFieldModule::Documents], true))->count();

        $note = null;
        if (! $route['has_points']) {
            $note = 'Sin puntos GPS en este turno.';
        } elseif ($shift->isOpen()) {
            $note = 'En curso: recorrido hasta las '.now()->timezone((string) config('app.timezone'))->format('H:i').'.';
        }

        return [
            'src' => $route['src'],
            'fields' => [
                ['label' => 'Km por GPS', 'value' => number_format($route['km'], 1, ',', '.')],
                ['label' => 'Duración', 'value' => $minutes !== null ? intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' min' : '—'],
                ['label' => 'Revistas', 'value' => (string) $shift->reviews->count()],
                ['label' => 'Alarmas, apoyos y documentos', 'value' => (string) $standalone],
                ['label' => 'Paradas', 'value' => (string) $route['stops']],
            ],
            'note' => $note,
        ];
    }

    /**
     * «EPP:» / «Casco: Sí» → «EPP · Casco» = Sí.
     *
     * @param  list<string>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function checklistFields(array $rows): array
    {
        $group = '';
        $fields = [];
        foreach ($rows as $row) {
            if (str_ends_with($row, ':')) {
                $group = rtrim($row, ':');

                continue;
            }
            $parts = explode(': ', $row, 2);
            if (count($parts) !== 2) {
                $fields[] = ['label' => $group ?: 'Detalle', 'value' => $row];

                continue;
            }
            $fields[] = ['label' => ($group !== '' && $group !== $parts[0] ? $group.' · ' : '').$parts[0], 'value' => $parts[1]];
        }

        return $fields;
    }

    /**
     * «15/10 09:06 · Revista · Cliente · novedad» → tabla.
     *
     * @param  list<string>  $rows
     */
    private function activityTable(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $parts = explode(' · ', $row);
            if (count($parts) < 2) {
                return ['rows' => $rows];
            }
            $novelty = end($parts) === 'novedad';
            if ($novelty) {
                array_pop($parts);
            }
            $out[] = [
                'cells' => [$parts[0], $parts[1], $parts[2] ?? '—', $novelty ? 'Sí' : 'No'],
                'tone' => $novelty ? 'nov' : null,
            ];
        }

        return ['rows' => [], 'table' => ['head' => ['Hora', 'Registro', 'Cliente', 'Novedad'], 'rows' => $out, 'center' => [3]]];
    }

    private function km(int|float|string|null $value): string
    {
        return $value === null || $value === '' ? '—' : number_format((float) $value, 0, ',', '.').' km';
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotArray(SupervisorShift $shift): array
    {
        $started = $shift->started_at ?? now();
        $kind = SupervisorFieldSheetKind::Shift;
        $first = $shift->locations->sortBy('recorded_at')->first();
        $last = $shift->locations->sortByDesc('recorded_at')->first();
        $lat = $first?->latitude !== null ? (float) $first->latitude : ($last?->latitude !== null ? (float) $last->latitude : null);
        $lng = $first?->longitude !== null ? (float) $first->longitude : ($last?->longitude !== null ? (float) $last->longitude : null);
        $reviews = $shift->reviews;
        $logs = $shift->fieldLogs;
        $hasNovelty = $reviews->contains(fn (SupervisorShiftReview $review) => $review->has_novelty)
            || $logs->contains(fn (SupervisorFieldLog $log) => $log->outcome !== null && $log->outcome !== SupervisorFieldOutcome::Ok);

        $activity = [];
        foreach ($reviews as $review) {
            $at = $review->recorded_at;
            $line = ($at?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—')
                .' · Revista'.($review->client?->name ? ' · '.$review->client->name : '')
                .($review->has_novelty ? ' · novedad' : '');
            $activity[] = $line;
        }
        foreach ($logs as $log) {
            if (! in_array($log->module, [SupervisorFieldModule::Alarms, SupervisorFieldModule::Supports, SupervisorFieldModule::Documents], true)) {
                continue;
            }
            $at = $log->recorded_at;
            $activity[] = ($at?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—')
                .' · '.$log->module->label()
                .($log->client?->name ? ' · '.$log->client->name : '');
        }

        $ppe = $this->checklistRows((int) $shift->security_company_id, SupervisorChecklistKind::Ppe, is_array($shift->ppe_checklist) ? $shift->ppe_checklist : []);
        $vehicle = $this->checklistRows((int) $shift->security_company_id, SupervisorChecklistKind::Vehicle, is_array($shift->vehicle_checklist) ? $shift->vehicle_checklist : []);

        $startPhotos = array_values(array_filter([
            $this->photoRef('Selfie de inicio', $shift->km_start_selfie_path),
            $this->photoRef('Odómetro de inicio', $shift->km_start_photo_path),
        ]));
        $endPhotos = array_values(array_filter([
            $this->photoRef('Selfie de cierre', $shift->km_end_selfie_path),
            $this->photoRef('Odómetro de cierre', $shift->km_end_photo_path),
        ]));

        $endRows = [
            'Fin: '.($shift->ended_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? ($shift->isOpen() ? 'Turno abierto' : '—')),
            'Km cierre: '.($shift->km_end ?? '—'),
            'Km recorridos: '.($shift->km_traveled ?? '—'),
        ];
        if ($shift->closed_by_system) {
            $endRows[] = 'Cierre por el sistema (sin fotos de cierre).';
        }

        $gpsRows = [
            'Inicio GPS: '.($first !== null ? number_format((float) $first->latitude, 6).', '.number_format((float) $first->longitude, 6) : 'Sin GPS aún'),
        ];
        if ($last !== null && ($last->id !== $first?->id)) {
            $gpsRows[] = 'Último GPS: '.number_format((float) $last->latitude, 6).', '.number_format((float) $last->longitude, 6);
        }

        return [
            'folio' => $kind->folio((int) $shift->id, $started),
            'recorded_at' => $started->toIso8601String(),
            'intro' => SupervisorFieldSheetIntro::resolve($shift->securityCompany?->field_sheet_intro),
            'supervisor_name' => $shift->user?->name ?? 'Supervisor',
            'username' => $shift->user?->username,
            'zone_name' => $shift->zone?->name ?? $shift->route_zone,
            'shift_label' => $shift->schedule_label ?? $shift->shiftTemplate?->scheduleLabel(),
            'latitude' => $lat,
            'longitude' => $lng,
            'has_novelty' => $hasNovelty,
            'notes' => $shift->notes,
            'selfie_path' => $shift->km_start_selfie_path,
            'sections' => [
                [
                    'title' => 'Inicio de turno',
                    'rows' => [
                        'Inicio: '.$started->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                        'Vehículo: '.($shift->fleetVehicle?->plate ?? '—'),
                        'Km inicio: '.($shift->km_start ?? '—'),
                        ...$gpsRows,
                    ],
                    'photos' => $startPhotos,
                ],
                [
                    'title' => 'Preoperacional',
                    'rows' => array_merge(
                        $ppe !== [] ? array_merge(['EPP:'], $ppe) : ['EPP: sin registro'],
                        $vehicle !== [] ? array_merge(['Vehículo:'], $vehicle) : ['Vehículo: sin registro'],
                    ),
                    'photos' => [],
                ],
                [
                    'title' => 'Actividad del turno',
                    'rows' => $activity !== [] ? $activity : ['Sin revistas, alarmas ni apoyos aún.'],
                    'photos' => [],
                ],
                [
                    'title' => 'Cierre de turno',
                    'rows' => $endRows,
                    'photos' => $endPhotos,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function checklistRows(int $companyId, SupervisorChecklistKind $kind, array $payload): array
    {
        $labels = SupervisorChecklistItem::keyedLabels($companyId, $kind);
        $rows = [];
        foreach ($labels as $key => $label) {
            $ok = (bool) ($payload[$key] ?? false);
            $rows[] = $label.': '.($ok ? 'Sí' : 'No');
        }

        return $rows;
    }

    /** @return array{label: string, path: string}|null */
    private function photoRef(string $label, ?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        return ['label' => $label, 'path' => $path];
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
