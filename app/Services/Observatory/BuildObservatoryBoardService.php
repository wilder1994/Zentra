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
use App\Models\RiskModelParameter;
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
        $owner = $this->ownerId($companyId, $clientId);
        $parameters = $owner > 0 ? RiskModelParameter::forCompany($owner) : null;
        $stored = app(ObservatoryChartPalette::class)->stored($owner);
        $types = $this->types($companyId, $clientId);
        $kinds = $this->seriesByType($query, $types);

        return [
            'total' => $total,
            'nuevo' => $nuevo,
            'en_atencion' => $enAtencion,
            'cerrado' => $cerrado,
            'closed_rate' => $total === 0 ? 0 : (int) round(100 * $cerrado / $total),
            'load_rate' => self::loadRate($nuevo, $enAtencion, $total),
            'trend' => $this->trendByType($companyId, $clientId, $installationIds, $from, $to, $grain, $types, $status, $typeIds, $stored),
            'peaks' => $this->peakDays($companyId, $clientId, $installationIds, $from, $to, $status, $typeIds),
            'kinds' => $kinds,
            'crimes' => $this->crimeRank($kinds),
            'sites' => $this->siteRank($query, $types),
            'inks' => [
                $this->ink($parameters, 'BAJO'),
                $this->ink($parameters, 'MEDIO'),
                $this->ink($parameters, 'ALTO'),
                $this->ink($parameters, 'CRITICO'),
            ],
            'sources' => $this->paintSeries($this->seriesByReport($query, 'source', ObservatoryReportSource::cases()), 'sources', $stored, array_map(fn (ObservatoryReportSource $source): string => $source->value, ObservatoryReportSource::cases())),
            'origins' => $this->paintSeries($this->originSeries($query), 'origins', $stored, array_column($this->originDefinitions(), 'value')),
            'paints' => $this->paints($types, $stored),
            'types' => $types,
            'grain' => $grain,
        ];
    }

    public static function loadRate(int $nuevo, int $enAtencion, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        $cerrado = max(0, $total - $nuevo - $enAtencion);
        $score = $cerrado + ($enAtencion * 0.5);

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
        array $stored = [],
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
                'key' => (string) $type['slug'],
                'color' => app(ObservatoryChartPalette::class)->color($stored, 'trend', (string) $type['slug'], count($series)),
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
            $colors[] = (string) ($type['color'] ?? '#94a3b8');
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
                $parts[] = ['name' => $type['name'], 'color' => (string) ($type['color'] ?? '#94a3b8'), 'count' => $count];
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

    private function ownerId(?int $companyId, ?int $clientId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }
        if ($clientId === null) {
            return 0;
        }

        return (int) Client::query()->whereKey($clientId)->value('security_company_id');
    }

    /**
     * @param  array{labels: list<string>, values: list<int>}  $series
     * @param  array<string, array<string, string>>  $stored
     * @param  list<string>  $keys
     * @return array{labels: list<string>, values: list<int>, keys: list<string>, colors: list<string>}
     */
    private function paintSeries(array $series, string $chart, array $stored, array $keys): array
    {
        $palette = app(ObservatoryChartPalette::class);
        $colors = [];
        foreach ($keys as $index => $key) {
            $colors[] = $palette->color($stored, $chart, $key, $index);
        }
        $series['keys'] = $keys;
        $series['colors'] = $colors;

        return $series;
    }

    /**
     * @param  list<array{slug: string, name: string}>  $types
     * @param  array<string, array<string, string>>  $stored
     * @return array{sources: list<array{key: string, label: string, color: string}>, origins: list<array{key: string, label: string, color: string}>, trend: list<array{key: string, label: string, color: string}>}
     */
    private function paints(array $types, array $stored): array
    {
        $palette = app(ObservatoryChartPalette::class);
        $sources = [];
        foreach (ObservatoryReportSource::cases() as $index => $source) {
            $sources[] = [
                'key' => $source->value,
                'label' => $source->label(),
                'color' => $palette->color($stored, 'sources', $source->value, $index),
            ];
        }
        $origins = [];
        foreach ($this->originDefinitions() as $index => $origin) {
            $origins[] = [
                'key' => $origin['value'],
                'label' => $origin['label'],
                'color' => $palette->color($stored, 'origins', $origin['value'], $index),
            ];
        }
        $trend = [];
        foreach (array_values($types) as $index => $type) {
            $trend[] = [
                'key' => (string) $type['slug'],
                'label' => (string) $type['name'],
                'color' => $palette->color($stored, 'trend', (string) $type['slug'], $index),
            ];
        }

        return ['sources' => $sources, 'origins' => $origins, 'trend' => $trend];
    }

    private function ink(?RiskModelParameter $parameters, string $band): string
    {
        return $parameters?->colorFor($band) ?? match ($band) {
            'MEDIO' => '#854d0e',
            'ALTO' => '#9a3412',
            'CRITICO' => '#9f1239',
            default => '#14532d',
        };
    }
}
