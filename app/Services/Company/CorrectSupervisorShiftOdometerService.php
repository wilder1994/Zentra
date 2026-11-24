<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorFleetVehicle;
use App\Models\SupervisorShift;
use App\Models\User;
use App\Services\Access\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Corrige el odómetro de un turno y el último kilometraje del vehículo. */
final class CorrectSupervisorShiftOdometerService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BuildSupervisorShiftSheetService $sheets,
    ) {}

    public function execute(SupervisorShift $shift, int $kmStart, ?int $kmEnd, string $reason): SupervisorShift
    {
        if ($kmStart < 0) {
            throw ValidationException::withMessages([
                'km_start' => 'El kilometraje de inicio no puede ser negativo.',
            ]);
        }

        if ($shift->isOpen()) {
            $kmEnd = null;
        } elseif ($kmEnd === null) {
            throw ValidationException::withMessages([
                'km_end' => 'El turno ya está cerrado: indica el kilometraje de cierre.',
            ]);
        } elseif ($kmEnd < $kmStart) {
            throw ValidationException::withMessages([
                'km_end' => 'El kilometraje de cierre debe ser mayor o igual al de inicio.',
            ]);
        }

        return DB::transaction(function () use ($shift, $kmStart, $kmEnd, $reason): SupervisorShift {
            $shift->load('fleetVehicle');
            $old = [
                'km_start' => $shift->km_start,
                'km_end' => $shift->km_end,
                'km_traveled' => $shift->km_traveled,
                'vehicle_last_km' => $shift->fleetVehicle?->last_km,
            ];

            $traveled = $kmEnd !== null ? $kmEnd - $kmStart : null;
            $shift->update([
                'km_start' => $kmStart,
                'km_end' => $kmEnd,
                'km_traveled' => $traveled,
            ]);

            $lastKm = $this->syncVehicleLastKm((int) $shift->supervisor_fleet_vehicle_id);

            if (! $shift->isOpen()) {
                $this->sheets->freeze($shift->fresh([
                    'user',
                    'zone',
                    'shiftTemplate',
                    'fleetVehicle',
                    'securityCompany',
                    'reviews.client',
                    'fieldLogs.client',
                    'locations',
                ]));
            }

            $this->audit->action('supervision.odometer.corrected', $shift, $old, [
                'km_start' => $kmStart,
                'km_end' => $kmEnd,
                'km_traveled' => $traveled,
                'vehicle_last_km' => $lastKm,
                'reason' => $reason,
            ]);

            return $shift->fresh();
        });
    }

    private function syncVehicleLastKm(int $vehicleId): int
    {
        if ($vehicleId < 1) {
            return 0;
        }

        $latest = SupervisorShift::query()
            ->where('supervisor_fleet_vehicle_id', $vehicleId)
            ->orderByRaw('COALESCE(ended_at, started_at) DESC')
            ->orderByDesc('id')
            ->first();

        $reading = (int) ($latest?->km_end ?? $latest?->km_start ?? 0);
        SupervisorFleetVehicle::query()->whereKey($vehicleId)->update(['last_km' => $reading]);

        return $reading;
    }
}
