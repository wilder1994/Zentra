<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryEventStatus;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Models\RiskModelParameter;
use App\Services\Company\BuildSupervisionMapService;
use App\Services\Ops\BuildActivityBubblesService;
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
            ->withCount([
                'supervisorPosts as posts_count' => fn ($q) => $q->where('is_active', true)->whereNull('archived_at'),
            ])
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
            ->whereHas('client', function ($client) use ($companyId): void {
                $client->where('has_observatory', true)
                    ->when($companyId !== null, fn ($query) => $query->where('security_company_id', $companyId));
            })
            ->when($installationIds !== null, fn ($q) => $q->whereIn('id', $installationIds))
            ->orderBy('name')
            ->get();
        $guardCounts = $this->guardCounts($sites->pluck('id')->all());

        $types = ObservatoryReportType::catalogForScope($companyId, $clientId);
        $crimeCompany = $companyId ?? ($clientId !== null
            ? (int) Client::query()->whereKey($clientId)->value('security_company_id')
            : 0);

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
                $pending = $events
                    ->filter(fn (ObservatoryEvent $event): bool => $event->status === ObservatoryEventStatus::Nuevo)
                    ->sortByDesc('id')
                    ->first();
                $pendingReport = $pending?->reports->sortByDesc('id')->first();
                $comunaHit = $layer->locate((float) $site->latitude, (float) $site->longitude);

                return [
                    'id' => (int) $site->id,
                    'name' => $site->name,
                    'posts_count' => (int) $site->posts_count,
                    'guards_count' => (int) ($guardCounts[(int) $site->id] ?? 0),
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
                    'risk_weight' => (float) ($openReports->max(fn (ObservatoryReport $report): float => $report->typeScore()) ?? 0),
                    'type_slugs' => $openReports->map(fn (ObservatoryReport $report): string => $report->typeSlug())->unique()->values()->all(),
                    'summary' => $this->typeSummary($openReports),
                    'report_summary' => $this->typeSummary($events->flatMap->reports),
                    'report_count' => $events->flatMap->reports->count(),
                    'pending' => $pending instanceof ObservatoryEvent ? [
                        'event_id' => (int) $pending->id,
                        'folio' => $pending->folio(),
                        'kind' => $pendingReport?->kindLabel() ?? $pending->kindLabel(),
                        'text' => mb_substr(trim((string) ($pendingReport?->body ?? '')), 0, 240),
                        'source' => $pendingReport?->sourceLabel() ?? '',
                        'show_url' => route($eventShowRoute, $pending),
                    ] : null,
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
                            'score' => $report->typeScore(),
                            'show_url' => route($eventShowRoute, $event),
                        ];
                    })->filter();
                });
            })->values()->all(),
            'crimes' => $crimeCompany > 0
                ? app(ManageObservatoryCrimeFiguresService::class)->mapPayload($crimeCompany, $from, $to)
                : ['types' => [], 'rows' => []],
            'shifts' => $this->shifts($companyId, $clientId, $installationIds, false),
            'bubbles' => $this->bubbles($companyId, $clientId),
        ];
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return list<array<string, mixed>>
     */
    public function positions(?int $companyId, ?int $clientId, ?array $installationIds = null): array
    {
        return $this->shifts($companyId, $clientId, $installationIds, false);
    }

    /**
     * @param  list<int>  $installationIds
     * @return array<int, int>
     */
    private function guardCounts(array $installationIds): array
    {
        if ($installationIds === []) {
            return [];
        }

        return DB::table('supervisor_post_employee as link')
            ->join('supervisor_posts as posts', 'posts.id', '=', 'link.supervisor_post_id')
            ->whereIn('posts.installation_id', $installationIds)
            ->where('posts.is_active', true)
            ->whereNull('posts.archived_at')
            ->whereNull('posts.deleted_at')
            ->groupBy('posts.installation_id')
            ->selectRaw('posts.installation_id as installation_id, count(distinct link.employee_id) as guards')
            ->pluck('guards', 'installation_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    private function shifts(?int $companyId, ?int $clientId, ?array $installationIds, bool $withPath): array
    {
        $resolvedCompany = $companyId ?? ($clientId !== null
            ? (int) Client::query()->whereKey($clientId)->value('security_company_id')
            : 0);
        if ($resolvedCompany < 1) {
            return [];
        }

        // Empresa: todos los turnos abiertos. Cliente: solo si tiene los dos módulos.
        // El recorrido no viaja aquí: el clic lo pide aparte.
        $scope = $clientId === null
            ? null
            : $this->dualClientIds($resolvedCompany, $clientId, $installationIds);

        return app(BuildSupervisionMapService::class)->openShiftsCovering($resolvedCompany, $scope, $withPath);
    }

    /**
     * Recorrido de un turno abierto de la empresa (o del cliente con ambos módulos).
     *
     * @return array{shift_id: int, path: list<array<string, mixed>>, lat: mixed, lng: mixed}|null
     */
    public function trail(?int $companyId, ?int $clientId, int $shiftId): ?array
    {
        $resolvedCompany = $companyId ?? ($clientId !== null
            ? (int) Client::query()->whereKey($clientId)->value('security_company_id')
            : 0);
        if ($resolvedCompany < 1 || $shiftId < 1) {
            return null;
        }

        if ($clientId !== null) {
            $allowed = array_column(
                app(BuildSupervisionMapService::class)->openShiftsCovering(
                    $resolvedCompany,
                    $this->dualClientIds($resolvedCompany, $clientId, null),
                ),
                'shift_id',
            );
            if (! in_array($shiftId, array_map('intval', $allowed), true)) {
                return null;
            }
        }

        return app(BuildSupervisionMapService::class)->trailOf($resolvedCompany, $shiftId);
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return list<int>
     */
    private function dualClientIds(int $companyId, ?int $clientId, ?array $installationIds): array
    {
        return Client::query()
            ->where('security_company_id', $companyId)
            ->where('has_observatory', true)
            ->where('has_supervision', true)
            ->when($clientId !== null, fn ($query) => $query->whereKey($clientId))
            ->when($installationIds !== null, fn ($query) => $query->whereHas(
                'installations',
                fn ($sites) => $sites->whereIn('id', $installationIds === [] ? [0] : $installationIds),
            ))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bubbles(?int $companyId, ?int $clientId): array
    {
        $resolvedCompany = $companyId ?? ($clientId !== null
            ? (int) Client::query()->whereKey($clientId)->value('security_company_id')
            : 0);

        return app(BuildActivityBubblesService::class)->forCompany($resolvedCompany, $clientId, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function forEvent(ObservatoryEvent $event): array
    {
        $event->loadMissing(['installation.client', 'client', 'reports.reportType']);
        $site = $event->installation;
        $layer = app(CaliComunaLayer::class);
        $hit = $site?->latitude !== null && $site?->longitude !== null
            ? $layer->locate((float) $site->latitude, (float) $site->longitude)
            : null;
        $comuna = is_array($hit) && ($hit['code'] ?? '') !== '' ? (string) $hit['code'] : 'fuera';
        $summary = $this->typeSummary($event->reports);
        $open = $event->status !== ObservatoryEventStatus::Cerrado;
        $guards = $site instanceof Installation ? $this->guardCounts([(int) $site->id]) : [];
        if ($site instanceof Installation) {
            $site->loadCount(['supervisorPosts as posts_count' => fn ($query) => $query->where('is_active', true)->whereNull('archived_at')]);
        }

        return [
            'google_maps' => $this->googleMaps(
                $site?->latitude !== null && $site?->longitude !== null
                    ? ['lat' => (float) $site->latitude, 'lng' => (float) $site->longitude]
                    : null,
                14,
            ),
            'folio' => true,
            'comuna' => $comuna,
            'comuna_name' => is_array($hit) ? (string) ($hit['name'] ?? '') : '',
            'layer_url' => route('geo.cali-comunas'),
            'types' => collect($summary)->map(fn (array $row): array => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'color' => $row['color'],
            ])->values()->all(),
            'sites' => $site instanceof Installation && $site->latitude !== null && $site->longitude !== null ? [[
                'id' => (int) $site->id,
                'name' => $site->name,
                'client' => $site->client?->name,
                'address' => PlaceLabel::of($site),
                'dane_code' => $site->dane_code,
                'lat' => (float) $site->latitude,
                'lng' => (float) $site->longitude,
                'comuna_name' => is_array($hit) ? (string) ($hit['name'] ?? 'Sin comuna') : 'Sin comuna',
                'posts_count' => (int) ($site->posts_count ?? 0),
                'guards_count' => (int) ($guards[(int) $site->id] ?? 0),
                'open_count' => $open ? $event->reports->count() : 0,
                'report_summary' => $summary,
                'report_count' => $event->reports->count(),
                'type_slugs' => collect($summary)->pluck('slug')->filter()->values()->all(),
                'pending' => null,
            ]] : [],
            'points' => [],
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
            ->sortByDesc(fn (ObservatoryReport $report): float => $report->typeScore())
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
