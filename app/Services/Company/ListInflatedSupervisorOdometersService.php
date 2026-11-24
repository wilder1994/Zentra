<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisorFieldOutcome;
use App\Models\SupervisorShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Turnos cuyo odómetro saltó o cuyo recorrido no cabe en un turno. */
final class ListInflatedSupervisorOdometersService
{
    /**
     * @return Collection<int, SupervisorOdometerComparison>
     */
    public function execute(int $companyId, SupervisionQueryFilter $filter): Collection
    {
        if ($filter->sheetKind !== null) {
            return collect();
        }

        $from = $filter->from !== null && $filter->from !== ''
            ? CarbonImmutable::parse($filter->from)->startOfDay()
            : CarbonImmutable::now()->subDays(90)->startOfDay();
        $to = $filter->to !== null && $filter->to !== ''
            ? CarbonImmutable::parse($filter->to)->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $max = ResolveSupervisorShiftTraveledKm::MAX_SHIFT_KM;

        return SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->whereNull('odometer_accepted_at')
            ->whereBetween('started_at', [$from, $to])
            ->whereNotNull('supervisor_fleet_vehicle_id')
            ->whereNotNull('km_start')
            ->when($filter->supervisorId !== null, fn ($query) => $query->where('user_id', $filter->supervisorId))
            ->when($filter->zoneId !== null, fn ($query) => $query->where('supervisor_zone_id', $filter->zoneId))
            ->when($filter->clientId !== null, function ($query) use ($filter): void {
                $query->where(function ($inner) use ($filter): void {
                    $inner->whereHas('reviews', fn ($review) => $review->where('client_id', $filter->clientId))
                        ->orWhereHas('fieldLogs', fn ($log) => $log->where('client_id', $filter->clientId));
                });
            })
            ->when($filter->hasNovelty === true, function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereHas('reviews', fn ($review) => $review->where('has_novelty', true))
                        ->orWhereHas('fieldLogs', fn ($log) => $log->where('outcome', '!=', SupervisorFieldOutcome::Ok->value));
                });
            })
            ->when($filter->hasNovelty === false, function ($query): void {
                $query->whereDoesntHave('reviews', fn ($review) => $review->where('has_novelty', true))
                    ->whereDoesntHave('fieldLogs', fn ($log) => $log->where('outcome', '!=', SupervisorFieldOutcome::Ok->value));
            })
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
            ->get()
            ->map(fn (SupervisorShift $shift): SupervisorOdometerComparison => $this->compare($shift))
            ->filter(fn (SupervisorOdometerComparison $row): bool => $row->flagged())
            ->values();
    }

    public function compare(SupervisorShift $shift): SupervisorOdometerComparison
    {
        $max = ResolveSupervisorShiftTraveledKm::MAX_SHIFT_KM;
        $trip = $shift->km_end !== null && $shift->km_start !== null
            ? (int) $shift->km_end - (int) $shift->km_start
            : ($shift->km_traveled !== null ? (int) $shift->km_traveled : null);

        $previous = $shift->supervisor_fleet_vehicle_id === null
            ? null
            : SupervisorShift::query()
                ->where('supervisor_fleet_vehicle_id', $shift->supervisor_fleet_vehicle_id)
                ->whereKeyNot($shift->id)
                ->whereNotNull('km_end')
                ->whereNotNull('ended_at')
                ->where('ended_at', '<=', $shift->started_at)
                ->orderByDesc('ended_at')
                ->first(['km_end', 'ended_at']);

        $gap = $previous !== null ? (int) $shift->km_start - (int) $previous->km_end : null;

        return new SupervisorOdometerComparison(
            shift: $shift,
            tripKm: $trip,
            tripOverLimit: $trip !== null && $trip > $max,
            previousKm: $previous !== null ? (int) $previous->km_end : null,
            previousEndedAt: $previous?->ended_at,
            gapKm: $gap,
            gapOverLimit: $gap !== null && $gap > $max,
        );
    }
}
