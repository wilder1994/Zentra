<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\InstallationKind;
use App\Enums\ObservatoryEventStatus;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Models\User;
use App\Services\Ops\RecordOperationalAlertService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitObservatoryReportService
{
    /**
     * @param  array{installation_id: int, kind: string, body: string, is_anonymous: bool, source: ObservatoryReportSource|string, reporter_role: ObservatoryReporterRole|string, reporter_name?: ?string, reporter_phone?: ?string, reported_by?: ?User, photo?: ?UploadedFile, photos?: list<UploadedFile>, latitude?: ?float, longitude?: ?float}  $data
     */
    public function execute(Client $client, array $data, ?string $ip = null): ObservatoryReport
    {
        $source = $data['source'] instanceof ObservatoryReportSource
            ? $data['source']
            : ObservatoryReportSource::tryFrom((string) $data['source']);
        $role = $data['reporter_role'] instanceof ObservatoryReporterRole
            ? $data['reporter_role']
            : ObservatoryReporterRole::tryFrom((string) $data['reporter_role']);

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereKey((int) $data['installation_id'])
            ->when(
                $source === ObservatoryReportSource::Comunidad,
                fn ($q) => $q->where('kind', InstallationKind::Colegio->value),
            )
            ->first();

        if ($installation === null) {
            throw ValidationException::withMessages([
                'installation_id' => $source === ObservatoryReportSource::Comunidad
                    ? 'Elige un colegio de esta Secretaría.'
                    : 'Elige una instalación de este cliente.',
            ]);
        }

        $type = app(EnsureObservatoryReportTypesService::class)->resolve($client, (string) $data['kind']);
        if ($type === null) {
            throw ValidationException::withMessages([
                'kind' => 'Indica el tipo de situación.',
            ]);
        }

        if ($source === null || $role === null || ! $role->allowedFor($source)) {
            throw ValidationException::withMessages([
                'reporter_role' => 'Indica quién reporta.',
            ]);
        }

        $body = trim((string) $data['body']);
        $anonymous = (bool) $data['is_anonymous'];
        $photoPaths = $this->storePhotos($data);
        $photoPath = $photoPaths[0] ?? null;
        $coords = $this->coordinates($data, $installation);
        $reporter = $data['reported_by'] ?? null;
        $reporter = $reporter instanceof User ? $reporter : null;

        $report = DB::transaction(function () use ($client, $installation, $type, $body, $anonymous, $data, $photoPath, $photoPaths, $ip, $coords, $source, $role, $reporter): ObservatoryReport {
            $event = $this->openOrAttach($client, $installation, $type);

            return ObservatoryReport::query()->create([
                'event_id' => $event->id,
                'client_id' => $client->id,
                'installation_id' => $installation->id,
                'source' => $source,
                'reporter_role' => $role,
                'kind' => $type->slug,
                'observatory_report_type_id' => $type->id,
                'body' => $body,
                'is_anonymous' => $anonymous,
                'reporter_name' => $anonymous ? null : $this->nullable($data['reporter_name'] ?? null),
                'reporter_phone' => $anonymous ? null : $this->nullable($data['reporter_phone'] ?? null),
                'reported_by_user_id' => $anonymous ? null : $reporter?->id,
                'photo_path' => $photoPath,
                'photo_paths' => $photoPaths !== [] ? $photoPaths : null,
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'ip_hash' => $ip !== null ? hash('sha256', $ip) : null,
            ]);
        });

        $report->setRelation('client', $client);
        $report->setRelation('installation', $installation);
        app(RecordOperationalAlertService::class)->observatory($report);

        return $report;
    }

    private function openOrAttach(Client $client, Installation $installation, ObservatoryReportType $type): ObservatoryEvent
    {
        $minutes = max(1, (int) config('observatory.merge_window_minutes', 60));
        $since = now()->subMinutes($minutes);

        $existing = ObservatoryEvent::query()
            ->where('client_id', $client->id)
            ->where('installation_id', $installation->id)
            ->whereNull('archived_at')
            ->whereIn('status', [
                ObservatoryEventStatus::Nuevo->value,
                ObservatoryEventStatus::EnAtencion->value,
            ])
            ->whereHas('reports', function ($q) use ($type, $since): void {
                $q->where(function ($inner) use ($type): void {
                    $inner->where('observatory_report_type_id', $type->id)
                        ->orWhere('kind', $type->slug);
                })->where('created_at', '>=', $since);
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($existing instanceof ObservatoryEvent) {
            return $existing;
        }

        return ObservatoryEvent::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'status' => ObservatoryEventStatus::Nuevo,
            'title' => $type->name,
            'opened_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function storePhotos(array $data): array
    {
        $files = [];
        $photos = $data['photos'] ?? [];
        if (is_array($photos)) {
            foreach ($photos as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }
        $single = $data['photo'] ?? null;
        if ($single instanceof UploadedFile) {
            array_unshift($files, $single);
        }
        $files = array_slice($files, 0, 3);

        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->store('observatory/photos', 'public');
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lat: ?string, lng: ?string}
     */
    private function coordinates(array $data, Installation $installation): array
    {
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
            return ['lat' => (string) $lat, 'lng' => (string) $lng];
        }

        if ($installation->hasCoordinates()) {
            return [
                'lat' => (string) $installation->latitude,
                'lng' => (string) $installation->longitude,
            ];
        }

        return ['lat' => null, 'lng' => null];
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
