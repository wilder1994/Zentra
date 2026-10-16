<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\ObservatoryEventStatus;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Observatory\StorePanelObservatoryReportRequest;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Models\User;
use App\Services\Observatory\BuildObservatoryBoardService;
use App\Services\Observatory\BuildObservatoryMapService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\ExportObservatoryBoardService;
use App\Services\Observatory\MergeObservatoryEventsService;
use App\Services\Observatory\PresentObservatoryLiveService;
use App\Services\Observatory\SubmitObservatoryReportService;
use App\Services\Observatory\UnhookObservatoryReportService;
use App\Services\Observatory\UpdateObservatoryEventStatusService;
use App\Services\Ops\NotifyOpsSurface;
use App\Support\Auth\AssignableRoles;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Observatory\ObservatoryPhotoInput;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ObservatoryEventController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UpdateObservatoryEventStatusService $statuses,
        private readonly MergeObservatoryEventsService $merger,
        private readonly UnhookObservatoryReportService $unhook,
        private readonly SubmitObservatoryReportService $submit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $filters = $this->filters($request);
        $board = app(BuildObservatoryBoardService::class);
        $siteIds = app(CaliComunaLayer::class)->scopeInstallationIds(
            $filters['comuna'],
            null,
            $clientId,
            $this->tenantContext->installationIds(),
        );

        $events = $board->scoped(null, $clientId, $siteIds, $filters['from'], $filters['to'])
            ->with(['installation', 'latestReport.reportType'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('installation', fn ($i) => $i->where('name', 'like', '%'.$filters['search'].'%')
                            ->orWhere('dane_code', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('opened_at')
            ->paginate(20)
            ->withQueryString();

        $client = Client::query()->findOrFail($clientId);
        app(EnsureObservatoryReportTypesService::class)->execute($client);

        return view('modules.observatory.client.index', [
            'events' => $events,
            'search' => $filters['search'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'vista' => $filters['vista'],
            'grain' => $filters['grain'],
            'comuna' => $filters['comuna'],
            'comunas' => app(CaliComunaLayer::class)->catalog(),
            'publicUrl' => route('observatory.public.show', $client->slug),
            'canReport' => $this->canReportFromPanel($request->user()),
            'reporterName' => $request->user()?->name,
            'board' => $board->execute(null, $clientId, $siteIds, $filters['from'], $filters['to'], $filters['grain']),
            'map' => app(BuildObservatoryMapService::class)->execute(
                null,
                $clientId,
                $siteIds,
                'client.observatory.events.show',
                $filters['comuna'],
            ),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $filters = $this->filters($request);
        $board = app(BuildObservatoryBoardService::class);
        $siteIds = app(CaliComunaLayer::class)->scopeInstallationIds(
            $filters['comuna'],
            null,
            $clientId,
            $this->tenantContext->installationIds(),
        );

        $events = $board->scoped(null, $clientId, $siteIds, $filters['from'], $filters['to'])
            ->with(['installation', 'latestReport.reportType'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('installation', fn ($i) => $i->where('name', 'like', '%'.$filters['search'].'%')
                            ->orWhere('dane_code', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('opened_at')
            ->paginate(20);

        return response()->json(app(PresentObservatoryLiveService::class)->execute(
            $board->execute(null, $clientId, $siteIds, $filters['from'], $filters['to'], $filters['grain']),
            app(BuildObservatoryMapService::class)->execute(
                null,
                $clientId,
                $siteIds,
                'client.observatory.events.show',
                $filters['comuna'],
            ),
            $events,
            'client.observatory.events.show',
            false,
        ));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $filters = $this->filters($request);
        $siteIds = app(CaliComunaLayer::class)->scopeInstallationIds(
            $filters['comuna'],
            null,
            $clientId,
            $this->tenantContext->installationIds(),
        );
        $client = Client::query()->findOrFail($clientId);
        $payload = app(BuildObservatoryBoardService::class)->execute(
            null,
            $clientId,
            $siteIds,
            $filters['from'],
            $filters['to'],
            $filters['grain'],
        );
        $export = app(ExportObservatoryBoardService::class);
        $file = $export->execute($payload, [
            'scope' => $client->name,
            'caption' => $export->caption(
                $filters['from'],
                $filters['to'],
                $filters['grain'],
                app(CaliComunaLayer::class)->label($filters['comuna']),
            ),
            'from' => $filters['from'],
            'to' => $filters['to'],
        ]);

        return response()
            ->download($file['path'], $file['filename'])
            ->deleteFileAfterSend();
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $user = $request->user();
        $canReport = $this->canReportFromPanel($user);
        $permission = $user?->installationAssignments()->value('installation_permission') ?? 'admin';

        $client = Client::query()->findOrFail((int) $this->tenantContext->clientId());
        app(EnsureObservatoryReportTypesService::class)->execute($client);

        return view('modules.observatory.client.create', [
            'canReport' => $canReport,
            'reporterName' => $user?->name,
            'sites' => $canReport ? $this->reportableSites() : collect(),
            'kinds' => ObservatoryReportType::optionsFor((int) $client->id),
            'roleLabel' => $permission === 'support'
                ? ObservatoryReporterRole::Apoyo->label()
                : ObservatoryReporterRole::Rector->label(),
        ]);
    }

    public function store(StorePanelObservatoryReportRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        abort_unless($this->canReportFromPanel($request->user()), 403);

        $installationId = (int) $request->validated('installation_id');
        abort_unless($this->tenantContext->allowsInstallation($installationId), 403);

        $user = $request->user();
        $role = $this->panelRole($user, $installationId);
        $anonymous = $request->boolean('is_anonymous');
        $client = Client::query()->findOrFail((int) $this->tenantContext->clientId());

        try {
            $report = $this->submit->execute($client, [
                'installation_id' => $installationId,
                'kind' => (string) $request->validated('kind'),
                'body' => (string) $request->validated('body'),
                'is_anonymous' => $anonymous,
                'source' => ObservatoryReportSource::Panel,
                'reporter_role' => $role,
                'reporter_name' => $anonymous ? null : $user->name,
                'reported_by' => $user,
                'photos' => ObservatoryPhotoInput::files($request),
                'photo' => $request->file('photo'),
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
            ], $request->ip());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('client.observatory.events.show', $report->event_id)
            ->with('success', 'Reporte enviado.');
    }

    public function show(Request $request, ObservatoryEvent $event): View
    {
        $this->assertClient($event);
        $event->load(['client', 'installation', 'reports.reportType', 'reports.reportedBy', 'statusLogs.user', 'closedBy']);
        $this->authorize('view', $event);

        $canUpdate = $request->user()?->can('update', $event) ?? false;

        return view('modules.observatory.client.show', [
            'event' => $event,
            'canUpdateStatus' => $canUpdate,
            'canMerge' => $canUpdate && $event->status !== ObservatoryEventStatus::Cerrado,
            'canDetach' => $canUpdate && $event->status !== ObservatoryEventStatus::Cerrado,
            'statuses' => ObservatoryEventStatus::options(),
            'mergeCandidates' => $this->mergeCandidates($event),
            'folioMap' => app(BuildObservatoryMapService::class)->forEvent($event),
        ]);
    }

    public function updateStatus(Request $request, ObservatoryEvent $event): RedirectResponse
    {
        $this->assertClient($event);
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

        return redirect()
            ->route('client.observatory.events.show', $event)
            ->with('success', $message);
    }

    public function merge(Request $request, ObservatoryEvent $event): RedirectResponse
    {
        $this->assertClient($event);
        $this->authorize('update', $event);

        $validated = $request->validate([
            'source_event_id' => ['required', 'integer', 'exists:observatory_events,id'],
        ]);

        $source = ObservatoryEvent::query()->findOrFail((int) $validated['source_event_id']);
        $this->assertClient($source);
        $this->authorize('update', $source);

        try {
            $this->merger->execute($event, $source);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        app(NotifyOpsSurface::class)->observatory(
            $event,
            $request->user(),
            'Unió folios en '.$event->folio(),
        );

        return redirect()
            ->route('client.observatory.events.show', $event)
            ->with('success', 'Folio '.$source->folio().' unido aquí.');
    }

    public function detach(Request $request, ObservatoryEvent $event, ObservatoryReport $report): RedirectResponse
    {
        $this->assertClient($event);
        $this->authorize('update', $event);
        abort_unless((int) $report->event_id === (int) $event->id, 404);

        try {
            $created = $this->unhook->execute($event, $report);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        app(NotifyOpsSurface::class)->observatory(
            $created,
            $request->user(),
            'Sacó un reporte a '.$created->folio(),
        );

        return redirect()
            ->route('client.observatory.events.show', $event)
            ->with('success', 'Reporte pasado a '.$created->folio().'.');
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

    private function assertClient(ObservatoryEvent $event): void
    {
        abort_unless((int) $event->client_id === (int) $this->tenantContext->clientId(), 404);
        abort_unless($this->tenantContext->allowsInstallation((int) $event->installation_id), 403);
    }

    private function canReportFromPanel(?User $user): bool
    {
        return $user !== null && $user->hasRole(AssignableRoles::CLIENT_INSTALLATION_ADMIN);
    }

    private function panelRole(User $user, int $installationId): ObservatoryReporterRole
    {
        abort_unless($user->canAccessInstallation($installationId), 403);

        return $user->isSiteSupport($installationId)
            ? ObservatoryReporterRole::Apoyo
            : ObservatoryReporterRole::Rector;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Installation> */
    private function reportableSites()
    {
        $siteIds = $this->tenantContext->installationIds();

        return Installation::query()
            ->where('client_id', (int) $this->tenantContext->clientId())
            ->where('is_active', true)
            ->when($siteIds !== null, fn ($q) => $q->whereIn('id', $siteIds))
            ->orderBy('name')
            ->get();
    }

    /** @return array{search: string, from: ?string, to: ?string, status: string, vista: string, grain: string, comuna: string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::enum(ObservatoryEventStatus::class)],
            'vista' => ['nullable', 'string', Rule::in(['tablero', 'eventos'])],
            'grain' => ['nullable', 'string', Rule::in(['day', 'month', 'year'])],
            'comuna' => ['nullable', 'string', 'max:12'],
        ]);

        return [
            'search' => trim((string) ($validated['q'] ?? '')),
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => (string) ($validated['status'] ?? ''),
            'vista' => (string) ($validated['vista'] ?? 'tablero'),
            'grain' => (string) ($validated['grain'] ?? 'day'),
            'comuna' => app(CaliComunaLayer::class)->normalize($validated['comuna'] ?? null),
        ];
    }
}
