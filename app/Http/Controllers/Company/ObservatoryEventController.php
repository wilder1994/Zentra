<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Enums\ObservatoryEventStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Models\SecurityCompany;
use App\Services\Observatory\BuildObservatoryBoardService;
use App\Services\Observatory\BuildObservatoryMapService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\BuildObservatoryExportDataService;
use App\Services\Observatory\ExportObservatoryBoardService;
use App\Services\Observatory\MergeObservatoryEventsService;
use App\Services\Observatory\PresentObservatoryLiveService;
use App\Services\Observatory\UnhookObservatoryReportService;
use App\Services\Observatory\UpdateObservatoryEventStatusService;
use App\Services\Ops\NotifyOpsSurface;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Observatory\ObservatoryFilters;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ObservatoryEventController extends Controller
{
    public function __construct(
        private readonly UpdateObservatoryEventStatusService $statuses,
        private readonly MergeObservatoryEventsService $merger,
        private readonly UnhookObservatoryReportService $unhook,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $companyId = $this->companyId($request);
        $allowed = $this->allowedSites($request);
        $filters = ObservatoryFilters::fromRequest($request, $companyId, null, $allowed);
        $board = app(BuildObservatoryBoardService::class);
        $siteIds = $filters->siteIds($companyId, $allowed);
        $clients = $this->clients($companyId);

        return view('modules.observatory.company.index', [
            'filters' => $filters,
            'events' => $this->events($board, $companyId, $siteIds, $filters)->paginate(20)->withQueryString(),
            'board' => $this->board($board, $companyId, $siteIds, $filters),
            'map' => $this->map($companyId, $siteIds, $filters),
            'filterOptions' => $this->filterOptions($companyId, $clients, $filters),
            'shareClients' => $clients,
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $companyId = $this->companyId($request);
        $allowed = $this->allowedSites($request);
        $filters = ObservatoryFilters::fromRequest($request, $companyId, null, $allowed);
        $board = app(BuildObservatoryBoardService::class);
        $siteIds = $filters->siteIds($companyId, $allowed);

        return response()->json(app(PresentObservatoryLiveService::class)->execute(
            $this->board($board, $companyId, $siteIds, $filters),
            $this->map($companyId, $siteIds, $filters),
            $this->events($board, $companyId, $siteIds, $filters)->paginate(20),
            'company.observatory.events.show',
            true,
        ));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $companyId = $this->companyId($request);
        $allowed = $this->allowedSites($request);
        $filters = ObservatoryFilters::fromRequest($request, $companyId, null, $allowed);
        $siteIds = $filters->siteIds($companyId, $allowed);
        $boardService = app(BuildObservatoryBoardService::class);
        $board = $this->board($boardService, $companyId, $siteIds, $filters);
        $company = SecurityCompany::query()->findOrFail($companyId);
        [$scope, $scopeKind] = match (true) {
            $filters->installationId !== null => [Installation::query()->withoutGlobalScopes()->whereKey($filters->installationId)->value('name'), 'Instalación'],
            $filters->clientId !== null => [Client::query()->whereKey($filters->clientId)->value('name'), 'Cliente'],
            default => [$company->displayName(), 'Empresa'],
        };
        $types = $filters->typesForExport($board['types'] ?? []);
        $data = app(BuildObservatoryExportDataService::class)->execute($this->events($boardService, $companyId, $siteIds, $filters), $types);

        $file = app(ExportObservatoryBoardService::class)->execute($board, $data, [
            'scope' => (string) ($scope ?: $company->displayName()),
            'scope_kind' => $scopeKind,
            'company' => $company->displayName(),
            'logo_path' => $company->logo_path,
            'filters' => $filters->labels($board['types'] ?? []),
            'from' => $filters->from,
            'to' => $filters->to,
            'grain' => $filters->grain(),
        ]);

        return response()
            ->download($file['path'], $file['filename'])
            ->deleteFileAfterSend();
    }

    public function types(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        $companyId = $this->companyId($request);
        $clients = $this->clients($companyId);
        $clientId = (int) $request->integer('client_id');
        $client = $clientId > 0 ? $clients->firstWhere('id', $clientId) : null;
        $ensure = app(EnsureObservatoryReportTypesService::class);

        if ($client instanceof Client) {
            $ensure->execute($client);
        }

        return view('modules.observatory.company.types', [
            'filterClients' => $clients,
            'filterClient' => $client,
            'types' => $client instanceof Client ? $client->observatoryReportTypes()->get() : collect(),
            'nextColor' => $client instanceof Client ? $ensure->nextColor($client) : '#94a3b8',
            'canEdit' => $request->user()?->can('observatory.events.update') ?? false,
        ]);
    }

    public function show(Request $request, ObservatoryEvent $event): View
    {
        $event->load(['client', 'installation', 'reports.reportType', 'reports.reportedBy', 'statusLogs.user', 'closedBy']);
        $this->assertCompany($request, $event);
        $this->authorize('view', $event);
        $canUpdate = $request->user()?->can('update', $event) ?? false;
        $open = $event->status !== ObservatoryEventStatus::Cerrado;

        return view('modules.observatory.company.show', [
            'event' => $event,
            'canUpdateStatus' => $canUpdate,
            'canMerge' => $canUpdate && $open,
            'canDetach' => $canUpdate && $open,
            'statuses' => ObservatoryEventStatus::options(),
            'mergeCandidates' => $canUpdate ? $this->mergeCandidates($event) : collect(),
            'folioMap' => app(BuildObservatoryMapService::class)->forEvent($event),
        ]);
    }

    public function updateStatus(Request $request, ObservatoryEvent $event): RedirectResponse
    {
        $this->assertCompany($request, $event);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(ObservatoryEventStatus::class)],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $target = ObservatoryEventStatus::from($validated['status']);
        $was = $event->status;

        try {
            $this->statuses->execute($event, $target, $request->user(), (string) $validated['note']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $message = match (true) {
            $was === $target && $target === ObservatoryEventStatus::EnAtencion => 'Observación agregada.',
            $target === ObservatoryEventStatus::Cerrado => 'Folio cerrado.',
            default => 'Folio en atención.',
        };

        return redirect()->route('company.observatory.events.show', $event)->with('success', $message);
    }

    public function merge(Request $request, ObservatoryEvent $event): RedirectResponse
    {
        $this->assertCompany($request, $event);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer', 'exists:observatory_events,id'],
        ]);

        $source = ObservatoryEvent::query()->findOrFail((int) $validated['source_event_id']);
        $this->assertCompany($request, $source);
        $this->authorize('update', $source);

        try {
            $this->merger->execute($event, $source);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        app(NotifyOpsSurface::class)->observatory($event, $request->user(), 'Unió folios en '.$event->folio());

        return redirect()->route('company.observatory.events.show', $event)
            ->with('success', 'Folio '.$source->folio().' unido aquí.');
    }

    public function detach(Request $request, ObservatoryEvent $event, ObservatoryReport $report): RedirectResponse
    {
        $this->assertCompany($request, $event);
        $this->authorize('update', $event);
        abort_unless((int) $report->event_id === (int) $event->id, 404);

        try {
            $created = $this->unhook->execute($event, $report);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        app(NotifyOpsSurface::class)->observatory($created, $request->user(), 'Sacó un reporte a '.$created->folio());

        return redirect()->route('company.observatory.events.show', $event)
            ->with('success', 'Reporte pasado a '.$created->folio().'.');
    }

    /**
     * Colaborador con clientes o instalaciones puntuales: solo esas. Admin de empresa: todas (null).
     *
     * @return list<int>|null
     */
    private function allowedSites(Request $request): ?array
    {
        $user = $request->user();

        return $user?->hasRole('colaborador') ? ($user->assignedInstallationIds() ?? []) : null;
    }

    /** @param  list<int>|null  $siteIds */
    private function events(BuildObservatoryBoardService $board, int $companyId, ?array $siteIds, ObservatoryFilters $filters)
    {
        return $filters->applySearch(
            $board->scoped($companyId, $filters->clientId, $siteIds, $filters->from, $filters->to, $filters->status, $filters->typeIds)
                ->with(['client', 'installation', 'latestReport.reportType']),
            true,
        )->orderByDesc('opened_at');
    }

    /**
     * @param  list<int>|null  $siteIds
     * @return array<string, mixed>
     */
    private function board(BuildObservatoryBoardService $board, int $companyId, ?array $siteIds, ObservatoryFilters $filters): array
    {
        return $board->execute(
            $companyId,
            $filters->clientId,
            $siteIds,
            $filters->from,
            $filters->to,
            $filters->grain(),
            $filters->status,
            $filters->typeIds,
        );
    }

    /**
     * @param  list<int>|null  $siteIds
     * @return array<string, mixed>
     */
    private function map(int $companyId, ?array $siteIds, ObservatoryFilters $filters): array
    {
        return app(BuildObservatoryMapService::class)->execute(
            $companyId,
            $filters->clientId,
            $siteIds,
            'company.observatory.events.show',
            $filters->comuna,
            $filters->from,
            $filters->to,
            $filters->status,
            $filters->typeIds,
        );
    }

    /**
     * Opciones del modal «Filtros»: clientes + instalaciones (buscador), tipos y comunas.
     *
     * @param  Collection<int, Client>  $clients
     * @return array<string, mixed>
     */
    private function filterOptions(int $companyId, Collection $clients, ObservatoryFilters $filters): array
    {
        $sites = Installation::query()
            ->withoutGlobalScopes()
            ->whereIn('client_id', $clients->pluck('id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'client_id', 'dane_code']);
        $clientNames = $clients->pluck('name', 'id');

        $places = $clients->map(fn (Client $client): array => [
            'value' => 'client:'.$client->id,
            'label' => $client->name,
            'hint' => 'Cliente',
        ])->values()->all();
        foreach ($sites as $site) {
            $places[] = [
                'value' => 'site:'.$site->id,
                'label' => $site->name,
                'hint' => trim(($clientNames[$site->client_id] ?? '').($site->dane_code ? ' · DANE '.$site->dane_code : '')),
            ];
        }

        return [
            'places' => $places,
            'types' => collect(ObservatoryReportType::catalogForScope($companyId, $filters->clientId))
                ->map(fn (array $type): array => ['value' => $type['slug'], 'label' => $type['name']])->values()->all(),
            'comunas' => app(CaliComunaLayer::class)->catalog(),
        ];
    }

    /** @return Collection<int, ObservatoryEvent> */
    private function mergeCandidates(ObservatoryEvent $event)
    {
        return ObservatoryEvent::query()
            ->where('client_id', $event->client_id)
            ->where('installation_id', $event->installation_id)
            ->whereNull('archived_at')
            ->whereKeyNot($event->id)
            ->withCount('reports')
            ->orderByDesc('opened_at')
            ->limit(30)
            ->get();
    }

    private function assertCompany(Request $request, ObservatoryEvent $event): void
    {
        $event->loadMissing('client');
        abort_unless((int) $event->client?->security_company_id === $this->companyId($request), 404);
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    /** @return Collection<int, Client> */
    private function clients(int $companyId)
    {
        return Client::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->where('has_observatory', true)
            ->whereNotNull('slug')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
}
