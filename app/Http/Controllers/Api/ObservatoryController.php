<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ObservatoryEventStatus;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreObservatoryApiReportRequest;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\User;
use App\Services\Observatory\BuildObservatoryBoardService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\PresentObservatoryApiService;
use App\Services\Observatory\ResolveObservatoryApiScopeService;
use App\Services\Observatory\SubmitObservatoryReportService;
use App\Support\Auth\AssignableRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ObservatoryController extends Controller
{
    public function __construct(
        private readonly ResolveObservatoryApiScopeService $scope,
        private readonly BuildObservatoryBoardService $board,
        private readonly PresentObservatoryApiService $present,
        private readonly SubmitObservatoryReportService $submit,
    ) {}

    public function events(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $scope = $this->scope->execute($request->user(), $filters['client_id']);
        $query = $this->board->scoped(
            $scope['company_id'],
            $scope['client_id'],
            $scope['installation_ids'],
            $filters['from'],
            $filters['to'],
        )->with(['installation', 'client'])->withCount('reports');

        if ($filters['installation_id'] !== null) {
            $this->assertInstallationInScope($filters['installation_id'], $scope);
            $query->where('installation_id', $filters['installation_id']);
        }

        if ($filters['search'] !== '') {
            $query->where(function ($inner) use ($filters): void {
                $inner->where('title', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('installation', fn ($i) => $i->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('dane_code', 'like', '%'.$filters['search'].'%'));
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('opened_at')->paginate(20)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (ObservatoryEvent $event): array => $this->present->event($event))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, ObservatoryEvent $event): JsonResponse
    {
        $scope = $this->scope->execute($request->user(), $request->integer('client_id') ?: null);
        $this->assertEventInScope($event, $scope);
        $event->load(['installation', 'client', 'reports.reportType']);

        return response()->json([
            'event' => $this->present->event($event, true),
        ]);
    }

    public function board(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $scope = $this->scope->execute($request->user(), $filters['client_id']);

        return response()->json([
            'board' => $this->board->execute(
                $scope['company_id'],
                $scope['client_id'],
                $scope['installation_ids'],
                $filters['from'],
                $filters['to'],
            ),
        ]);
    }

    public function sites(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $scope = $this->scope->execute($request->user(), $filters['client_id']);
        $search = $filters['search'];

        $sites = Installation::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->when($scope['client_id'] !== null, fn ($q) => $q->where('client_id', $scope['client_id']))
            ->when($scope['company_id'] !== null, fn ($q) => $q->whereHas(
                'client',
                fn ($c) => $c->where('security_company_id', $scope['company_id']),
            ))
            ->when($scope['installation_ids'] !== null, fn ($q) => $q->whereIn('id', $scope['installation_ids']))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('dane_code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'client_id', 'name', 'dane_code', 'city', 'latitude', 'longitude']);

        $clientIds = $scope['client_id'] !== null
            ? [$scope['client_id']]
            : $sites->pluck('client_id')->all();
        $kindsByClient = app(EnsureObservatoryReportTypesService::class)->optionsByClient($clientIds);
        $kinds = [];
        foreach ($kindsByClient as $map) {
            $kinds = $kinds + $map;
        }

        return response()->json([
            'sites' => $sites->map(fn (Installation $site): array => $this->present->site($site))->all(),
            'kinds' => $kinds,
            'kinds_by_client' => $kindsByClient,
        ]);
    }

    public function store(StoreObservatoryApiReportRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasRole('company-admin') && ! $user->hasRole(AssignableRoles::CLIENT_ADMIN), 403, 'La empresa solo consulta el Observatorio.');

        $scope = $this->scope->execute($user, $request->integer('client_id') ?: null);
        $installationId = (int) $request->validated('installation_id');
        $this->assertInstallationInScope($installationId, $scope);

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->with('client')
            ->whereKey($installationId)
            ->where('is_active', true)
            ->first();

        if ($installation?->client === null) {
            throw ValidationException::withMessages([
                'installation_id' => 'Elige una instalación de tu alcance.',
            ]);
        }

        [$source, $role] = $this->writeOrigin($user, $installationId);
        $anonymous = $request->boolean('is_anonymous');
        $before = ObservatoryEvent::query()
            ->where('client_id', $installation->client_id)
            ->where('installation_id', $installation->id)
            ->count();

        $report = $this->submit->execute($installation->client, [
            'installation_id' => $installationId,
            'kind' => (string) $request->validated('kind'),
            'body' => (string) $request->validated('body'),
            'is_anonymous' => $anonymous,
            'source' => $source,
            'reporter_role' => $role,
            'reporter_name' => $anonymous ? null : ($request->validated('reporter_name') ?? $user->name),
            'reporter_phone' => $anonymous ? null : $request->validated('reporter_phone'),
            'reported_by' => $user,
            'photo' => $request->file('photo'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
        ], $request->ip());

        $report->load('event');
        $after = ObservatoryEvent::query()
            ->where('client_id', $installation->client_id)
            ->where('installation_id', $installation->id)
            ->count();

        return response()->json([
            'report' => [
                'id' => $report->id,
                'event_id' => $report->event_id,
                'folio' => $report->event?->folio(),
                'merged' => $after === $before,
            ],
        ], 201);
    }

    /**
     * @return array{0: ObservatoryReportSource, 1: ObservatoryReporterRole}
     */
    private function writeOrigin(User $user, int $installationId): array
    {
        if ($user->hasRole(AssignableRoles::CLIENT_INSTALLATION_ADMIN)) {
            return [
                ObservatoryReportSource::Panel,
                $user->isSiteSupport($installationId)
                    ? ObservatoryReporterRole::Apoyo
                    : ObservatoryReporterRole::Rector,
            ];
        }

        return [ObservatoryReportSource::Api, ObservatoryReporterRole::Integracion];
    }

    /**
     * @param  array{company_id: ?int, client_id: ?int, installation_ids: ?list<int>}  $scope
     */
    private function assertEventInScope(ObservatoryEvent $event, array $scope): void
    {
        $event->loadMissing('client');

        if ($scope['client_id'] !== null && (int) $event->client_id !== $scope['client_id']) {
            abort(404);
        }
        if ($scope['company_id'] !== null && (int) $event->client?->security_company_id !== $scope['company_id']) {
            abort(404);
        }
        if ($scope['installation_ids'] !== null && ! in_array((int) $event->installation_id, $scope['installation_ids'], true)) {
            abort(404);
        }
    }

    /**
     * @param  array{company_id: ?int, client_id: ?int, installation_ids: ?list<int>}  $scope
     */
    private function assertInstallationInScope(int $installationId, array $scope): void
    {
        $site = Installation::query()->withoutGlobalScopes()->with('client')->whereKey($installationId)->first();
        if ($site === null) {
            abort(422, 'Elige un colegio de tu alcance.');
        }
        if ($scope['client_id'] !== null && (int) $site->client_id !== $scope['client_id']) {
            abort(403, 'Ese colegio no es de tu cliente.');
        }
        if ($scope['company_id'] !== null && (int) $site->client?->security_company_id !== $scope['company_id']) {
            abort(403, 'Ese colegio no es de tu empresa.');
        }
        if ($scope['installation_ids'] !== null && ! in_array($installationId, $scope['installation_ids'], true)) {
            abort(403, 'Esa instalación no está en tu alcance.');
        }
    }

    /**
     * @return array{search: string, from: ?string, to: ?string, status: string, client_id: ?int, installation_id: ?int}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::enum(ObservatoryEventStatus::class)],
            'client_id' => ['nullable', 'integer'],
            'installation_id' => ['nullable', 'integer'],
        ]);

        return [
            'search' => trim((string) ($validated['q'] ?? '')),
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => (string) ($validated['status'] ?? ''),
            'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            'installation_id' => isset($validated['installation_id']) ? (int) $validated['installation_id'] : null,
        ];
    }
}
