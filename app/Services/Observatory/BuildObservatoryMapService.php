<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Geo\PlaceLabel;

final class BuildObservatoryMapService
{
    /**
     * @param  list<int>|null  $installationIds
     * @param  list<int>|null  $typeIds
     * @return array<string, mixed>
     */
    public function execute(
        ?int $companyId,
        ?int $clientId,
        ?array $installationIds,
        string $eventShowRoute,
        string $comuna = '',
        ?string $from = null,
        ?string $to = null,
        string $status = '',
        ?array $typeIds = null,
    ): array {
        $layer = app(CaliComunaLayer::class);
        $typeScope = $typeIds === [] ? [0] : $typeIds;
        $sites = Installation::query()
            ->withoutGlobalScopes()
            ->with([
                'client:id,name,security_company_id',
                'observatoryEvents' => fn ($q) => $q
                    ->whereNull('archived_at')
                    ->when($from !== null && $from !== '', fn ($e) => $e->whereDate('opened_at', '>=', $from))
                    ->when($to !== null && $to !== '', fn ($e) => $e->whereDate('opened_at', '<=', $to))
                    ->when($status !== '', fn ($e) => $e->where('status', $status))
                    ->when($typeScope !== null, fn ($e) => $e->whereHas('reports', fn ($r) => $r->whereIn('observatory_report_type_id', $typeScope))),
                'observatoryEvents.reports' => fn ($q) => $q->when($typeScope !== null, fn ($r) => $r->whereIn('observatory_report_type_id', $typeScope)),
                'observatoryEvents.reports.reportType',
            ])
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->when($companyId !== null, fn ($q) => $q->whereHas(
                'client',
                fn ($c) => $c->where('security_company_id', $companyId),
            ))
            ->when($installationIds !== null, fn ($q) => $q->whereIn('id', $installationIds))
            ->orderBy('name')
            ->get();

        $types = ObservatoryReportType::catalogForScope($companyId, $clientId);

        return [
            'google_maps' => $this->googleMaps(),
            'types' => $types,
            'comunas' => $layer->catalog(),
            'comuna' => $layer->normalize($comuna),
            'layer_url' => route('geo.cali-comunas'),
            'sites' => $sites->map(function (Installation $site) use ($eventShowRoute, $layer): array {
                $events = $site->observatoryEvents;
                $open = $events->filter(
                    fn (ObservatoryEvent $event): bool => $event->status !== ObservatoryEventStatus::Cerrado,
                );
                $openReports = $open->flatMap->reports;
                $worst = $this->worstType($openReports);
                $status = $this->worstStatus($events);
                $focus = $open->sortByDesc('id')->first() ?? $events->sortByDesc('id')->first();
                $comunaHit = $layer->locate((float) $site->latitude, (float) $site->longitude);

                return [
                    'id' => (int) $site->id,
                    'name' => $site->name,
                    'client' => $site->client?->name,
                    'address' => PlaceLabel::of($site),
                    'dane_code' => $site->dane_code,
                    'lat' => (float) $site->latitude,
                    'lng' => (float) $site->longitude,
                    'comuna' => $comunaHit['code'] ?? '',
                    'comuna_name' => $comunaHit['name'] ?? 'Sin comuna Cali',
                    'open_count' => $open->count(),
                    'status' => $status?->value,
                    'status_label' => $status?->label() ?? 'Sin reportes',
                    'color' => $worst['color'],
                    'kind' => $worst['name'],
                    'kind_slug' => $worst['slug'],
                    'show_url' => $focus instanceof ObservatoryEvent
                        ? route($eventShowRoute, $focus)
                        : null,
                    'heat_weight' => max(1, $openReports->count()),
                    'risk_weight' => max(1, (int) $openReports->sum(fn (ObservatoryReport $report): int => $report->typeLevel())),
                    'type_slugs' => $openReports->map(fn (ObservatoryReport $report): string => $report->typeSlug())->unique()->values()->all(),
                    'summary' => $this->typeSummary($openReports),
                ];
            })->values()->all(),
            'points' => $sites->flatMap(function (Installation $site) use ($eventShowRoute, $layer) {
                return $site->observatoryEvents->flatMap(function (ObservatoryEvent $event) use ($site, $eventShowRoute, $layer) {
                    return $event->reports->map(function (ObservatoryReport $report) use ($site, $event, $eventShowRoute, $layer): ?array {
                        $lat = $report->latitude ?? $site->latitude;
                        $lng = $report->longitude ?? $site->longitude;
                        if ($lat === null || $lng === null) {
                            return null;
                        }
                        $comunaHit = $layer->locate((float) $lat, (float) $lng);

                        return [
                            'lat' => (float) $lat,
                            'lng' => (float) $lng,
                            'comuna' => $comunaHit['code'] ?? '',
                            'comuna_name' => $comunaHit['name'] ?? 'Sin comuna Cali',
                            'open' => $event->status !== ObservatoryEventStatus::Cerrado,
                            'status' => $event->status->value,
                            'status_label' => $event->statusLabel(),
                            'title' => $site->name,
                            'client' => $site->client?->name,
                            'address' => PlaceLabel::of($site),
                            'kind' => $report->kindLabel(),
                            'kind_slug' => $report->typeSlug(),
                            'color' => $report->typeColor(),
                            'level' => $report->typeLevel(),
                            'show_url' => route($eventShowRoute, $event),
                        ];
                    })->filter();
                });
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forEvent(ObservatoryEvent $event): array
    {
        $event->loadMissing(['installation', 'reports.reportType']);
        $site = $event->installation;
        $status = $event->status instanceof ObservatoryEventStatus ? $event->status->value : null;

        $points = $event->reports->map(function (ObservatoryReport $report) use ($site, $event, $status): ?array {
            $lat = $report->latitude ?? $site?->latitude;
            $lng = $report->longitude ?? $site?->longitude;
            if ($lat === null || $lng === null) {
                return null;
            }

            return [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'open' => $event->status !== ObservatoryEventStatus::Cerrado,
                'status' => $status,
                'status_label' => $event->statusLabel(),
                'title' => $site?->name ?? $event->folio(),
                'kind' => $report->kindLabel(),
                'kind_slug' => $report->typeSlug(),
                'color' => $report->typeColor(),
                'level' => $report->typeLevel(),
                'show_url' => null,
            ];
        })->filter()->values()->all();

        return [
            'google_maps' => $this->googleMaps(
                $site?->latitude !== null && $site?->longitude !== null
                    ? ['lat' => (float) $site->latitude, 'lng' => (float) $site->longitude]
                    : null,
                16,
            ),
            'types' => collect($points)
                ->unique('kind_slug')
                ->map(fn (array $point): array => [
                    'slug' => $point['kind_slug'],
                    'name' => $point['kind'],
                    'color' => $point['color'],
                    'level' => $point['level'],
                ])
                ->values()
                ->all(),
            'sites' => [],
            'points' => $points,
        ];
    }

    /** @return array{api_key: ?string, center: mixed, zoom: mixed} */
    private function googleMaps(mixed $center = null, mixed $zoom = null): array
    {
        return [
            'api_key' => config('google-maps.api_key'),
            'center' => $center ?? config('google-maps.default_center'),
            'zoom' => $zoom ?? config('google-maps.default_zoom'),
        ];
    }

    /** @param  \Illuminate\Support\Collection<int, ObservatoryEvent>  $events */
    private function worstStatus($events): ?ObservatoryEventStatus
    {
        if ($events->contains(fn (ObservatoryEvent $e): bool => $e->status === ObservatoryEventStatus::Nuevo)) {
            return ObservatoryEventStatus::Nuevo;
        }
        if ($events->contains(fn (ObservatoryEvent $e): bool => $e->status === ObservatoryEventStatus::EnAtencion)) {
            return ObservatoryEventStatus::EnAtencion;
        }
        if ($events->contains(fn (ObservatoryEvent $e): bool => $e->status === ObservatoryEventStatus::Cerrado)) {
            return ObservatoryEventStatus::Cerrado;
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ObservatoryReport>  $reports
     * @return array{name: string, slug: string, color: string}
     */
    private function worstType($reports): array
    {
        $worst = $reports
            ->sortByDesc(fn (ObservatoryReport $report): int => $report->typeLevel())
            ->first();

        if (! $worst instanceof ObservatoryReport) {
            return ['name' => 'Sin reportes', 'slug' => '', 'color' => '#94a3b8'];
        }

        return [
            'name' => $worst->kindLabel(),
            'slug' => $worst->typeSlug(),
            'color' => $worst->typeColor(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ObservatoryReport>  $reports
     * @return list<array{name: string, slug: string, count: int, color: string}>
     */
    private function typeSummary($reports): array
    {
        return $reports
            ->groupBy(fn (ObservatoryReport $report): string => $report->typeSlug() ?: 'otro')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'name' => $first instanceof ObservatoryReport ? $first->kindLabel() : '—',
                    'slug' => $first instanceof ObservatoryReport ? $first->typeSlug() : '',
                    'count' => $rows->count(),
                    'color' => $first instanceof ObservatoryReport ? $first->typeColor() : '#94a3b8',
                ];
            })
            ->values()
            ->all();
    }
}
