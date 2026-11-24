<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;
use Illuminate\Support\Collection;

/** Turnos cuyo odómetro saltó o cuyo recorrido no cabe en un turno. */
final class ListInflatedSupervisorOdometersService
{
    /**
     * @return Collection<int, SupervisorShift>
     */
    public function execute(int $companyId): Collection
    {
        $max = ResolveSupervisorShiftTraveledKm::MAX_SHIFT_KM;

        return SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->where('started_at', '>=', now()->subDays(90))
            ->whereNotNull('supervisor_fleet_vehicle_id')
            ->whereNotNull('km_start')
            ->where(function ($query) use ($max): void {
                $query->where('km_traveled', '>', $max)
                    ->orWhereRaw('(km_end IS NOT NULL AND km_start IS NOT NULL AND (km_end - km_start) > ?)', [$max])
                    ->orWhereRaw(
                        'km_start > ? + COALESCE((SELECT p.km_end FROM supervisor_shifts p WHERE p.supervisor_fleet_vehicle_id = supervisor_shifts.supervisor_fleet_vehicle_id AND p.id <> supervisor_shifts.id AND p.km_end IS NOT NULL AND p.ended_at IS NOT NULL AND p.ended_at <= supervisor_shifts.started_at ORDER BY p.ended_at DESC LIMIT 1), km_start)',
                        [$max],
                    );
            })
            ->with(['user:id,name', 'fleetVehicle:id,plate'])
            ->orderByDesc('started_at')
            ->limit(40)
            ->get();
    }
}
