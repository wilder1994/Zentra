<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\Tenant\Data\CreateClientData;
use App\Enums\PartyType;
use App\Exports\ClientImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\PreviewClientImportRequest;
use App\Http\Requests\Company\StoreClientRequest;
use App\Http\Requests\Company\UpdateClientModulesRequest;
use App\Http\Requests\Company\UpdateClientRequest;
use App\Models\Client;
use App\Models\IdentityDocumentType;
use App\Models\Installation;
use App\Models\Location;
use App\Models\StructureType;
use App\Models\SupervisorPostModality;
use App\Repositories\ClientRepository;
use App\Services\Company\CommitClientImportService;
use App\Services\Company\ManageArchiveService;
use App\Services\Company\PreviewClientImportService;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use App\Services\Tenant\BuildClientExpedienteService;
use App\Services\Tenant\CreateClientService;
use App\Services\Tenant\UpdateClientService;
use App\Support\Company\CompanyOperateContext;
use App\Support\Company\InstallationSiteAdmins;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ClientController extends Controller
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly CreateClientService $createClientService,
        private readonly UpdateClientService $updateClientService,
        private readonly BuildClientExpedienteService $buildClientExpedienteService,
        private readonly PreviewClientImportService $previewClientImportService,
        private readonly CommitClientImportService $commitClientImportService,
        private readonly ManageArchiveService $archives,
    ) {}

    public function index(Request $request): View
    {
        $operateMode = $request->query('modo') === 'operar';
        $user = $request->user();
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();

        if ($operateMode) {
            abort_unless($user?->can('access.dashboard'), 403);
            if (! in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }
        } else {
            $this->authorize('viewAny', Client::class);
            if (! in_array($status, ['active', 'inactive'], true)) {
                $status = 'all';
            }
        }

        if ($user->hasRole('super-admin')) {
            $companyId = app(ActingCompanyResolver::class)->id($user);
            abort_unless($companyId !== null || $operateMode, 403, 'Use el panel de plataforma para gestionar empresas.');

            if ($companyId !== null) {
                if ($operateMode && $status === 'all') {
                    $status = 'active';
                }

                $clients = $this->clientRepository->paginateForCompany(
                    $companyId,
                    15,
                    $search !== '' ? $search : null,
                    $status !== 'all' ? $status : null,
                    $operateMode,
                );
                $metrics = $this->clientRepository->metricsForCompany($companyId);

                return view('modules.company.clients.index', compact(
                    'clients',
                    'metrics',
                    'search',
                    'status',
                    'operateMode',
                ));
            }

            $clients = $this->clientRepository->paginateOperableForUser(
                $user,
                15,
                $search !== '' ? $search : null,
                $status,
            );

            return view('modules.company.clients.index', [
                'clients' => $clients,
                'metrics' => null,
                'search' => $search,
                'status' => $status,
                'operateMode' => true,
            ]);
        }

        if ($user->hasAnyRole(['company-admin', 'colaborador']) && $user->security_company_id) {
            $companyId = (int) $user->security_company_id;

            if ($operateMode && $status === 'all') {
                $status = 'active';
            }

            $clients = $this->clientRepository->paginateForCompany(
                $companyId,
                15,
                $search !== '' ? $search : null,
                $status !== 'all' ? $status : null,
                $operateMode,
            );
            $metrics = $this->clientRepository->metricsForCompany($companyId);

            return view('modules.company.clients.index', compact(
                'clients',
                'metrics',
                'search',
                'status',
            ) + ['operateMode' => $operateMode]);
        }

        if ($operateMode) {
            $clients = $this->clientRepository->paginateOperableForUser(
                $user,
                15,
                $search !== '' ? $search : null,
                $status,
            );

            return view('modules.company.clients.index', [
                'clients' => $clients,
                'metrics' => null,
                'search' => $search,
                'status' => $status,
                'operateMode' => true,
            ]);
        }

        abort(403);
    }

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $this->authorize('create', Client::class);

        return Excel::download(new ClientImportTemplateExport, 'formato-clientes-zentra.xlsx');
    }

    public function storeImportPreview(PreviewClientImportRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        try {
            $preview = $request->file('file') !== null
                ? $this->previewClientImportService->previewFile($request->file('file'), $companyId)
                : $this->previewClientImportService->previewPaste((string) $request->input('paste'), $companyId);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('company.clients.index')
                ->with('error', $e->getMessage());
        }

        $this->previewClientImportService->put($companyId, (int) $request->user()->id, $preview);

        return redirect()->route('company.clients.import.preview');
    }

    public function showImportPreview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Client::class);
        $preview = $this->previewClientImportService->get($this->companyId($request), (int) $request->user()->id);

        if ($preview === null) {
            return redirect()
                ->route('company.clients.index')
                ->with('error', 'No hay una revisión vigente. Vuelve a cargar el archivo.');
        }

        return view('modules.company.clients.import-preview', compact('preview'));
    }

    public function commitImport(Request $request): RedirectResponse
    {
        $this->authorize('create', Client::class);
        try {
            $count = $this->commitClientImportService->execute(
                $this->companyId($request),
                (int) $request->user()->id,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.clients.import.preview')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo cargar.');
        }

        return redirect()
            ->route('company.clients.index')
            ->with('success', $count === 1 ? '1 cliente cargado.' : $count.' clientes cargados.');
    }

    public function cancelImport(Request $request): RedirectResponse
    {
        $this->authorize('create', Client::class);
        $this->previewClientImportService->forget($this->companyId($request), (int) $request->user()->id);

        return redirect()->route('company.clients.index');
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Client::class);

        $companyId = $this->companyId($request);
        $metrics = $this->clientRepository->metricsForCompany($companyId);

        $documentTypes = IdentityDocumentType::optionsForSelect();
        $structureTypes = StructureType::optionsForSelect($companyId);

        return view('modules.company.clients.create', compact('metrics', 'documentTypes', 'structureTypes'));
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $client = $this->createClientService->execute(new CreateClientData(
            securityCompanyId: $companyId,
            name: $request->validated('name'),
            partyType: PartyType::from((string) $request->validated('party_type')),
            legalName: $request->validated('legal_name'),
            documentType: $request->validated('document_type'),
            taxId: $request->validated('tax_id'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),
            representativeName: $request->validated('representative_name'),
            representativeEmail: $request->validated('representative_email'),
            structureTypeId: (int) $request->validated('structure_type_id'),
            address: $request->validated('address'),
            city: $request->validated('city'),
            department: $request->validated('department'),
            latitude: $request->filled('latitude') ? (float) $request->validated('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->validated('longitude') : null,
            isActive: $request->boolean('is_active', true),
            hasAccess: $request->boolean('has_access'),
            hasSupervision: $request->boolean('has_supervision'),
            showPersonnelFolders: $request->boolean('has_access') && $request->boolean('show_personnel_folders'),
            serviceStartedAt: $request->validated('service_started_at'),
            hasObservatory: $request->boolean('has_observatory'),
        ));

        return redirect()
            ->route('company.clients.show', $client)
            ->with('success', "Cliente «{$client->name}» creado correctamente.");
    }

    public function show(Request $request, Client $client): View
    {
        $this->authorize('view', $client);
        $this->assertCompanyOwnership($request, $client);

        $client->load(['securityCompany']);
        $vista = $this->resolveClientVista($request, $client);
        $expediente = $vista === 'resumen'
            ? $this->buildClientExpedienteService->execute($client)
            : null;

        $installations = in_array($vista, ['cliente', 'sitio', 'puertas'], true)
            ? $client->installations()
                ->with($vista === 'cliente' ? [] : [
                    'locations' => fn ($q) => $q->orderBy('code'),
                    'supervisorPosts' => fn ($q) => $q->with(['employees.jobTitle'])->orderBy('name'),
                ])
                ->orderByDesc('is_client_site')
                ->orderBy('name')
                ->get()
            : collect();

        return view('modules.company.clients.show', [
            'client' => $client,
            'vista' => $vista,
            'expediente' => $expediente,
            'installations' => $installations,
            'installationsCount' => $vista === 'resumen' ? $client->installations()->count() : $installations->count(),
            'postModalities' => $this->postModalityOptions((int) $client->security_company_id),
            'canManageTree' => $request->user()->can('update', $client),
            'canOperate' => $client->has_access && $request->user()->can('operate', $client),
            'canUpdate' => $request->user()->can('update', $client),
            'canOperateClientPanel' => $client->has_access
                && $request->user()->can('client.structures.manage')
                && $request->user()->can('operate', $client),
            'siteAdmins' => InstallationSiteAdmins::optionsForClients([$client->id]),
        ]);
    }

    public function edit(Request $request, Client $client): View
    {
        $this->authorize('update', $client);
        $this->assertCompanyOwnership($request, $client);

        $client->load('securityCompany');
        $metrics = $this->clientRepository->metricsForCompany((int) $client->security_company_id);

        return view('modules.company.clients.edit', [
            'client' => $client,
            'metrics' => $metrics,
            'documentTypes' => IdentityDocumentType::optionsForSelect(),
            'structureTypes' => StructureType::optionsForSelect((int) $client->security_company_id),
            'canOperate' => $client->has_access && $request->user()->can('operate', $client),
            'canUpdate' => true,
            'canOperateClientPanel' => $client->has_access
                && $request->user()->can('client.structures.manage')
                && $request->user()->can('operate', $client),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->assertCompanyOwnership($request, $client);

        $this->updateClientService->execute($client, $request->validated());

        return redirect()
            ->route('company.clients.show', $client)
            ->with('success', 'Cliente actualizado.');
    }

    public function archive(Request $request, Client $client): RedirectResponse
    {
        return $this->lifecycle($request, $client, fn () => $this->archives->archiveClient($client), 'Cliente archivado: sus instalaciones y puestos liberaron cupo. Puedes restaurarlo cuando haya cupo.');
    }

    public function restore(Request $request, Client $client): RedirectResponse
    {
        return $this->lifecycle($request, $client, fn () => $this->archives->restoreClient($client), 'Cliente restaurado con sus instalaciones y puestos.');
    }

    public function release(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $this->assertCompanyOwnership($request, $client);

        try {
            $this->archives->releaseClient($client);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.clients.show', $client)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('company.clients.index')
            ->with('success', "Cliente «{$client->name}» dado de baja. Sus datos se conservan según la política de retención.");
    }

    private function lifecycle(Request $request, Client $client, \Closure $action, string $message): RedirectResponse
    {
        $this->authorize('update', $client);
        $this->assertCompanyOwnership($request, $client);

        try {
            $action();
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.clients.show', $client)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('company.clients.show', $client)
            ->with('success', $message);
    }

    public function activate(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('operate', $client);
        abort_unless($client->has_access, 403);

        $hasDoors = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereNotNull('installation_id')
            ->exists();

        if (! $hasDoors) {
            return redirect()
                ->route('company.clients.show', $client)
                ->with('error', 'Este cliente no tiene puertas. Sin puertas no hay portería que operar.');
        }

        $request->session()->put(config('tenancy.session.active_client_key'), $client->id);
        CompanyOperateContext::enter((int) $client->id, CompanyOperateContext::MODE_PORTERIA);

        return redirect()
            ->route('access.dashboard')
            ->with('success', "Operando portería en: {$client->name}");
    }

    public function operateClient(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('operate', $client);
        abort_unless($client->has_access, 403);
        abort_unless($request->user()?->can('client.structures.manage'), 403);

        $request->session()->put(config('tenancy.session.active_client_key'), $client->id);
        CompanyOperateContext::enter((int) $client->id, CompanyOperateContext::MODE_CLIENTE);

        return redirect()
            ->route('client.dashboard')
            ->with('success', "Operando panel del cliente: {$client->name}");
    }

    public function operateInstallation(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('operate', $client);
        abort_unless($client->has_access, 403);
        abort_unless($request->user()?->can('client.structures.manage'), 403);

        $installationId = (int) $request->input('installation_id');
        if ($installationId < 1) {
            $only = $client->installations()->orderBy('name')->limit(2)->pluck('id');
            abort_unless($only->count() === 1, 422, 'Elige una instalación.');
            $installationId = (int) $only->first();
        }

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->whereKey($installationId)
            ->firstOrFail();

        $request->session()->put(config('tenancy.session.active_client_key'), $client->id);
        CompanyOperateContext::enter((int) $client->id, CompanyOperateContext::MODE_CLIENTE, (int) $installation->id);

        return redirect()
            ->route('client.dashboard')
            ->with('success', "Operando instalación: {$installation->name}");
    }

    public function updateServiceStart(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);
        $this->assertCompanyOwnership($request, $client);

        $validated = $request->validate([
            'service_started_at' => ['nullable', 'date'],
        ]);
        $client->update(['service_started_at' => $validated['service_started_at'] ?? null]);

        return redirect()
            ->route('company.clients.show', $client)
            ->with('success', 'Inicio de servicio actualizado.');
    }

    public function exitOperate(Request $request): RedirectResponse
    {
        $clientId = CompanyOperateContext::clientId();
        abort_unless($clientId !== null, 404);

        $client = Client::query()->findOrFail($clientId);
        $this->authorize('view', $client);

        CompanyOperateContext::exit();

        return redirect()
            ->route('company.clients.show', $client)
            ->with('success', 'Volviste al expediente del cliente.');
    }

    public function updateModules(UpdateClientModulesRequest $request, Client $client): RedirectResponse
    {
        $this->assertCompanyOwnership($request, $client);

        $client->update(['panel_modules' => $request->modules()]);

        return redirect()
            ->route('company.clients.show', $client)
            ->with('success', 'Módulos del panel cliente actualizados.');
    }

    private function resolveClientVista(Request $request, Client $client): string
    {
        $vista = $request->string('vista')->toString();
        if (in_array($vista, ['accesos', 'supervision'], true)) {
            $vista = 'sitio';
        }

        $allowed = ['cliente'];
        if (! $client->isCatalogOnly()) {
            $allowed[] = 'sitio';
        }
        if ($client->has_access) {
            $allowed[] = 'puertas';
            $allowed[] = 'resumen';
        }

        return in_array($vista, $allowed, true) ? $vista : 'cliente';
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    /** @return array<int, string> */
    private function postModalityOptions(int $companyId): array
    {
        app(SeedSupervisorIntakeDefaultsService::class)->execute($companyId);

        return SupervisorPostModality::optionsForCompany($companyId);
    }

    private function assertCompanyOwnership(Request $request, Client $client): void
    {
        if ($request->user()->hasRole('super-admin')) {
            return;
        }

        abort_unless(
            (int) $request->user()->security_company_id === (int) $client->security_company_id,
            403
        );
    }
}
