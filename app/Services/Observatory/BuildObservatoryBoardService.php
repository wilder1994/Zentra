<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

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
        string $status = '',
        ?array $typeIds = null,
    ): array {
        if ($clientId !== null) {
            $client = Client::query()->find($clientId);
            if ($client) {
                app(EnsureObservatoryReportTypesService::class)->execute($client);
            }
        }

        $query = $this->scoped($companyId, $clientId, $installationIds, $from, $to, $status, $typeIds);
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
        $kinds = $this->seriesByType($query, $types);

        return [
            'total' => $total,
            'nuevo' => $nuevo,
            'en_atencion' => $enAtencion,
            'cerrado' => $cerrado,
            'closed_rate' => $total === 0 ? 0 : (int) round(100 * $cerrado / $total),
            'load_rate' => self::loadRate($nuevo, $enAtencion, $total),
            'trend' => $this->trendByType($companyId, $clientId, $installationIds, $from, $to, $grain, $types, $status, $typeIds),
            'peaks' => $this->peakDays($companyId, $clientId, $installationIds, $from, $to, $status, $typeIds),
            'kinds' => $kinds,
            'crimes' => $this->crimeRank($kinds),
            'sites' => $this->siteRank($query, $types),
            'sources' => $this->seriesByReport($query, 'source', ObservatoryReportSource::cases()),
            'origins' => $this->originSeries($query),
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
     * @param  list<int>|null  $typeIds  eventos con al menos un reporte de esos tipos
     */
    public function scoped(?int $companyId, ?int $clientId, ?array $installationIds, ?string $from, ?string $to, string $status = '', ?array $typeIds = null): Builder
    {
        return ObservatoryEvent::query()
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->when($typeIds !== null, fn (Builder $q) => $q->whereHas(
                'reports',
                fn (Builder $r) => $r->whereIn('observatory_report_type_id', $typeIds === [] ? [0] : $typeIds),
            ))
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
     * @return list<array{id: int, ids: list<int>, slug: string, name: string, impacto: int, urgencia: int, score: float, band: string, color: string}>
     */
    private function types(?int $companyId, ?int $clientId): array
    {
        return ObservatoryReportType::catalogForScope($companyId, $clientId);
    }

    /**
     * @param  list<int>|null  $installationIds
     * @param  list<array{id: int, slug: string, name: string, score: float, band: string, color: string}>  $types
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
        string $status = '',
        ?array $typeIds = null,
    ): array {
        [$start, $end] = $this->range($from, $to, $grain);
        [$labels, $keys, $sql] = $this->buckets($start, $end, $grain);

        $eventIds = $this->scoped($companyId, $clientId, $installationIds, $start->toDateString(), $end->toDateString(), $status, $typeIds)->pluck('id');
        $raw = $eventIds->isEmpty()
            ? collect()
            : ObservatoryReport::query()
                ->whereIn('event_id', $eventIds->all())
                ->when($typeIds !== null, fn ($q) => $q->whereIn('observatory_report_type_id', $typeIds === [] ? [0] : $typeIds))
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
    private function peakDays(?int $companyId, ?int $clientId, ?array $installationIds, ?string $from, ?string $to, string $status = '', ?array $typeIds = null): array
    {
        $raw = $this->scoped($companyId, $clientId, $installationIds, $from, $to, $status, $typeIds)
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
     * @param  list<array{id: int, slug: string, name: string, score: float, band: string, color: string}>  $types
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
     * @param  array{labels: list<string>, values: list<int>, colors?: list<string>}  $kinds
     * @return array{labels: list<string>, values: list<int>, colors: list<string>}
     */
    private function crimeRank(array $kinds): array
    {
        $rows = [];
        foreach ($kinds['labels'] as $index => $label) {
            $value = (int) ($kinds['values'][$index] ?? 0);
            if ($value < 1) {
                continue;
            }
            $rows[] = [
                'label' => (string) $label,
                'value' => $value,
                'color' => (string) ($kinds['colors'][$index] ?? '#94a3b8'),
            ];
        }
        usort($rows, fn (array $left, array $right): int => $right['value'] <=> $left['value'] ?: strcmp($left['label'], $right['label']));

        return [
            'labels' => array_column($rows, 'label'),
            'values' => array_map(intval(...), array_column($rows, 'value')),
            'colors' => array_column($rows, 'color'),
        ];
    }

    /**
     * @param  list<array{id: int, ids?: list<int>, name: string, color: string}>  $types
     * @return array{rows: list<array{name: string, total: int, parts: list<array{name: string, color: string, count: int}>}>}
     */
    private function siteRank(Builder $query, array $types): array
    {
        $eventIds = (clone $query)->pluck('id');
        if ($eventIds->isEmpty()) {
            return ['rows' => []];
        }

        $raw = ObservatoryReport::query()
            ->whereIn('event_id', $eventIds->all())
            ->whereNotNull('installation_id')
            ->selectRaw('installation_id, COALESCE(observatory_report_type_id, 0) as type_id, COUNT(*) as aggregate')
            ->groupBy('installation_id', 'type_id')
            ->get();

        $bySite = [];
        foreach ($raw as $row) {
            $bySite[(int) $row->installation_id][(int) $row->type_id] = (int) $row->aggregate;
        }
        $names = Installation::query()->whereIn('id', array_keys($bySite))->pluck('name', 'id');
        $rows = [];
        foreach ($bySite as $siteId => $counts) {
            $parts = [];
            $matched = 0;
            foreach ($types as $type) {
                $count = 0;
                foreach ($type['ids'] ?? [$type['id']] as $typeId) {
                    $count += (int) ($counts[(int) $typeId] ?? 0);
                }
                if ($count < 1) {
                    continue;
                }
                $parts[] = ['name' => $type['name'], 'color' => $type['color'], 'count' => $count];
                $matched += $count;
            }
            $leftover = array_sum($counts) - $matched;
            if ($leftover > 0) {
                $parts[] = ['name' => 'Otro', 'color' => '#94a3b8', 'count' => $leftover];
                $matched += $leftover;
            }
            if ($matched < 1) {
                continue;
            }
            $rows[] = [
                'name' => (string) ($names[$siteId] ?? 'Instalación'),
                'total' => $matched,
                'parts' => $parts,
            ];
        }
        usort($rows, fn (array $left, array $right): int => $right['total'] <=> $left['total'] ?: strcmp($left['name'], $right['name']));

        return ['rows' => $rows];
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

    /**
     * Orígenes del tablero. Agregar una fila para sumar otro origen.
     *
     * @return list<array{label: string, column: string, value: string}>
     */
    private function originDefinitions(): array
    {
        return [
            ['label' => 'Alumno', 'column' => 'reporter_role', 'value' => ObservatoryReporterRole::Alumno->value],
            ['label' => 'Padre', 'column' => 'reporter_role', 'value' => ObservatoryReporterRole::Padre->value],
            ['label' => 'Vecino', 'column' => 'reporter_role', 'value' => ObservatoryReporterRole::Vecino->value],
            ['label' => 'Usuario panel', 'column' => 'source', 'value' => ObservatoryReportSource::Panel->value],
            ['label' => 'Usuario API', 'column' => 'source', 'value' => ObservatoryReportSource::Api->value],
            ['label' => 'Usuario app patrulla', 'column' => 'source', 'value' => ObservatoryReportSource::Campo->value],
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function originSeries(Builder $query): array
    {
        $eventIds = (clone $query)->pluck('id');
        $counts = [];
        if ($eventIds->isNotEmpty()) {
            foreach (['reporter_role', 'source'] as $column) {
                $counts[$column] = ObservatoryReport::query()
                    ->whereIn('event_id', $eventIds->all())
                    ->selectRaw($column.' as bucket, COUNT(*) as aggregate')
                    ->groupBy('bucket')
                    ->pluck('aggregate', 'bucket');
            }
        }

        $labels = [];
        $values = [];
        foreach ($this->originDefinitions() as $origin) {
            $labels[] = $origin['label'];
            $values[] = (int) ($counts[$origin['column']][$origin['value']] ?? 0);
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
