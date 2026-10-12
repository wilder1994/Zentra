<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\SupervisionPeriodSnapshot;
use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorRiskLevel;
use App\Enums\SupervisorShiftStatus;
use App\Models\SecurityCompany;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorRecommendation;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorZone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BuildSupervisionSummaryService
{
    public function __construct(
        private readonly BuildSupervisionPeriodCharts $charts,
        private readonly BuildPostComplianceService $compliance,
    ) {}

    public function execute(SecurityCompany $company, SupervisionQueryFilter $filter): SupervisionPeriodSnapshot
    {
        $fromAt = $filter->from !== null && $filter->from !== ''
            ? CarbonImmutable::parse($filter->from)->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
        $toAt = $filter->to !== null && $filter->to !== ''
            ? CarbonImmutable::parse($filter->to)->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $companyId = (int) $company->id;

        $reviews = SupervisorShiftReview::query()
            ->whereHas('shift', fn ($q) => $q->where('security_company_id', $companyId)->matchingFilter($filter))
            ->whereBetween('recorded_at', [$fromAt, $toAt])
            ->with(['client:id,name', 'shift.user:id,name'])
            ->get();

        $logs = SupervisorFieldLog::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('recorded_at', [$fromAt, $toAt])
            ->when($filter->supervisorId !== null, fn ($q) => $q->where('user_id', $filter->supervisorId))
            ->when($filter->zoneId !== null, fn ($q) => $q->whereHas('shift', fn ($s) => $s->where('supervisor_zone_id', $filter->zoneId)))
            ->with(['client:id,name', 'user:id,name'])
            ->get();

        $shifts = SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('started_at', [$fromAt, $toAt])
            ->matchingFilter($filter)
            ->with('user:id,name')
            ->get();

        $compliance = $this->compliance->range($companyId, $fromAt, $toAt, $filter);
        $unvisited = collect($compliance['posts'])
            ->filter(fn (array $row) => $row['done'] < $row['expected'])
            ->sortByDesc(fn (array $row) => $row['expected'] - $row['done'])
            ->map(function (array $row): string {
                $post = $row['post'];
                $missing = $row['expected'] - $row['done'];
                $where = trim(($post->client?->name ?? '').' · '.($post->installation?->name ?? ''), ' ·');

                return $where.' · '.$post->name.($row['expected'] > 1 ? ' ('.$missing.' de '.$row['expected'].' días)' : '');
            })
            ->values()
            ->all();

        $sitesContracted = $compliance['expected'];
        $sitesVisited = $compliance['done'];
        $coverage = $compliance['percent'];

        $semaphore = $this->semaphore($coverage, $sitesContracted);

        $recRows = $this->recommendationRows($companyId, $fromAt, $toAt, $filter);
        $recommendations = [
            'total' => $recRows->count(),
            'low' => $recRows->where('risk_level', SupervisorRiskLevel::Low)->count(),
            'medium' => $recRows->where('risk_level', SupervisorRiskLevel::Medium)->count(),
            'high' => $recRows->where('risk_level', SupervisorRiskLevel::High)->count(),
            'extreme' => $recRows->where('risk_level', SupervisorRiskLevel::Extreme)->count(),
        ];
        $modules = $this->moduleRows($reviews->count(), $logs);
        $alerts = $this->alerts($unvisited, $logs, $recommendations, $shifts);
        $charts = $this->charts->execute($fromAt, $toAt, $reviews, $logs, $shifts, $recRows);

        $caption = 'Del '.$fromAt->format('d/m/Y').' al '.$toAt->format('d/m/Y');
        if ($filter->zoneId !== null) {
            $zoneName = SupervisorZone::query()->whereKey($filter->zoneId)->value('name');
            if (is_string($zoneName) && $zoneName !== '') {
                $caption .= ' · '.$zoneName;
            }
        }
        if ($filter->supervisorId !== null) {
            $supervisorName = User::query()->whereKey($filter->supervisorId)->value('name');
            if (is_string($supervisorName) && $supervisorName !== '') {
                $caption .= ' · '.$supervisorName;
            }
        }

        return new SupervisionPeriodSnapshot(
            companyName: $company->displayName(),
            from: $fromAt->toDateString(),
            to: $toAt->toDateString(),
            caption: $caption,
            reviews: $reviews->count(),
            sitesContracted: $sitesContracted,
            sitesVisited: $sitesVisited,
            kmTraveled: (int) $shifts->sum(fn (SupervisorShift $shift) => (int) ($shift->km_traveled ?? 0)),
            openShifts: $shifts->where('status', SupervisorShiftStatus::Open)->count(),
            fieldLogs: $logs->count(),
            semaphore: $semaphore,
            coveragePercent: $coverage,
            bySupervisor: $this->bySupervisor($reviews, $logs, $shifts),
            byClient: $this->byClient($reviews, $logs),
            modules: $modules,
            recommendations: $recommendations,
            charts: $charts,
            unvisitedSites: $unvisited,
            alerts: $alerts,
        );
    }

    private function semaphore(?float $coverage, int $sitesContracted): string
    {
        if ($sitesContracted === 0) {
            return 'neutral';
        }

        return match (true) {
            $coverage >= 90 => 'green',
            $coverage >= 70 => 'yellow',
            default => 'red',
        };
    }

    /**
     * @param  Collection<int, SupervisorFieldLog>  $logs
     * @return array<string, array{label: string, total: int, ok: int, attention: int, critical: int}>
     */
    private function moduleRows(int $reviews, Collection $logs): array
    {
        $rows = [
            'reviews' => [
                'label' => 'Revista',
                'total' => $reviews,
                'ok' => $reviews,
                'attention' => 0,
                'critical' => 0,
            ],
        ];

        foreach (SupervisorFieldModule::cases() as $module) {
            $ofModule = $logs->filter(fn (SupervisorFieldLog $log) => $log->module === $module);
            $rows[$module->value] = [
                'label' => $module->label(),
                'total' => $ofModule->count(),
                'ok' => $ofModule->where('outcome', SupervisorFieldOutcome::Ok)->count(),
                'attention' => $ofModule->where('outcome', SupervisorFieldOutcome::Attention)->count(),
                'critical' => $ofModule->where('outcome', SupervisorFieldOutcome::Critical)->count(),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, SupervisorRecommendation>
     */
    private function recommendationRows(int $companyId, CarbonImmutable $fromAt, CarbonImmutable $toAt, SupervisionQueryFilter $filter): Collection
    {
        return SupervisorRecommendation::query()
            ->where('security_company_id', $companyId)
            ->when($filter->supervisorId !== null, fn ($q) => $q->where('opened_by_user_id', $filter->supervisorId))
            ->when($filter->zoneId !== null, fn ($q) => $q->whereHas('openedShift', fn ($s) => $s->where('supervisor_zone_id', $filter->zoneId)))
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->get();
    }

    /**
     * @param  Collection<int, SupervisorShiftReview>  $reviews
     * @param  Collection<int, SupervisorFieldLog>  $logs
     * @param  Collection<int, SupervisorShift>  $shifts
     * @return list<array{name: string, reviews: int, km: int, logs: int}>
     */
    private function bySupervisor(Collection $reviews, Collection $logs, Collection $shifts): array
    {
        $names = [];
        foreach ($shifts as $shift) {
            $names[(int) $shift->user_id] = $shift->user?->name ?? 'Supervisor';
        }
        foreach ($reviews as $review) {
            $userId = (int) $review->shift?->user_id;
            $names[$userId] = $review->shift?->user?->name ?? ($names[$userId] ?? 'Supervisor');
        }
        foreach ($logs as $log) {
            $names[(int) $log->user_id] = $log->user?->name ?? ($names[(int) $log->user_id] ?? 'Supervisor');
        }

        $rows = [];
        foreach ($names as $userId => $name) {
            if ($userId < 1) {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'reviews' => $reviews->filter(fn (SupervisorShiftReview $review) => (int) $review->shift?->user_id === $userId)->count(),
                'km' => (int) $shifts->where('user_id', $userId)->sum(fn (SupervisorShift $shift) => (int) ($shift->km_traveled ?? 0)),
                'logs' => $logs->where('user_id', $userId)->count(),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['reviews'] <=> $a['reviews']);

        return $rows;
    }

    /**
     * @param  Collection<int, SupervisorShiftReview>  $reviews
     * @param  Collection<int, SupervisorFieldLog>  $logs
     * @return list<array{name: string, reviews: int, novelty: int}>
     */
    private function byClient(Collection $reviews, Collection $logs): array
    {
        $names = [];
        foreach ($reviews as $review) {
            $names[(int) $review->client_id] = $review->client?->name ?? 'Sitio';
        }
        foreach ($logs as $log) {
            if ($log->client_id === null) {
                continue;
            }
            $names[(int) $log->client_id] = $log->client?->name ?? ($names[(int) $log->client_id] ?? 'Sitio');
        }

        $rows = [];
        foreach ($names as $clientId => $name) {
            $ofSite = $reviews->where('client_id', $clientId);
            $rows[] = [
                'name' => $name,
                'reviews' => $ofSite->count(),
                'novelty' => $ofSite->where('has_novelty', true)->count(),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['reviews'] <=> $a['reviews']);

        return $rows;
    }

    /**
     * @param  list<string>  $unvisited
     * @param  Collection<int, SupervisorFieldLog>  $logs
     * @param  array{total: int, low: int, medium: int, high: int, extreme: int}  $recommendations
     * @param  Collection<int, SupervisorShift>  $shifts
     * @return list<string>
     */
    private function alerts(array $unvisited, Collection $logs, array $recommendations, Collection $shifts): array
    {
        $alerts = [];

        if ($unvisited !== []) {
            $alerts[] = count($unvisited).' puesto(s) con revistas pendientes en el periodo: '.implode(', ', array_slice($unvisited, 0, 8));
        }

        $failedAlarms = $logs
            ->filter(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Alarms
                && in_array((string) ($log->payload['result'] ?? ''), ['fail', 'real'], true))
            ->count();
        if ($failedAlarms > 0) {
            $alerts[] = $failedAlarms.' alarma(s) en falla o reales.';
        }

        $pendingDocs = $logs
            ->filter(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Documents
                && collect($log->payload['items'] ?? [])->contains(
                    fn (mixed $item) => is_array($item) && (int) ($item['pending'] ?? 0) > 0,
                ))
            ->count();
        if ($pendingDocs > 0) {
            $alerts[] = $pendingDocs.' registro(s) de documentos del turno con cantidades pendientes.';
        }

        if ($recommendations['extreme'] > 0) {
            $alerts[] = $recommendations['extreme'].' recomendación(es) de riesgo extremo.';
        }

        if ($recommendations['high'] > 0) {
            $alerts[] = $recommendations['high'].' recomendación(es) de riesgo alto.';
        }

        $longOpen = $shifts
            ->filter(fn (SupervisorShift $shift) => $shift->status === SupervisorShiftStatus::Open
                && $shift->started_at !== null
                && $shift->started_at->lt(now()->subHours(12)))
            ->count();
        if ($longOpen > 0) {
            $alerts[] = $longOpen.' turno(s) abierto(s) hace más de 12 h.';
        }

        if ($alerts === []) {
            $alerts[] = 'Sin alertas operativas en el periodo.';
        }

        return $alerts;
    }
}
