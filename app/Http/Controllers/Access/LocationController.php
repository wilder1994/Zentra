<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\Location;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class LocationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): View
    {
        $locations = Location::query()->with('installation')->latest()->paginate(15);

        return view('modules.access.locations.index', compact('locations'));
    }

    public function create(): View
    {
        $installations = Installation::query()->where('is_active', true)->orderBy('name')->get();

        return view('modules.access.locations.create', compact('installations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $clientId = $this->tenantContext->clientId();
        abort_if($clientId === null, 422, 'Seleccione un cliente activo.');

        $validated = $request->validate([
            'installation_id' => [
                'required',
                'integer',
                Rule::exists('installations', 'id')->where(fn ($q) => $q->where('client_id', $clientId)),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('locations', 'code')->where(fn ($q) => $q->where('client_id', $clientId)),
            ],
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geo_radius_m' => 'nullable|integer|min:10',
            'is_active' => 'boolean',
        ]);

        if (($validated['latitude'] ?? null) !== null xor ($validated['longitude'] ?? null) !== null) {
            return back()->withErrors(['geo' => 'Latitud y longitud deben ir juntas.'])->withInput();
        }

        Location::query()->create([
            ...$validated,
            'client_id' => $clientId,
            'type' => 'access_point',
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('access.locations.index')
            ->with('success', 'Punto de acceso creado correctamente.');
    }

    public function edit(Location $location): View
    {
        $installations = Installation::query()->where('is_active', true)->orderBy('name')->get();

        return view('modules.access.locations.edit', compact('location', 'installations'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'installation_id' => [
                'required',
                'integer',
                Rule::exists('installations', 'id')->where(fn ($q) => $q->where('client_id', $location->client_id)),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('locations', 'code')
                    ->where(fn ($q) => $q->where('client_id', $location->client_id))
                    ->ignore($location->id),
            ],
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geo_radius_m' => 'nullable|integer|min:10',
            'is_active' => 'boolean',
        ]);

        if (($validated['latitude'] ?? null) !== null xor ($validated['longitude'] ?? null) !== null) {
            return back()->withErrors(['geo' => 'Latitud y longitud deben ir juntas.'])->withInput();
        }

        $location->update([
            ...$validated,
            'type' => 'access_point',
            'is_active' => $request->boolean('is_active', $location->is_active),
        ]);

        return redirect()->route('access.locations.index')
            ->with('success', 'Punto de acceso actualizado correctamente.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('access.locations.index')
            ->with('success', 'Punto de acceso eliminado correctamente.');
    }
}
