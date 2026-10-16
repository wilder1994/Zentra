<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Enums\ObservatoryReportSource;
use App\Models\Client;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Support\Geo\CaliComunaLayer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BuildObservatoryBoardService
{
    /**
     * @param  list<int>|null  $installationIds
     * @return array<string, mixed>
     */
    public function execute(
        ?int $companyId,
        ?int $clientId,
        ?array $installationIds,
        ?string $from,
        ?string $to,
        string $grain = 'day',
    ): array {
        if ($clientId !== null) {
            $client = Client::query()->find($clientId);
            if ($client) {
                app(EnsureObservatoryReportTypesService::class)->execute($client);
            }
        }

        $query = $this->scoped($companyId, $clientId, $installationIds, $from, $to);
        $grain = in_array($grain, ['day', 'month', 'year'], true) ? $grain : 'day';

        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $nuevo = (int) ($counts[ObservatoryEventStatus::Nuevo->value] ?? 0);
        $enAtencion = (int) ($counts[ObservatoryEventStatus::EnAtencion->value] ?? 0);
        $cerrado = (int) ($counts[ObservatoryEventStatus::Cerrado->value] ?? 0);
        $types = $this->types($companyId, $clientId);

        return [
            'total' => $total,
            'nuevo' => $nuevo,
            'en_atencion' => $enAtencion,
            'cerrado' => $cerrado,
            'closed_rate' => $total === 0 ? 0 : (int) round(100 * $cerrado / $total),
            'load_rate' => self::loadRate($nuevo, $enAtencion, $total),
            'top' => $this->ranking($query),
            'trend' => $this->trendByType($companyId, $clientId, $installationIds, $from, $to, $grain, $types),
            'peaks' => $this->peakDays($companyId, $clientId, $installationIds, $from, $to),
            'kinds' => $this->seriesByType($query, $types),
            'sources' => $this->seriesByReport($query, 'source', ObservatoryReportSource::cases()),
            'types' => $types,
            'grain' => $grain,
        ];
    }

    public static function loadRate(int $nuevo, int $enAtencion, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        $score = $nuevo + ($enAtencion * 0.4);

        return (int) round(100 * $score / $total);
    }

    /**
     * @param  list<int>|null  $installationIds
     */
    public function scoped(?int $companyId, ?int $clientId, ?array $installationIds, ?string $from, ?string $to): Builder
    {
        return ObservatoryEvent::query()
            ->whereNull('archived_at')
            ->when($clientId !== null, fn (Builder $q) => $q->where('client_id', $clientId))
            ->when($companyId !== null, fn (Builder $q) => $q->whereHas(
                'client',
                fn (Builder $c) => $c->where('security_company_id', $companyId),
            ))
            ->when($installationIds !== null, fn (Builder $q) => $q->whereIn('installation_id', $installationIds))
            ->when($from !== null && $from !== '', fn (Builder $q) => $q->whereDate('opened_at', '>=', $from))
            ->when($to !== null && $to !== '', fn (Builder $q) => $q->whereDate('opened_at', '<=', $to));
    }

    /**
     * @return list<array{id: int, ids: list<int>, slug: string, name: string, level: int, color: string}>
     */
    private function types(?int $companyId, ?int $clientId): array
    {
        return ObservatoryReportType::catalogForScope($companyId, $clientId);
    }

    /**
     * @return list<array{name: string, client: ?string, count: int, score: int, comuna: string, comuna_name: ?string}>
     */
    private function ranking(Builder $query): array
    {
        $events = (clone $query)->with(['installation.client', 'reports.reportType'])->get();

        $layer = app(CaliComunaLayer::class);
        $grouped = $events->groupBy('installation_id')->map(function (Collection $rows) use ($layer): array {
            $site = $rows->first()?->installation;
            $score = 0;
            foreach ($rows as $event) {
                foreach ($event->reports as $report) {
                    $score += $report->typeLevel();
                }
            }
            $comuna = $site?->latitude !== null && $site?->longitude !== null
                ? $layer->locate((float) $site->latitude, (float) $site->longitude)
                : null;

            return [
                'name' => $site?->name ?? '—',
                'client' => $site?->client?->name,
                'count' => $rows->count(),
                'score' => $score,
                'comuna' => $comuna['code'] ?? '',
                'comuna_name' => $comuna['name'] ?? null,
            ];
        })->sortByDesc('score')->take(8)->values();

        return $grouped->all();
    }

    /**
     * @param  list<int>|null  $installationIds
     * @param  list<array{id: int, slug: string, name: string, level: int, color: string}>  $types
     * @return array{labels: list<string>, series: list<array{label: string, color: string, values: list<int>}>}
     */
    private function trendByType(
        ?int $companyId,
        ?int $clientId,
        ?array $installationIds,
        ?string $from,
        ?string $to,
        string $grain,
        array $types,
    ): array {
        [$start, $end] = $this->range($from, $to, $grain);
        [$labels, $keys, $sql] = $this->buckets($start, $end, $grain);

        $eventIds = $this->scoped($companyId, $clientId, $installationIds, $start->toDateString(), $end->toDateString())->pluck('id');
        $raw = $eventIds->isEmpty()
            ? collect()
            : ObservatoryReport::query()
                ->whereIn('event_id', $eventIds->all())
                ->selectRaw($sql.' as bucket, COALESCE(observatory_report_type_id, 0) as type_id, COUNT(*) as aggregate')
                ->groupBy('bucket', 'type_id')
                ->get();

        $lookup = [];
        foreach ($raw as $row) {
            $lookup[(string) $row->bucket.'|'.(int) $row->type_id] = (int) $row->aggregate;
        }

        $series = [];
        foreach ($types as $type) {
            $values = [];
            foreach ($keys as $key) {
                $count = 0;
                foreach ($type['ids'] ?? [$type['id']] as $typeId) {
                    $count += (int) ($lookup[$key.'|'.$typeId] ?? 0);
                }
                $values[] = $count;
            }
            $series[] = [
                'label' => $type['name'],
                'color' => $type['color'],
                'values' => $values,
            ];
        }

        if ($series === []) {
            $series[] = ['label' => 'Reportes', 'color' => '#94a3b8', 'values' => array_fill(0, max(1, count($keys)), 0)];
        }

        if ($labels === []) {
            return ['labels' => ['—'], 'series' => [['label' => 'Reportes', 'color' => '#94a3b8', 'values' => [0]]]];
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return array{labels: list<string>, values: list<int>}
     */
    private function peakDays(?int $companyId, ?int $clientId, ?array $installationIds, ?string $from, ?string $to): array
    {
        $raw = $this->scoped($companyId, $clientId, $installationIds, $from, $to)
            ->selectRaw('DATE(opened_at) as bucket, COUNT(*) as aggregate')
            ->groupBy('bucket')
            ->orderByDesc('aggregate')
            ->orderBy('bucket')
            ->limit(7)
            ->get();

        if ($raw->isEmpty()) {
            return ['labels' => ['—'], 'values' => [0]];
        }

        return [
            'labels' => $raw->map(fn ($row) => Carbon::parse((string) $row->bucket)->isoFormat('D MMM'))->all(),
            'values' => $raw->map(fn ($row) => (int) $row->aggregate)->all(),
        ];
    }

    /**
     * @param  list<array{id: int, slug: string, name: string, level: int, color: string}>  $types
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    private function seriesByType(Builder $query, array $types): array
    {
        $eventIds = (clone $query)->pluck('id');
        $raw = $eventIds->isEmpty()
            ? collect()
            : ObservatoryReport::query()
                ->whereIn('event_id', $eventIds->all())
                ->selectRaw('observatory_report_type_id as bucket, COUNT(*) as aggregate')
                ->groupBy('bucket')
                ->pluck('aggregate', 'bucket');

        $labels = [];
        $values = [];
        $colors = [];
        foreach ($types as $type) {
            $count = 0;
            foreach ($type['ids'] ?? [$type['id']] as $typeId) {
                $count += (int) ($raw[$typeId] ?? 0);
            }
            $labels[] = $type['name'];
            $values[] = $count;
            $colors[] = $type['color'];
        }

        return ['labels' => $labels, 'values' => $values, 'colors' => $colors];
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return array{labels: list<string>, values: list<int>}
     */
    private function seriesByReport(Builder $query, string $column, array $cases): array
    {
        $eventIds = (clone $query)->pluck('id');
        $raw = $eventIds->isEmpty()
            ? collect()
            : ObservatoryReport::query()
                ->whereIn('event_id', $eventIds->all())
                ->selectRaw($column.' as bucket, COUNT(*) as aggregate')
                ->groupBy('bucket')
                ->pluck('aggregate', 'bucket');

        $labels = [];
        $values = [];
        foreach ($cases as $case) {
            $labels[] = $case->label();
            $values[] = (int) ($raw[$case->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(?string $from, ?string $to, string $grain): array
    {
        $end = ($to !== null && $to !== '') ? Carbon::parse($to)->startOfDay() : now()->startOfDay();
        if ($from !== null && $from !== '') {
            $start = Carbon::parse($from)->startOfDay();
        } elseif ($grain === 'year') {
            $start = $end->copy()->startOfYear();
        } elseif ($grain === 'month') {
            $start = $end->copy()->startOfMonth();
        } else {
            $start = $end->copy()->subDays(13);
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    /**
     * @return array{0: list<string>, 1: list<string>, 2: string}
     */
    private function buckets(Carbon $start, Carbon $end, string $grain): array
    {
        $labels = [];
        $keys = [];

        if ($grain === 'year') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $keys[] = $cursor->format('Y-m');
                $labels[] = $cursor->isoFormat('MMM');
                $cursor->addMonth();
            }

            return [$labels, $keys, "DATE_FORMAT(created_at, '%Y-%m')"];
        }

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $keys[] = $cursor->toDateString();
            $labels[] = $cursor->isoFormat('D MMM');
            $cursor->addDay();
        }

        return [$labels, $keys, 'DATE(created_at)'];
    }
}
