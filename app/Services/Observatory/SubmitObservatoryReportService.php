<?php

declare(strict_types=1);

namespace App\Services\Observatory;

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

        $communityOnly = $source?->usesCommunitySites() ?? false;

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereKey((int) $data['installation_id'])
            ->when($communityOnly, fn ($q) => $q->observatoryCommunity())
            ->first();

        if ($installation === null) {
            throw ValidationException::withMessages([
                'installation_id' => $communityOnly
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
        $isAdult = array_key_exists('is_adult', $data) ? $data['is_adult'] : null;
        $isAdult = $isAdult === null ? null : (bool) $isAdult;
        if ($isAdult === false) {
            $anonymous = false;
        }
        $coords = $this->coordinates($data, $installation);
        $reporter = $data['reported_by'] ?? null;
        $reporter = $reporter instanceof User ? $reporter : null;
        $personKey = $this->personKey($data, $ip);

        $report = DB::transaction(function () use ($client, $installation, $type, $body, $anonymous, $isAdult, $data, $ip, $coords, $source, $role, $reporter, $personKey): ObservatoryReport {
            if ($this->duplicate($installation, $type, $personKey)) {
                throw ValidationException::withMessages([
                    'report' => 'Ya enviamos un reporte tuyo de este tipo en este colegio. Si es otro hecho, inténtalo más tarde.',
                ]);
            }

            $photoPaths = $this->storePhotos($data);
            $photoPath = $photoPaths[0] ?? null;
            $videoPath = $this->storeVideo($data);
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
                'is_adult' => $isAdult,
                'reporter_name' => $anonymous ? null : $this->nullable($data['reporter_name'] ?? null),
                'reporter_phone' => $anonymous ? null : $this->nullable($data['reporter_phone'] ?? null),
                'reported_by_user_id' => $anonymous ? null : $reporter?->id,
                'photo_path' => $photoPath,
                'photo_paths' => $photoPaths !== [] ? $photoPaths : null,
                'video_path' => $videoPath,
                'reporter_key' => $personKey,
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

        return $this->storeFiles($files, 'observatory/photos');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeVideo(array $data): ?string
    {
        $file = $data['video'] ?? null;
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $stored = $this->storeFiles([$file], 'observatory/videos');

        return $stored[0] ?? null;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<string>
     */
    private function storeFiles(array $files, string $directory): array
    {
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->store($directory, 'public');
        }

        return $paths;
    }

    /**
     * Misma sede, mismo tipo y misma persona dentro de la ventana de unión.
     * La persona es el teléfono, el usuario, el nombre o la IP; no se muestra.
     */
    private function duplicate(Installation $installation, ObservatoryReportType $type, ?string $personKey): bool
    {
        if ($personKey === null) {
            return false;
        }

        $minutes = max(1, (int) config('observatory.merge_window_minutes', 60));

        return ObservatoryReport::query()
            ->where('installation_id', $installation->id)
            ->where('reporter_key', $personKey)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->where(function ($q) use ($type): void {
                $q->where('observatory_report_type_id', $type->id)
                    ->orWhere('kind', $type->slug);
            })
            ->lockForUpdate()
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function personKey(array $data, ?string $ip): ?string
    {
        $phone = trim((string) ($data['reporter_phone'] ?? ''));
        $name = mb_strtolower(trim((string) ($data['reporter_name'] ?? '')));
        $user = $data['reported_by'] ?? null;
        $userId = $user instanceof User ? (string) $user->id : '';
        $raw = match (true) {
            $phone !== '' => 'tel:'.$phone,
            $userId !== '' => 'user:'.$userId,
            $name !== '' => 'name:'.$name,
            $ip !== null && $ip !== '' => 'ip:'.$ip,
            default => '',
        };

        return $raw === '' ? null : hash('sha256', $raw);
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
