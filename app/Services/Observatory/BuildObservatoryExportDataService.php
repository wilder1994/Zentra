<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Support\Geo\CaliComunaLayer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Datos del PPTX del Observatorio a partir de la misma consulta filtrada del tablero
 * (empresa, cliente o instalación, fechas, estado, tipo, comuna).
 */
final class BuildObservatoryExportDataService
{
    /**
     * @param  Builder<ObservatoryEvent>  $events
     * @param  list<array{slug: string, name: string, level: int, color: string}>  $types
     * @return array{
     *     total: int,
     *     points: list<array{id: int, folio: string, lat: float, lng: float, slug: string, color: string, site: string}>,
     *     without_coords: int,
     *     by_type: array<string, array{name: string, color: string, level: int, events: int, reports: int, status: array<string, int>, sites: array<string, int>}>,
     *     ranking: list<array{name: string, client: ?string, comuna: ?string, count: int, score: int, top_type: string}>
     * }
     */
    public function execute(Builder $events, array $types): array
    {
        $rows = (clone $events)
            ->reorder()
            ->with(['installation.client', 'reports.reportType'])
            ->orderByDesc('opened_at')
            ->get();

        $byType = [];
        foreach ($types as $type) {
            $byType[$type['slug']] = [
                'name' => $type['name'],
                'color' => $type['color'],
                'level' => (int) $type['level'],
                'events' => 0,
                'reports' => 0,
                'status' => array_fill_keys(array_map(fn (ObservatoryEventStatus $s): string => $s->value, ObservatoryEventStatus::cases()), 0),
                'sites' => [],
            ];
        }

        $points = [];
        $withoutCoords = 0;
        $sites = [];

        foreach ($rows as $event) {
            /** @var ObservatoryReport|null $latest */
            $latest = $event->reports->first();
            $slug = $latest?->typeSlug() ?? 'otro';
            $siteName = $event->installation?->name ?? '—';
            $status = $event->status instanceof ObservatoryEventStatus ? $event->status->value : 'nuevo';

            if (! isset($byType[$slug])) {
                $byType[$slug] = [
                    'name' => $latest?->kindLabel() ?? 'Otro',
                    'color' => $latest?->typeColor() ?? '#94a3b8',
                    'level' => $latest?->typeLevel() ?? 1,
                    'events' => 0,
                    'reports' => 0,
                    'status' => ['nuevo' => 0, 'en_atencion' => 0, 'cerrado' => 0],
                    'sites' => [],
                ];
            }
            $byType[$slug]['events']++;
            $byType[$slug]['reports'] += $event->reports->count();
            $byType[$slug]['status'][$status] = ($byType[$slug]['status'][$status] ?? 0) + 1;
            $byType[$slug]['sites'][$siteName] = ($byType[$slug]['sites'][$siteName] ?? 0) + 1;

            $lat = $latest?->latitude ?? $event->installation?->latitude;
            $lng = $latest?->longitude ?? $event->installation?->longitude;
            if ($lat !== null && $lng !== null) {
                $points[] = [
                    'id' => (int) $event->id,
                    'folio' => $event->folio(),
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'slug' => $slug,
                    'color' => $byType[$slug]['color'],
                    'site' => $siteName,
                ];
            } else {
                $withoutCoords++;
            }

            $key = (int) $event->installation_id;
            $sites[$key] ??= ['site' => $event->installation, 'count' => 0, 'score' => 0, 'types' => []];
            $sites[$key]['count']++;
            foreach ($event->reports as $report) {
                $sites[$key]['score'] += $report->typeLevel();
            }
            $sites[$key]['types'][$slug] = ($sites[$key]['types'][$slug] ?? 0) + 1;
        }

        foreach ($byType as &$type) {
            arsort($type['sites']);
        }
        unset($type);

        return [
            'total' => $rows->count(),
            'points' => $points,
            'without_coords' => $withoutCoords,
            'by_type' => $byType,
            'ranking' => $this->ranking($sites, $byType),
        ];
    }

    /**
     * @param  array<int, array{site: mixed, count: int, score: int, types: array<string, int>}>  $sites
     * @param  array<string, array{name: string}>  $byType
     * @return list<array{name: string, client: ?string, comuna: ?string, count: int, score: int, top_type: string}>
     */
    private function ranking(array $sites, array $byType): array
    {
        $layer = app(CaliComunaLayer::class);
        $rows = [];
        foreach ($sites as $row) {
            $site = $row['site'];
            arsort($row['types']);
            $topSlug = (string) array_key_first($row['types']);
            $comuna = $site?->latitude !== null && $site?->longitude !== null
                ? $layer->locate((float) $site->latitude, (float) $site->longitude)
                : null;

            $rows[] = [
                'name' => $site?->name ?? '—',
                'client' => $site?->client?->name,
                'comuna' => $comuna['name'] ?? null,
                'count' => $row['count'],
                'score' => $row['score'],
                'top_type' => $byType[$topSlug]['name'] ?? '—',
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$b['score'], $b['count']] <=> [$a['score'], $a['count']]);

        return $rows;
    }
}
