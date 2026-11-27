<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorFieldSheetKind;
use App\Models\SupervisorShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Cartas de turno del supervisor, aparte de las fichas de los guardas. */
final class ListSupervisorShiftSheetsService
{
    public function __construct(
        private readonly SupervisorShiftRosterQuery $roster,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SupervisorShift>
     */
    public function execute(int $companyId, SupervisionQueryFilter $filter, int $page = 1): LengthAwarePaginator
    {
        return $this->roster->matching($companyId, $filter)
            ->with(['user:id,name', 'fleetVehicle:id,plate'])
            ->withExists([
                'reviews as has_novelty_review' => fn ($query) => $query->where('has_novelty', true),
                'fieldLogs as has_novelty_log' => fn ($query) => $query->where('outcome', '!=', SupervisorFieldOutcome::Ok->value),
            ])
            ->orderByDesc('started_at')
            ->paginate(perPage: 30, page: max(1, $page))
            ->withQueryString();
    }

    public function folio(SupervisorShift $shift): string
    {
        $at = $shift->started_at ?? now();

        return SupervisorFieldSheetKind::Shift->folio((int) $shift->id, $at);
    }
}
