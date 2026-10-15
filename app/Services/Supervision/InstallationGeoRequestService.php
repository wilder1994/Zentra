<?php

declare(strict_types=1);

namespace App\Services\Supervision;

use App\Enums\InstallationGeoRequestStatus;
use App\Models\Installation;
use App\Models\InstallationGeoRequest;
use App\Models\User;
use App\Services\Access\AuditLogger;
use App\Services\Access\GeoService;
use App\Services\Company\LookupSupervisorVisitService;
use App\Services\Company\ManageSupervisorShiftService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InstallationGeoRequestService
{
    public const PERMISSION = 'company.geo.approve';

    public const MAX_ACCURACY_METERS = 30;

    public function __construct(
        private readonly LookupSupervisorVisitService $lookup,
        private readonly ManageSupervisorShiftService $shifts,
        private readonly GeoService $geo,
    ) {}

    public function canApprove(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'company-admin']) || $user->can(self::PERMISSION);
    }

    /**
     * @return list<array{id: int, name: string, latitude: ?float, longitude: ?float, pending: bool}>
     */
    public function installationsFor(User $supervisor, int $clientId): array
    {
        $client = $this->lookup->companySupervisionClient($supervisor, $clientId);
        if ($client === null) {
            return [];
        }

        $pending = InstallationGeoRequest::query()
            ->pending()
            ->where('client_id', $client->id)
            ->pluck('installation_id')
            ->all();

        return Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude'])
            ->map(fn (Installation $installation) => [
                'id' => (int) $installation->id,
                'name' => (string) $installation->name,
                'latitude' => $installation->latitude !== null ? (float) $installation->latitude : null,
                'longitude' => $installation->longitude !== null ? (float) $installation->longitude : null,
                'pending' => in_array($installation->id, $pending, false),
            ])
            ->values()
            ->all();
    }

    public function submit(User $supervisor, int $clientId, int $installationId, float $lat, float $lng, int $accuracy): InstallationGeoRequest
    {
        $shift = $this->shifts->currentFor($supervisor);
        if ($shift === null) {
            throw ValidationException::withMessages(['shift' => 'Abre tu turno para enviar la georreferenciación.']);
        }

        if ($accuracy > self::MAX_ACCURACY_METERS) {
            throw ValidationException::withMessages([
                'accuracy' => 'El GPS tiene una precisión de ±'.$accuracy.' m. Espera a que baje de ±'.self::MAX_ACCURACY_METERS.' m e intenta de nuevo.',
            ]);
        }

        $client = $this->lookup->companySupervisionClient($supervisor, $clientId);
        $installation = $client === null ? null : Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->find($installationId);
        if ($installation === null) {
            throw ValidationException::withMessages(['installation_id' => 'La instalación no está disponible para tu empresa.']);
        }

        $distance = $installation->hasCoordinates()
            ? $this->geo->distanceMeters($lat, $lng, (float) $installation->latitude, (float) $installation->longitude)
            : null;

        return DB::transaction(function () use ($supervisor, $client, $installation, $shift, $lat, $lng, $accuracy, $distance) {
            InstallationGeoRequest::query()
                ->pending()
                ->where('installation_id', $installation->id)
                ->lockForUpdate()
                ->update(['status' => InstallationGeoRequestStatus::Superseded->value, 'updated_at' => now()]);

            return InstallationGeoRequest::query()->create([
                'security_company_id' => (int) $client->security_company_id,
                'client_id' => (int) $client->id,
                'installation_id' => (int) $installation->id,
                'requested_by' => (int) $supervisor->id,
                'supervisor_shift_id' => (int) $shift->id,
                'previous_latitude' => $installation->latitude,
                'previous_longitude' => $installation->longitude,
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy_m' => $accuracy,
                'distance_m' => $distance !== null ? (int) round($distance) : null,
                'status' => InstallationGeoRequestStatus::Pending->value,
            ]);
        });
    }

    public function approve(InstallationGeoRequest $request, User $reviewer): void
    {
        DB::transaction(function () use ($request, $reviewer): void {
            $row = $this->lockPending($request);
            $installation = Installation::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($row->installation_id);
            $old = ['latitude' => $installation->latitude, 'longitude' => $installation->longitude];

            $installation->forceFill(['latitude' => $row->latitude, 'longitude' => $row->longitude])->save();
            $row->forceFill([
                'status' => InstallationGeoRequestStatus::Approved->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            app(AuditLogger::class)->record($installation, 'installation.geo.approve', $old, [
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'geo_request_id' => $row->id,
                'requested_by' => $row->requested_by,
            ]);
        });
    }

    public function reject(InstallationGeoRequest $request, User $reviewer, ?string $note): void
    {
        DB::transaction(function () use ($request, $reviewer, $note): void {
            $row = $this->lockPending($request);
            $row->forceFill([
                'status' => InstallationGeoRequestStatus::Rejected->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ])->save();
        });
    }

    private function lockPending(InstallationGeoRequest $request): InstallationGeoRequest
    {
        $row = InstallationGeoRequest::query()->lockForUpdate()->findOrFail($request->id);
        if ($row->status !== InstallationGeoRequestStatus::Pending) {
            throw new ConflictHttpException('Esta solicitud ya fue '.mb_strtolower($row->status->label()).'.');
        }

        return $row;
    }
}
