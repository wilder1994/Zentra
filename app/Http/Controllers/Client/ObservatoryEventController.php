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
use App\Services\Observatory\BuildInstallationRiskService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\BuildObservatoryExportDataService;
use App\Services\Observatory\ExportObservatoryBoardService;
use App\Services\Observatory\MoveObservatoryReportService;
use App\Services\Observatory\PresentObservatoryLiveService;
use App\Services\Observatory\SubmitObservatoryReportService;
use App\Services\Observatory\UpdateObservatoryEventStatusService;
use App\Support\Auth\AssignableRoles;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Observatory\ObservatoryFilters;
use App\Support\Observatory\ObservatoryPhotoInput;
use App\Support\Tenancy\TenantContext;
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
        private readonly TenantContext $tenantContext,
        private readonly UpdateObservatoryEventStatusService $statuses,
        private readonly MoveObservatoryReportService $moves,
        private readonly SubmitObservatoryReportService $submit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $allowed = $this->tenantContext->installationIds();
        $filters = ObservatoryFilters::fromRequest($request, null, $clientId, $allowed);
        $board = app(BuildObservatoryBoardService::class);
        $siteIds = $filters->siteIds(null, $allowed);

        $client = Client::query()->findOrFail($clientId);
        app(EnsureObservatoryReportTypesService::class)->execute($client);

        return view('modules.observatory.client.index', [
            'filters' => $filters,
            'events' => $this->eventsQuery($board, $clientId, $siteIds, $filters)->paginate(20)->withQueryString(),
            'publicUrl' => route('observatory.public.show', $client->slug),
            'canReport' => $this->canReportFromPanel($request->user()),
            'reporterName' => $request->user()?->name,
            'board' => $this->boardPayload($board, $clientId, $siteIds, $filters),
            'map' => $this->mapPayload($clientId, $siteIds, $filters),
            'filterOptions' => $this->filterOptions($clientId, $allowed),
            'risk' => app(BuildInstallationRiskService::class)->execute((int) $client->security_company_id, $clientId, $siteIds),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $allowed = $this->tenantContext->installationIds();
        $filters = ObservatoryFilters::fromRequest($request, null, $clientId, $allowed);
        $board = app(BuildObservatoryBoardService::class);
        $siteIds = $filters->siteIds(null, $allowed);
        $companyId = (int) Client::query()->whereKey($clientId)->value('security_company_id');

        return response()->json(app(PresentObservatoryLiveService::class)->execute(
            $this->boardPayload($board, $clientId, $siteIds, $filters),
            $this->mapPayload($clientId, $siteIds, $filters),
            $this->eventsQuery($board, $clientId, $siteIds, $filters)->paginate(20),
            'client.observatory.events.show',
            false,
            app(BuildInstallationRiskService::class)->execute($companyId, $clientId, $siteIds),
        ));
    }

    public function shiftPositions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $allowed = $this->tenantContext->installationIds();
        $filters = ObservatoryFilters::fromRequest($request, null, $clientId, $allowed);

        return response()->json([
            'shifts' => app(BuildObservatoryMapService::class)->positions(
                null,
                $clientId,
                $filters->siteIds(null, $allowed),
            ),
        ]);
    }

    public function shiftTrail(Request $request, int $shift): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);
        $trail = app(BuildObservatoryMapService::class)->trail(null, $clientId, $shift);
        abort_unless($trail !== null, 404);

        return response()->json($trail);
    }

    public function export(Request $request): BinaryFileResponse|\Illuminate\View\View|JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);

        return \App\Jobs\ExportObservatoryBoardJob::start($request, [
            'panel' => 'client',
            'client_id' => $clientId,
            'allowed' => $this->tenantContext->installationIds(),
        ], 'client.observatory.board.export.status', 'client.observatory.board.export.file');
    }

    public function exportStatus(Request $request, string $token): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        return response()->json($this->exportState($request, $token));
    }

    public function exportFile(Request $request, string $token): BinaryFileResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        $state = $this->exportState($request, $token);
        abort_unless(($state['status'] ?? '') === 'ready' && is_string($state['path'] ?? null) && is_file($state['path']), 404);

        return response()->download($state['path'], (string) $state['filename'])->deleteFileAfterSend();
    }

    /** @return array<string, mixed> */
    private function exportState(Request $request, string $token): array
    {
        $state = \Illuminate\Support\Facades\Cache::get(\App\Jobs\ExportObservatoryBoardJob::key($token));
        abort_unless(is_array($state) && (int) ($state['user_id'] ?? 0) === (int) $request->user()->id, 404);

        return [
            'status' => (string) ($state['status'] ?? 'queued'),
            'message' => (string) ($state['message'] ?? ''),
            'path' => $state['path'] ?? null,
            'filename' => $state['filename'] ?? null,
        ];
    }

    /** @param  list<int>|null  $siteIds */
    private function eventsQuery(BuildObservatoryBoardService $board, int $clientId, ?array $siteIds, ObservatoryFilters $filters)
    {
        return $filters->applySearch(
            $board->scoped(null, $clientId, $siteIds, $filters->from, $filters->to, $filters->status, $filters->typeIds)
                ->with(['installation', 'latestReport.reportType']),
            false,
        )->orderByDesc('opened_at');
    }

    /**
     * @param  list<int>|null  $siteIds
     * @return array<string, mixed>
     */
    private function boardPayload(BuildObservatoryBoardService $board, int $clientId, ?array $siteIds, ObservatoryFilters $filters): array
    {
        return $board->execute(null, $clientId, $siteIds, $filters->from, $filters->to, $filters->grain(), $filters->status, $filters->typeIds);
    }

    /**
     * @param  list<int>|null  $siteIds
     * @return array<string, mixed>
     */
    private function mapPayload(int $clientId, ?array $siteIds, ObservatoryFilters $filters): array
    {
        return app(BuildObservatoryMapService::class)->execute(
            null,
            $clientId,
            $siteIds,
            'client.observatory.events.show',
            $filters->comuna,
            $filters->from,
            $filters->to,
            $filters->status,
            $filters->typeIds,
        );
    }

    /**
     * @param  list<int>|null  $allowed
     * @return array<string, mixed>
     */
    private function filterOptions(int $clientId, ?array $allowed): array
    {
        $sites = Installation::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->orderBy('name')
            ->get(['id', 'name', 'dane_code']);

        return [
            'places' => $sites->map(fn (Installation $site): array => [
                'value' => 'site:'.$site->id,
                'label' => $site->name,
                'hint' => $site->dane_code ? 'DANE '.$site->dane_code : 'Instalación',
            ])->values()->all(),
            'types' => collect(ObservatoryReportType::catalogFor($clientId))
                ->map(fn (array $type): array => ['value' => $type['slug'], 'label' => $type['name']])->values()->all(),
            'comunas' => app(CaliComunaLayer::class)->catalog(),
        ];
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
                'community_only' => false,
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
            'canMove' => $canUpdate && $event->status !== ObservatoryEventStatus::Cerrado,
            'statuses' => ObservatoryEventStatus::options(),
            'moveOptions' => $canUpdate && $event->status !== ObservatoryEventStatus::Cerrado
                ? $this->moves->destinations(
                    $event,
                    (int) ($event->client?->security_company_id ?? 0),
                    (int) $this->tenantContext->clientId(),
                )
                : [],
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

    public function move(Request $request, ObservatoryEvent $event, ObservatoryReport $report): RedirectResponse
    {
        $this->assertClient($event);
        $this->authorize('update', $event);
        abort_unless((int) $report->event_id === (int) $event->id, 404);

        $validated = $request->validate([
            'installation_id' => ['required', 'integer', 'exists:installations,id'],
            'destination' => ['required', 'string', 'max:20'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $installation = Installation::query()->find((int) $validated['installation_id']);
        abort_unless(
            $installation instanceof Installation && (int) $installation->client_id === (int) $event->client_id,
            404,
        );

        try {
            $target = $this->moves->execute(
                $event,
                $report,
                $request->user(),
                (string) $validated['note'],
                (int) $validated['installation_id'],
                (string) $validated['destination'],
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $event->refresh();
        $message = 'Reporte '.$report->code().' pasó a '.$target->folio().'.';
        if ($event->status === ObservatoryEventStatus::Cerrado) {
            $message .= ' Este folio se cerró porque quedó sin reportes.';
        }

        return redirect()
            ->route('client.observatory.events.show', $event)
            ->with('success', $message);
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
}
