<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\RiskModelParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Datos del PPTX del Observatorio a partir de la misma consulta filtrada del tablero
 * (empresa, cliente o instalación, fechas, estado, tipo, comuna).
 */
final class BuildObservatoryExportDataService
{
    /**
     * @param  Builder<ObservatoryEvent>  $events
     * @param  list<array{slug: string, name: string, band?: string, color: string}>  $types
     * @return array{
     *     total: int,
     *     points: list<array{id: int, folio: string, lat: float, lng: float, slug: string, color: string, site: string}>,
     *     without_coords: int,
     *     by_type: array<string, array{name: string, color: string, band: string, events: int, reports: int, status: array<string, int>, sites: array<string, int>}>,
     *     reports: list<array{at: string, folio: string, site: string, origin: string, kind: string, excerpt: string}>
     * }
     */
    public function execute(Builder $events, array $types): array
    {
        $rows = (clone $events)
            ->reorder()
            ->with(['installation.client', 'reports.reportType'])
            ->orderByDesc('opened_at')
            ->get();

        $companyId = (int) ($rows->first()?->installation?->client?->security_company_id ?? 0);
        $parameters = $companyId > 0 ? RiskModelParameter::forCompany($companyId) : null;

        $byType = [];
        foreach ($types as $type) {
            $byType[$type['slug']] = [
                'name' => $type['name'],
                'color' => $type['color'],
                'band' => (string) ($type['band'] ?? 'BAJO'),
                'events' => 0,
                'reports' => 0,
                'status' => array_fill_keys(array_map(fn (ObservatoryEventStatus $s): string => $s->value, ObservatoryEventStatus::cases()), 0),
                'sites' => [],
            ];
        }

        $points = [];
        $withoutCoords = 0;
        $reports = [];

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
                    'band' => $parameters && $latest ? $parameters->bandOf($latest->typeScore()) : 'BAJO',
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

            foreach ($event->reports as $report) {
                $reports[] = [
                    'sort' => $report->created_at?->getTimestamp() ?? 0,
                    'at' => $report->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
                    'folio' => $event->folio(),
                    'site' => $siteName,
                    'origin' => $report->originLabel(),
                    'kind' => $report->kindLabel(),
                    'excerpt' => Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $report->body)), 90),
                ];
            }
        }

        usort($reports, fn (array $left, array $right): int => $right['sort'] <=> $left['sort']);
        foreach ($reports as &$report) {
            unset($report['sort']);
        }
        unset($report);

        foreach ($byType as &$type) {
            arsort($type['sites']);
        }
        unset($type);

        return [
            'total' => $rows->count(),
            'points' => $points,
            'without_coords' => $withoutCoords,
            'by_type' => $byType,
            'reports' => $reports,
        ];
    }
}
