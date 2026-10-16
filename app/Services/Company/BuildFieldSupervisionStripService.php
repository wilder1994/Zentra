<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorShiftStatus;
use App\Models\SecurityCompany;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorRecommendation;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Support\Supervision\SupervisionOperationalDay;

final class BuildFieldSupervisionStripService
{
    public function __construct(
        private readonly BuildSupervisorTrailService $trail,
    ) {}

    /** @return array<string, mixed>|null */
    public function forToday(SecurityCompany $company): ?array
    {
        if (! $company->hasSupervisionPackage()) {
            return null;
        }

        $companyId = (int) $company->id;
        $date = SupervisionOperationalDay::dateFor();
        [$from, $to] = SupervisionOperationalDay::window($date);
        $to = $to->subSecond();

        $rows = app(BuildPostComplianceService::class)->day($companyId, $date);
        $expectedRows = array_filter($rows, fn (array $row) => $row['expected']);
        $contracted = count($expectedRows);
        $visited = count(array_filter($expectedRows, fn (array $row) => $row['done']));

        $openShifts = SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->where('status', SupervisorShiftStatus::Open)
            ->with(['locations' => fn ($q) => $q->orderBy('recorded_at')])
            ->get();

        $reviews = SupervisorShiftReview::query()
            ->whereHas('shift', fn ($q) => $q->where('security_company_id', $companyId))
            ->whereBetween('recorded_at', [$from, $to])
            ->count();

        $closedKm = (int) SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->where('status', SupervisorShiftStatus::Closed)
            ->whereBetween('started_at', [$from, $to])
            ->sum('km_traveled');

        $liveKm = 0.0;
        foreach ($openShifts as $shift) {
            $liveKm += (float) $this->trail->execute($shift->locations, true)['km'];
        }

        $kmToday = (int) round($closedKm + $liveKm);

        $recsToday = SupervisorRecommendation::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $attention = SupervisorFieldLog::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('recorded_at', [$from, $to])
            ->where('outcome', '!=', SupervisorFieldOutcome::Ok)
            ->count();

        $coverage = $contracted > 0 ? (int) round(($visited / $contracted) * 100) : null;

        return [
            'open_shifts' => $openShifts->count(),
            'reviews_today' => $reviews,
            'sites_contracted' => $contracted,
            'sites_visited' => $visited,
            'coverage_pct' => $coverage,
            'recommendations_today' => $recsToday,
            'attention_today' => $attention,
            'km_today' => $kmToday,
            'posts_count' => count($rows),
        ];
    }
}
