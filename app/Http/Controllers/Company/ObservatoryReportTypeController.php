<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Observatory\StoreObservatoryReportTypeRequest;
use App\Models\Client;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReportType;
use App\Services\Observatory\ManageObservatoryReportTypesService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Tipos del Observatorio por cliente desde el panel empresa (permiso «Gestionar» del módulo). */
final class ObservatoryReportTypeController extends Controller
{
    public function __construct(
        private readonly ManageObservatoryReportTypesService $types,
    ) {}

    public function store(StoreObservatoryReportTypeRequest $request, Client $client): RedirectResponse
    {
        $this->assertClient($request, $client);

        try {
            $this->types->create($client, $request->validated(), $request->boolean('is_active', true));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('success', 'Tipo creado. Ya aparece en el link y en el mapa.');
    }

    public function update(StoreObservatoryReportTypeRequest $request, ObservatoryReportType $type): RedirectResponse
    {
        $this->assertClient($request, $this->clientOf($type));

        try {
            $this->types->update($type, $request->validated(), $request->boolean('is_active', true));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Tipo actualizado.');
    }

    public function destroy(Request $request, ObservatoryReportType $type): RedirectResponse
    {
        $this->assertClient($request, $this->clientOf($type));

        try {
            $this->types->delete($type);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Tipo eliminado.');
    }

    public function copy(Request $request, Client $client): RedirectResponse
    {
        $this->assertClient($request, $client);
        $companyId = $this->companyId($request);

        $validated = $request->validate([
            'client_ids' => ['required', 'array', 'min:1'],
            'client_ids.*' => ['integer'],
        ], ['client_ids.required' => 'Elige al menos un cliente.']);

        $targets = Client::query()
            ->where('security_company_id', $companyId)
            ->where('has_observatory', true)
            ->whereKey($validated['client_ids'])
            ->whereKeyNot($client->id)
            ->get();
        $created = $this->types->copy($client, $targets);

        return back()->with('success', $created === 0
            ? 'Los clientes elegidos ya tenían todos estos tipos.'
            : "Se crearon {$created} tipos en {$targets->count()} ".($targets->count() === 1 ? 'cliente' : 'clientes').'.');
    }

    private function clientOf(ObservatoryReportType $type): Client
    {
        return Client::query()->findOrFail($type->client_id);
    }

    private function assertClient(Request $request, Client $client): void
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        abort_unless($request->user()?->can('observatory.events.update'), 403);
        abort_unless((int) $client->security_company_id === $this->companyId($request), 404);
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
