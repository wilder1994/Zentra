<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Domain\Supervision\Data\SupervisorFieldSheetListItem;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorFieldSheetKind;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShiftReview;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

final class ListSupervisorFieldSheetsService
{
    /**
     * @return LengthAwarePaginator<int, SupervisorFieldSheetListItem>
     */
    public function execute(
        int $companyId,
        SupervisionQueryFilter $filter,
        int $page = 1,
        int $perPage = 30,
        ?int $onlyUserId = null,
    ): LengthAwarePaginator {
        $fromAt = $filter->from !== null && $filter->from !== ''
            ? CarbonImmutable::parse($filter->from)->startOfDay()
            : null;
        $toAt = $filter->to !== null && $filter->to !== ''
            ? CarbonImmutable::parse($filter->to)->endOfDay()
            : null;

        $kind = SupervisorFieldSheetKind::tryFrom((string) ($filter->sheetKind ?? ''));
        $items = collect();

        if ($kind === null || $kind === SupervisorFieldSheetKind::Review) {
            $items = $items->concat($this->reviews($companyId, $filter, $fromAt, $toAt, $onlyUserId));
        }

        $logModules = $this->logModulesFor($kind);
        if ($logModules !== []) {
            $items = $items->concat($this->logs($companyId, $filter, $fromAt, $toAt, $logModules, $onlyUserId));
        }

        /** @var Collection<int, SupervisorFieldSheetListItem> $sorted */
        $sorted = $items
            ->sortByDesc(fn (SupervisorFieldSheetListItem $item) => $item->recordedAt->getTimestamp())
            ->values();

        $page = max(1, $page);
        $slice = $sorted->forPage($page, $perPage)->values();

        return new Paginator(
            $slice,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    /**
     * @return list<SupervisorFieldSheetListItem>
     */
    private function reviews(
        int $companyId,
        SupervisionQueryFilter $filter,
        ?CarbonImmutable $fromAt,
        ?CarbonImmutable $toAt,
        ?int $onlyUserId,
    ): array {
        return SupervisorShiftReview::query()
            ->whereHas('shift', function ($query) use ($companyId, $filter, $onlyUserId): void {
                $query->where('security_company_id', $companyId)->matchingSupervisor($filter);
                if ($onlyUserId !== null) {
                    $query->where('user_id', $onlyUserId);
                }
            })
            ->when($filter->zoneId !== null, fn ($query) => $query->where('supervisor_zone_id', $filter->zoneId))
            ->when($fromAt !== null, fn ($query) => $query->where('recorded_at', '>=', $fromAt))
            ->when($toAt !== null, fn ($query) => $query->where('recorded_at', '<=', $toAt))
            ->when($filter->clientId !== null, fn ($query) => $query->where('client_id', $filter->clientId))
            ->when($filter->hasNovelty === true, fn ($query) => $query->where('has_novelty', true))
            ->when($filter->hasNovelty === false, fn ($query) => $query->where('has_novelty', false))
            ->with(['client:id,name', 'shift.user:id,name'])
            ->orderByDesc('recorded_at')
            ->limit(400)
            ->get()
            ->map(function (SupervisorShiftReview $review) {
                $at = $review->recorded_at ?? now();
                $kind = SupervisorFieldSheetKind::Review;

                return new SupervisorFieldSheetListItem(
                    kind: $kind,
                    id: (int) $review->id,
                    folio: $kind->folio((int) $review->id, $at),
                    typeLabel: $kind->label(),
                    supervisorName: $review->shift?->user?->name ?? 'Supervisor',
                    clientName: $review->client?->name,
                    hasNovelty: (bool) $review->has_novelty,
                    recordedAt: $at,
                );
            })
            ->all();
    }

    /**
     * @param  list<SupervisorFieldModule>  $modules
     * @return list<SupervisorFieldSheetListItem>
     */
    private function logs(
        int $companyId,
        SupervisionQueryFilter $filter,
        ?CarbonImmutable $fromAt,
        ?CarbonImmutable $toAt,
        array $modules,
        ?int $onlyUserId,
    ): array {
        return SupervisorFieldLog::query()
            ->where('security_company_id', $companyId)
            ->whereIn('module', $modules)
            ->when($onlyUserId !== null, fn ($query) => $query->where('user_id', $onlyUserId))
            ->whereHas('shift', fn ($query) => $query->matchingSupervisor($filter))
            ->when($filter->zoneId !== null, fn ($query) => $query->where('supervisor_zone_id', $filter->zoneId))
            ->when($fromAt !== null, fn ($query) => $query->where('recorded_at', '>=', $fromAt))
            ->when($toAt !== null, fn ($query) => $query->where('recorded_at', '<=', $toAt))
            ->when($filter->clientId !== null, fn ($query) => $query->where('client_id', $filter->clientId))
            ->when($filter->hasNovelty === true, fn ($query) => $query->where('outcome', '!=', SupervisorFieldOutcome::Ok))
            ->when($filter->hasNovelty === false, fn ($query) => $query->where('outcome', SupervisorFieldOutcome::Ok))
            ->with(['client:id,name', 'user:id,name'])
            ->orderByDesc('recorded_at')
            ->limit(400)
            ->get()
            ->map(function (SupervisorFieldLog $log) {
                $kind = SupervisorFieldSheetKind::fromStandaloneModule($log->module);
                if ($kind === null) {
                    return null;
                }
                $at = $log->recorded_at ?? now();

                return new SupervisorFieldSheetListItem(
                    kind: $kind,
                    id: (int) $log->id,
                    folio: $kind->folio((int) $log->id, $at),
                    typeLabel: $kind->label(),
                    supervisorName: $log->user?->name ?? 'Supervisor',
                    clientName: $log->client?->name,
                    hasNovelty: $log->outcome !== SupervisorFieldOutcome::Ok,
                    recordedAt: $at,
                );
            })
            ->filter()
            ->all();
    }

    /**
     * @return list<SupervisorFieldModule>
     */
    private function logModulesFor(?SupervisorFieldSheetKind $kind): array
    {
        return match ($kind) {
            SupervisorFieldSheetKind::Review, SupervisorFieldSheetKind::Shift => [],
            SupervisorFieldSheetKind::Alarm => [SupervisorFieldModule::Alarms],
            SupervisorFieldSheetKind::Support => [SupervisorFieldModule::Supports],
            SupervisorFieldSheetKind::Document => [SupervisorFieldModule::Documents],
            null => [
                SupervisorFieldModule::Alarms,
                SupervisorFieldModule::Supports,
                SupervisorFieldModule::Documents,
            ],
        };
    }
}
