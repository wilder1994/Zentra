<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisorFieldOutcome;
use App\Models\SupervisorShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Turnos de supervisor que caen en el periodo y los filtros del header. */
final class SupervisorShiftRosterQuery
{
    /**
     * @return Builder<SupervisorShift>
     */
    public function matching(int $companyId, SupervisionQueryFilter $filter): Builder
    {
        $from = $filter->from !== null && $filter->from !== ''
            ? CarbonImmutable::parse($filter->from)->startOfDay()
            : CarbonImmutable::now()->subDays(90)->startOfDay();
        $to = $filter->to !== null && $filter->to !== ''
            ? CarbonImmutable::parse($filter->to)->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('started_at', [$from, $to])
            ->when($filter->supervisorId !== null, fn (Builder $query) => $query->where('user_id', $filter->supervisorId))
            ->when($filter->zoneId !== null, fn (Builder $query) => $query->where('supervisor_zone_id', $filter->zoneId))
            ->when($filter->clientId !== null, function (Builder $query) use ($filter): void {
                $query->where(function (Builder $inner) use ($filter): void {
                    $inner->whereHas('reviews', fn (Builder $review) => $review->where('client_id', $filter->clientId))
                        ->orWhereHas('fieldLogs', fn (Builder $log) => $log->where('client_id', $filter->clientId));
                });
            })
            ->when($filter->hasNovelty === true, function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->whereHas('reviews', fn (Builder $review) => $review->where('has_novelty', true))
                        ->orWhereHas('fieldLogs', fn (Builder $log) => $log->where('outcome', '!=', SupervisorFieldOutcome::Ok->value));
                });
            })
            ->when($filter->hasNovelty === false, function (Builder $query): void {
                $query->whereDoesntHave('reviews', fn (Builder $review) => $review->where('has_novelty', true))
                    ->whereDoesntHave('fieldLogs', fn (Builder $log) => $log->where('outcome', '!=', SupervisorFieldOutcome::Ok->value));
            });
    }

    /**
     * Recorrido del turno o salto contra el cierre anterior de la misma placa.
     *
     * @param  Builder<SupervisorShift>  $query
     * @return Builder<SupervisorShift>
     */
    public function whereDifferenceAtLeast(Builder $query, int $minDiff): Builder
    {
        $previousKm = '(SELECT p.km_end FROM supervisor_shifts p WHERE p.supervisor_fleet_vehicle_id = supervisor_shifts.supervisor_fleet_vehicle_id AND p.id <> supervisor_shifts.id AND p.km_end IS NOT NULL AND p.ended_at IS NOT NULL AND p.ended_at <= supervisor_shifts.started_at ORDER BY p.ended_at DESC LIMIT 1)';

        return $query->where(function (Builder $inner) use ($minDiff, $previousKm): void {
            $inner->whereRaw('(km_end IS NOT NULL AND km_start IS NOT NULL AND (km_end - km_start) >= ?)', [$minDiff])
                ->orWhere(function (Builder $open) use ($minDiff): void {
                    $open->whereNull('km_end')->where('km_traveled', '>=', $minDiff);
                })
                ->orWhereRaw('km_start >= ? + '.$previousKm, [$minDiff]);
        });
    }
}
