<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\RiskModelParameter;
use Illuminate\Database\Eloquent\Builder;

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
     *     by_type: array<string, array{name: string, color: string, band: string, events: int, reports: int, status: array<string, int>, sites: array<string, int>}>
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
        ];
    }
}
