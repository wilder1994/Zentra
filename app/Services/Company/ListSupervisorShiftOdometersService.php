<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Models\SupervisorShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/** Kilometraje de cada turno del filtro, haya o no salto marcado por el sistema. */
final class ListSupervisorShiftOdometersService
{
    public function __construct(
        private readonly SupervisorShiftRosterQuery $roster,
        private readonly ListInflatedSupervisorOdometersService $comparisons,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SupervisorOdometerComparison>
     */
    public function execute(
        int $companyId,
        SupervisionQueryFilter $filter,
        int $page = 1,
        ?int $minDiff = null,
    ): LengthAwarePaginator {
        $query = $this->roster->matching($companyId, $filter)
            ->whereNotNull('supervisor_fleet_vehicle_id')
            ->whereNotNull('km_start');

        if ($minDiff !== null && $minDiff > 0) {
            $this->roster->whereDifferenceAtLeast($query, $minDiff);
        }

        /** @var Paginator<int, SupervisorShift> $pageResult */
        $pageResult = $query
            ->with(['user:id,name', 'fleetVehicle:id,plate'])
            ->orderByDesc('started_at')
            ->paginate(perPage: 30, page: max(1, $page))
            ->withQueryString();

        $pageResult->setCollection(
            $pageResult->getCollection()->map(
                fn (SupervisorShift $shift): SupervisorOdometerComparison => $this->comparisons->compare($shift),
            ),
        );

        return $pageResult;
    }
}
