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
use App\Models\SupervisorPost;
use App\Models\SupervisorRecommendation;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorTraining;
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
            ->whereHas('shift', fn ($q) => $q->where('security_company_id', $companyId)->matchingSupervisor($filter))
            ->when($filter->zoneId !== null, fn ($q) => $q->where('supervisor_zone_id', $filter->zoneId))
            ->whereBetween('recorded_at', [$fromAt, $toAt])
            ->with(['client:id,name', 'shift.user:id,name', 'employee:id,first_names,last_name_paternal,last_name_maternal'])
            ->get();

        $trainings = SupervisorTraining::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('recorded_at', [$fromAt, $toAt])
            ->when($filter->zoneId !== null, fn ($q) => $q->where('supervisor_zone_id', $filter->zoneId))
            ->when($filter->supervisorId !== null, fn ($q) => $q->where('user_id', $filter->supervisorId))
            ->with([
                'topic:id,name',
                'employee:id,first_names,last_name_paternal,last_name_maternal',
                'user:id,name',
                'log:id,payload',
            ])
            ->get();

        $logs = SupervisorFieldLog::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('recorded_at', [$fromAt, $toAt])
            ->when($filter->supervisorId !== null, fn ($q) => $q->where('user_id', $filter->supervisorId))
            ->when($filter->zoneId !== null, fn ($q) => $q->where('supervisor_zone_id', $filter->zoneId))
            ->with(['client:id,name', 'user:id,name'])
            ->get();

        $shifts = SupervisorShift::query()
            ->where('security_company_id', $companyId)
            ->whereBetween('started_at', [$fromAt, $toAt])
            ->matchingFilter($filter)
            ->with('user:id,name')
            ->get();

        $compliance = $this->compliance->range($companyId, $fromAt, $toAt, $filter);
        $postRows = collect($compliance['posts']);

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
        $alerts = $this->alerts($logs, $recommendations, $shifts);
        $charts = $this->charts->execute($fromAt, $toAt, $reviews, $logs, $shifts, $recRows, $trainings);

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
            reviewsWithNovelty: $reviews->where('has_novelty', true)->count(),
            sitesContracted: $sitesContracted,
            sitesVisited: $sitesVisited,
            postsTotal: $postRows->count(),
            postsDone: $postRows->filter(fn (array $row) => $row['done'] >= $row['expected'])->count(),
            kmTraveled: (int) $shifts->sum(fn (SupervisorShift $shift) => (int) ($shift->km_traveled ?? 0)),
            openShifts: $shifts->where('status', SupervisorShiftStatus::Open)->count(),
            fieldLogs: $logs->count(),
            semaphore: $semaphore,
            coveragePercent: $coverage,
            bySupervisor: $this->bySupervisor($reviews, $logs, $shifts),
            byClient: $this->byClient($postRows, $reviews),
            modules: $modules,
            recommendations: $recommendations,
            charts: $charts,
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
            ->when($filter->zoneId !== null, fn ($q) => $q->where('supervisor_zone_id', $filter->zoneId))
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->get();
    }

    /**
     * @param  Collection<int, SupervisorShiftReview>  $reviews
     * @param  Collection<int, SupervisorFieldLog>  $logs
     * @param  Collection<int, SupervisorShift>  $shifts
     * @return list<array{name: string, shifts: int, reviews: int, km: int, logs: int, alarms: int, supports: int}>
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
            $ofUser = $logs->where('user_id', $userId);
            $rows[] = [
                'name' => $name,
                'shifts' => $shifts->where('user_id', $userId)->count(),
                'reviews' => $reviews->filter(fn (SupervisorShiftReview $review) => (int) $review->shift?->user_id === $userId)->count(),
                'km' => (int) $shifts->where('user_id', $userId)->sum(fn (SupervisorShift $shift) => (int) ($shift->km_traveled ?? 0)),
                'logs' => $ofUser->count(),
                'alarms' => $ofUser->filter(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Alarms)->count(),
                'supports' => $ofUser->filter(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Supports)->count(),
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['reviews'], $a['name']] <=> [$a['reviews'], $b['name']]);

        return $rows;
    }

    /**
     * Un cliente por fila con el cumplimiento de sus puestos. Rojo = ninguna revista esperada hecha,
     * ámbar = parcial, verde = todos los puestos cumplidos.
     *
     * @param  Collection<int, array{post: SupervisorPost, expected: int, done: int}>  $postRows
     * @param  Collection<int, SupervisorShiftReview>  $reviews
     * @return list<array<string, mixed>>
     */
    private function byClient(Collection $postRows, Collection $reviews): array
    {
        $rank = ['red' => 0, 'amber' => 1, 'green' => 2];

        return $postRows
            ->groupBy(fn (array $row) => (int) $row['post']->client_id)
            ->map(function (Collection $rows, int $clientId) use ($reviews): array {
                $expected = (int) $rows->sum('expected');
                $done = (int) $rows->sum('done');
                $ofClient = $reviews->where('client_id', $clientId);
                $pending = $rows
                    ->filter(fn (array $row) => $row['done'] < $row['expected'])
                    ->map(fn (array $row) => [
                        'installation' => (string) ($row['post']->installation?->name ?? ''),
                        'post' => (string) $row['post']->name,
                        'missing' => $row['expected'] - $row['done'],
                        'expected' => $row['expected'],
                    ])
                    ->sortBy([['installation', 'asc'], ['post', 'asc']])
                    ->values()
                    ->all();

                return [
                    'name' => (string) ($rows->first()['post']->client?->name ?? 'Cliente'),
                    'status' => match (true) {
                        $done >= $expected => 'green',
                        $done === 0 => 'red',
                        default => 'amber',
                    },
                    'installations' => $rows->map(fn (array $row) => (int) $row['post']->installation_id)->unique()->count(),
                    'posts' => $rows->count(),
                    'posts_done' => $rows->filter(fn (array $row) => $row['done'] >= $row['expected'])->count(),
                    'expected' => $expected,
                    'done' => $done,
                    'percent' => $expected > 0 ? round(($done / $expected) * 100, 1) : null,
                    'reviews' => $ofClient->count(),
                    'novelty' => $ofClient->where('has_novelty', true)->count(),
                    'pending' => $pending,
                ];
            })
            ->sort(fn (array $a, array $b) => [$rank[$a['status']], mb_strtolower($a['name'])] <=> [$rank[$b['status']], mb_strtolower($b['name'])])
            ->values()
            ->all();
    }

    /**
     * Solo lo que pide acción y no se ve ya en KPIs o tablas. Vacío si no hay nada.
     *
     * @param  Collection<int, SupervisorFieldLog>  $logs
     * @param  array{total: int, low: int, medium: int, high: int, extreme: int}  $recommendations
     * @param  Collection<int, SupervisorShift>  $shifts
     * @return list<array{level: string, text: string}>
     */
    private function alerts(Collection $logs, array $recommendations, Collection $shifts): array
    {
        $alerts = [];
        $alarms = $logs->filter(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Alarms);

        $real = $alarms->filter(fn (SupervisorFieldLog $log) => ($log->payload['result'] ?? '') === 'real')->count();
        if ($real > 0) {
            $alerts[] = ['level' => 'red', 'text' => $real.' '.($real === 1 ? 'alarma real' : 'alarmas reales')];
        }

        $failed = $alarms->filter(fn (SupervisorFieldLog $log) => ($log->payload['result'] ?? '') === 'fail')->count();
        if ($failed > 0) {
            $alerts[] = ['level' => 'red', 'text' => $failed.' '.($failed === 1 ? 'alarma en falla' : 'alarmas en falla')];
        }

        if ($recommendations['extreme'] > 0) {
            $n = $recommendations['extreme'];
            $alerts[] = ['level' => 'red', 'text' => $n.' '.($n === 1 ? 'recomendación extrema' : 'recomendaciones extremas')];
        }

        $longOpen = $shifts
            ->filter(fn (SupervisorShift $shift) => $shift->status === SupervisorShiftStatus::Open
                && $shift->started_at !== null
                && $shift->started_at->lt(now()->subHours(12)))
            ->count();
        if ($longOpen > 0) {
            $alerts[] = ['level' => 'amber', 'text' => $longOpen.' '.($longOpen === 1 ? 'turno abierto' : 'turnos abiertos').' > 12 h'];
        }

        $pendingDocs = $logs
            ->filter(fn (SupervisorFieldLog $log) => $log->module === SupervisorFieldModule::Documents
                && collect($log->payload['items'] ?? [])->contains(
                    fn (mixed $item) => is_array($item) && (int) ($item['pending'] ?? 0) > 0,
                ))
            ->count();
        if ($pendingDocs > 0) {
            $alerts[] = ['level' => 'amber', 'text' => $pendingDocs.' '.($pendingDocs === 1 ? 'entrega de documentos pendiente' : 'entregas de documentos pendientes')];
        }

        return $alerts;
    }
}
