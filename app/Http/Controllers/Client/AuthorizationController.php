<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\AuthorizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreAuthorizationRequest;
use App\Models\StructureMember;
use App\Models\VisitorPreAuthorization;
use App\Repositories\StructureRepository;
use App\Repositories\VisitorPreAuthorizationRepository;
use App\Services\Structure\ImportAuthorizationsService;
use App\Services\Structure\ManageAuthorizationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

final class AuthorizationController extends Controller
{
    public const ZONES = ['Salón social', 'Piscina', 'Zona BBQ', 'Gimnasio', 'Cancha', 'Parque infantil', 'Administración', 'Portería'];

    public function __construct(
        private readonly VisitorPreAuthorizationRepository $authorizations,
        private readonly ManageAuthorizationService $service,
        private readonly ImportAuthorizationsService $importAuthorizationsService,
        private readonly StructureRepository $structures,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), VisitorPreAuthorizationRepository::TABS, true) ? $request->query('tab') : 'hoy';
        $clientId = (int) $this->tenantContext->clientId();
        $scope = $this->tenantContext->installationIds();

        return view('modules.client.authorizations.index', [
            'tab' => $tab,
            'authorizations' => $this->authorizations->paginate($clientId, $scope, $tab, $request->only('q', 'type')),
            'counts' => $this->authorizations->counts($clientId, $scope),
            'types' => AuthorizationType::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $picker = $this->structures->censusPickerData((int) $this->tenantContext->clientId(), $this->tenantContext->installationIds());

        return view('modules.client.authorizations.create', [
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'types' => AuthorizationType::options(),
            'multiDefaults' => collect(AuthorizationType::cases())->mapWithKeys(fn (AuthorizationType $t) => [$t->value => $t->defaultMultiEntry()])->all(),
            'zones' => self::ZONES,
            'preset' => $request->only('structure_id', 'installation_id', 'type'),
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $structureId = $request->integer('structure_id');
        $members = StructureMember::query()
            ->where('client_id', (int) $this->tenantContext->clientId())
            ->where('structure_id', $structureId)
            ->when($this->tenantContext->installationIds() !== null, fn ($q) => $q->whereHas(
                'structure',
                fn ($s) => $s->whereIn('installation_id', $this->tenantContext->installationIds()),
            ))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (StructureMember $m) => ['id' => $m->id, 'name' => trim($m->first_name.' '.$m->last_name)]);

        return response()->json(['members' => $members]);
    }

    public function store(StoreAuthorizationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $zone = $data['destination_mode'] === 'zone';

        $authorization = $this->service->create($request->user(), [
            ...$data,
            'structure_id' => $zone ? null : $data['structure_id'],
            'destination_text' => $zone ? $data['destination_text'] : null,
            'origin' => 'admin',
        ]);

        return redirect()
            ->route('client.authorizations.show', $authorization)
            ->with('success', 'Autorización creada. Comparte el QR con el visitante.');
    }

    public function show(VisitorPreAuthorization $authorization): View
    {
        $this->authorizeScope($authorization);
        $authorization->load(['structure', 'member', 'installation', 'creator', 'guests.visitor']);

        return view('modules.client.authorizations.show', [
            'authorization' => $authorization,
            'passUrl' => route('authorizations.pass', $authorization->qr_auth_token),
        ]);
    }

    public function cancel(Request $request, VisitorPreAuthorization $authorization): RedirectResponse
    {
        $this->authorizeScope($authorization);
        $this->service->cancel($request->user(), $authorization);

        return back()->with('success', 'Autorización cancelada.');
    }

    public function importForm(): View
    {
        return view('modules.client.authorizations.import');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $importer = $this->importAuthorizationsService->forClient((int) $this->tenantContext->clientId(), $request->user());
        Excel::import($importer, $request->file('file'));

        $message = "Importadas {$importer->importedCount()} autorizaciones.";
        $errors = $importer->errors();

        return redirect()
            ->route('client.authorizations.index')
            ->with(count($errors) > 0 ? 'warning' : 'success', count($errors) > 0
                ? $message.' Algunas filas tuvieron errores: '.implode(' ', array_slice($errors, 0, 3))
                : $message);
    }

    private function authorizeScope(VisitorPreAuthorization $authorization): void
    {
        $allowed = $this->tenantContext->installationIds();
        abort_if(
            (int) $authorization->client_id !== (int) $this->tenantContext->clientId()
            || ($allowed !== null && ! in_array((int) $authorization->installation_id, $allowed, true)),
            404,
        );
    }
}
