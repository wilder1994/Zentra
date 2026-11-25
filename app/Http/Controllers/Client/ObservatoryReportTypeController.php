<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Observatory\StoreObservatoryReportTypeRequest;
use App\Models\Client;
use App\Models\ObservatoryReportType;
use App\Models\RiskModelParameter;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\ManageObservatoryReportTypesService;
use App\Support\Auth\AssignableRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ObservatoryReportTypeController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EnsureObservatoryReportTypesService $ensure,
        private readonly ManageObservatoryReportTypesService $types,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\ObservatoryEvent::class);
        $client = $this->client();
        $this->ensure->execute($client);

        return view('modules.observatory.client.types', [
            'types' => $client->observatoryReportTypes()->get(),
            'nextColor' => $this->ensure->nextColor($client),
            'canEdit' => $this->canEdit($request),
            'parameters' => RiskModelParameter::forCompany((int) $client->security_company_id),
            'vista' => 'tipos',
        ]);
    }

    public function store(StoreObservatoryReportTypeRequest $request): RedirectResponse
    {
        $this->assertEditor($request);
        $client = $this->client();

        try {
            $this->types->create($client, $request->validated(), $request->boolean('is_active', true));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('success', 'Registro del catálogo endógeno creado. Lo ven todos los clientes con Observatorio.');
    }

    public function update(StoreObservatoryReportTypeRequest $request, ObservatoryReportType $type): RedirectResponse
    {
        $this->assertEditor($request);
        $this->assertOwned($type);

        try {
            $this->types->update($type, $request->validated(), $request->boolean('is_active', true));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Catálogo endógeno actualizado.');
    }

    public function destroy(Request $request, ObservatoryReportType $type): RedirectResponse
    {
        $this->assertEditor($request);
        $this->assertOwned($type);

        try {
            $this->types->delete($type);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Registro del catálogo endógeno eliminado.');
    }

    private function client(): Client
    {
        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);

        return Client::query()->findOrFail($clientId);
    }

    private function assertOwned(ObservatoryReportType $type): void
    {
        $client = $this->client();
        abort_unless((int) $type->security_company_id === (int) $client->security_company_id, 404);
    }

    private function canEdit(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $user->hasRole(AssignableRoles::CLIENT_ADMIN)
            && $user->can('observatory.events.update');
    }

    private function assertEditor(Request $request): void
    {
        $this->authorize('viewAny', \App\Models\ObservatoryEvent::class);
        abort_unless($this->canEdit($request), 403);
    }
}
