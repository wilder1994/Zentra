<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\Geo\GeoAddressData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyInstallationRequest;
use App\Models\Client;
use App\Models\Installation;
use App\Models\SupervisorPostModality;
use App\Services\Company\ManageClientInstallationService;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use App\Services\Ops\BuildSigBoardService;
use App\Support\Company\InstallationSiteAdmins;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CompanyInstallationController extends Controller
{
    public function __construct(
        private readonly ManageClientInstallationService $installations,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('company.installations.view'), 403);

        $companyId = $this->companyId($request);

        $clients = Client::query()
            ->withoutGlobalScopes()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('installations', fn ($q) => $q->withoutGlobalScopes()->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        $clientId = $request->integer('client_id') ?: null;
        $selectedClient = $clientId !== null ? $clients->firstWhere('id', $clientId) : null;
        if ($selectedClient === null) {
            $clientId = null;
        }

        return view('modules.company.installations.index', [
            'clients' => $clients,
            'selectedClient' => $selectedClient,
            'sigBoard' => app(BuildSigBoardService::class)->forCompany($companyId, $clientId),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('company.installations.manage'), 403);

        return view('modules.company.installations.create', $this->formData($request));
    }

    public function store(StoreCompanyInstallationRequest $request): RedirectResponse
    {
        $client = $request->client();
        abort_unless($client instanceof Client, 404);
        abort_unless($request->user()?->can('company.installations.manage'), 403);

        try {
            $installation = $this->installations->create($client, $this->payload($request));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('company.installations.show', $installation)
            ->with('success', 'Instalación creada.');
    }

    public function show(Request $request, Installation $installation): View
    {
        $this->assertCompany($request, $installation);
        abort_unless($request->user()?->can('company.installations.view'), 403);

        $installation->load([
            'client',
            'rector',
            'assignedAdmins',
            'supervisorPosts' => fn ($q) => $q->with(['employees.jobTitle'])->orderBy('name'),
        ]);

        return view('modules.company.installations.show', [
            'installation' => $installation,
            'maps' => [
                'api_key' => config('google-maps.api_key'),
                'zoom' => 17,
            ],
            'postModalities' => $this->postModalityOptions((int) $installation->client->security_company_id),
            'canManageTree' => $request->user()?->can('company.installations.manage') ?? false,
        ]);
    }

    public function edit(Request $request, Installation $installation): View
    {
        $this->assertCompany($request, $installation);
        abort_unless($request->user()?->can('company.installations.manage'), 403);

        return view('modules.company.installations.edit', array_merge(
            $this->formData($request),
            ['installation' => $installation->load(['client', 'rector'])],
        ));
    }

    public function update(StoreCompanyInstallationRequest $request, Installation $installation): RedirectResponse
    {
        $this->assertCompany($request, $installation);
        $client = $request->client();
        abort_unless($client instanceof Client && (int) $client->id === (int) $installation->client_id, 404);
        abort_unless($request->user()?->can('company.installations.manage'), 403);

        try {
            $this->installations->update($installation, $this->payload($request));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('company.installations.show', $installation)
            ->with('success', 'Instalación actualizada.');
    }

    /** @return array<string, mixed> */
    private function payload(StoreCompanyInstallationRequest $request): array
    {
        return [
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'kind' => $request->validated('kind'),
            'dane_code' => $request->validated('dane_code'),
            'commune' => $request->validated('commune'),
            'rector_user_id' => $request->validated('rector_user_id'),
            'is_client_site' => $request->boolean('is_client_site'),
            'is_active' => $request->boolean('is_active', true),
            'geo' => $request->boolean('is_client_site')
                ? null
                : GeoAddressData::fromValidated($request->validated()),
        ];
    }

    /** @return array{clients: Collection<int, Client>, siteAdmins: list<array{id: int, name: string, label: string, client_id: int}>} */
    private function formData(Request $request): array
    {
        $companyId = $this->companyId($request);

        $clients = Client::query()
            ->where('security_company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude']);

        return [
            'clients' => $clients,
            'siteAdmins' => InstallationSiteAdmins::optionsForClients($clients->pluck('id')),
        ];
    }

    private function assertCompany(Request $request, Installation $installation): void
    {
        abort_unless(
            (int) $installation->client?->security_company_id === $this->companyId($request),
            404
        );
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
}
