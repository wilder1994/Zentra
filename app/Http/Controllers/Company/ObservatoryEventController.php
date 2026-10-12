<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Enums\ObservatoryEventStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ObservatoryEvent;
use App\Models\SecurityCompany;
use App\Services\Observatory\BuildObservatoryBoardService;
use App\Services\Observatory\BuildObservatoryMapService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\ExportObservatoryBoardService;
use App\Services\Observatory\PresentObservatoryLiveService;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ObservatoryEventController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $filters = $this->filters($request, $companyId);
        $board = app(BuildObservatoryBoardService::class);
        $clientId = $filters['client_id'];
        $siteIds = app(CaliComunaLayer::class)->scopeInstallationIds(
            $filters['comuna'],
            $companyId,
            $clientId,
            null,
        );

        $events = $board->scoped($companyId, $clientId, $siteIds, $filters['from'], $filters['to'])
            ->with(['client', 'installation', 'latestReport.reportType'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('installation', fn ($i) => $i->where('name', 'like', '%'.$filters['search'].'%')
                            ->orWhere('dane_code', 'like', '%'.$filters['search'].'%'))
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('opened_at')
            ->paginate(20)
            ->withQueryString();

        $clients = $this->clients($companyId);

        return view('modules.observatory.company.index', [
            'events' => $events,
            'search' => $filters['search'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'vista' => $filters['vista'],
            'grain' => $filters['grain'],
            'comuna' => $filters['comuna'],
            'comunas' => app(CaliComunaLayer::class)->catalog(),
            'filterClientId' => $clientId,
            'filterClients' => $clients,
            'shareClients' => $clients,
            'board' => $board->execute($companyId, $clientId, $siteIds, $filters['from'], $filters['to'], $filters['grain']),
            'map' => app(BuildObservatoryMapService::class)->execute(
                $companyId,
                $clientId,
                $siteIds,
                'company.observatory.events.show',
                $filters['comuna'],
            ),
        ]);
    }

    public function live(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $filters = $this->filters($request, $companyId);
        $board = app(BuildObservatoryBoardService::class);
        $clientId = $filters['client_id'];
        $siteIds = app(CaliComunaLayer::class)->scopeInstallationIds(
            $filters['comuna'],
            $companyId,
            $clientId,
            null,
        );

        $events = $board->scoped($companyId, $clientId, $siteIds, $filters['from'], $filters['to'])
            ->with(['client', 'installation', 'latestReport.reportType'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($inner) use ($filters) {
                    $inner->where('title', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('installation', fn ($i) => $i->where('name', 'like', '%'.$filters['search'].'%')
                            ->orWhere('dane_code', 'like', '%'.$filters['search'].'%'))
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('opened_at')
            ->paginate(20);

        return response()->json(app(PresentObservatoryLiveService::class)->execute(
            $board->execute($companyId, $clientId, $siteIds, $filters['from'], $filters['to'], $filters['grain']),
            app(BuildObservatoryMapService::class)->execute(
                $companyId,
                $clientId,
                $siteIds,
                'company.observatory.events.show',
                $filters['comuna'],
            ),
            $events,
            'company.observatory.events.show',
            true,
        ));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $filters = $this->filters($request, $companyId);
        $clientId = $filters['client_id'];
        $siteIds = app(CaliComunaLayer::class)->scopeInstallationIds(
            $filters['comuna'],
            $companyId,
            $clientId,
            null,
        );
        $board = app(BuildObservatoryBoardService::class)->execute(
            $companyId,
            $clientId,
            $siteIds,
            $filters['from'],
            $filters['to'],
            $filters['grain'],
        );
        $company = SecurityCompany::query()->findOrFail($companyId);
        $clientName = $clientId !== null
            ? Client::query()->whereKey($clientId)->value('name')
            : null;
        $export = app(ExportObservatoryBoardService::class);
        $file = $export->execute($board, [
            'scope' => $clientName ?: $company->displayName(),
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

    public function types(Request $request): View
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $clients = $this->clients($companyId);
        $clientId = (int) $request->integer('client_id');
        $client = $clientId > 0 ? $clients->firstWhere('id', $clientId) : null;

        if ($client instanceof Client) {
            app(EnsureObservatoryReportTypesService::class)->execute($client);
        }

        return view('modules.observatory.company.types', [
            'filterClients' => $clients,
            'filterClientId' => $client?->id,
            'types' => $client instanceof Client ? $client->observatoryReportTypes()->get() : collect(),
        ]);
    }

    public function show(Request $request, ObservatoryEvent $event): View
    {
        $event->load(['client', 'installation', 'reports.reportType', 'reports.reportedBy', 'statusLogs.user', 'closedBy']);
        $this->authorize('view', $event);

        return view('modules.observatory.company.show', [
            'event' => $event,
            'canUpdateStatus' => $request->user()?->can('update', $event) ?? false,
            'folioMap' => app(BuildObservatoryMapService::class)->forEvent($event),
        ]);
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

    /** @return array{search: string, from: ?string, to: ?string, status: string, vista: string, grain: string, client_id: ?int, comuna: string} */
    private function filters(Request $request, int $companyId): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::enum(ObservatoryEventStatus::class)],
            'vista' => ['nullable', 'string', Rule::in(['tablero', 'eventos'])],
            'grain' => ['nullable', 'string', Rule::in(['day', 'month', 'year'])],
            'comuna' => ['nullable', 'string', 'max:12'],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('security_company_id', $companyId)),
            ],
        ]);

        $clientId = isset($validated['client_id']) ? (int) $validated['client_id'] : 0;

        return [
            'search' => trim((string) ($validated['q'] ?? '')),
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'status' => (string) ($validated['status'] ?? ''),
            'vista' => (string) ($validated['vista'] ?? 'tablero'),
            'grain' => (string) ($validated['grain'] ?? 'day'),
            'comuna' => app(CaliComunaLayer::class)->normalize($validated['comuna'] ?? null),
            'client_id' => $clientId > 0 ? $clientId : null,
        ];
    }
}
