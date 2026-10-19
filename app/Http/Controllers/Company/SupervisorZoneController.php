<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorZone;
use App\Services\Company\AssignClientZonesService;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SupervisorZoneController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
        private readonly AssignClientZonesService $assignments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupervisorZone::class);
        $companyId = $this->companyId($request);
        $this->catalog->ensureDefaults($companyId);

        $zones = SupervisorZone::query()
            ->where('security_company_id', $companyId)
            ->withCount(['clients', 'sharedClients'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('modules.company.supervision-catalogs.zones', [
            'zones' => $zones,
            'clientRows' => $this->assignments->rows($companyId),
            'unassigned' => $this->assignments->unassignedCount($companyId),
            'canManage' => (bool) $request->user()?->can('company.settings.manage'),
        ]);
    }

    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createZone($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-zones.index')->with('success', 'Zona creada.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorZone $zone): RedirectResponse
    {
        abort_unless((int) $zone->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateZone($zone, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-zones.index')->with('success', 'Zona actualizada.');
    }

    public function destroy(Request $request, SupervisorZone $zone): RedirectResponse
    {
        abort_unless((int) $zone->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteZone($zone);

        return redirect()->route('company.supervision-zones.index')->with('success', 'Zona eliminada.');
    }

    public function assign(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('company.settings.manage'), 403);
        $data = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:2000'],
            'changes.*.client_id' => ['required', 'integer'],
            'changes.*.zone_id' => ['nullable', 'integer'],
            'changes.*.shared' => ['nullable', 'array'],
            'changes.*.shared.*' => ['integer'],
        ]);

        $companyId = $this->companyId($request);
        $updated = $this->assignments->apply($companyId, array_map(fn (array $row) => [
            'client_id' => (int) $row['client_id'],
            'zone_id' => isset($row['zone_id']) ? (int) $row['zone_id'] : null,
            'shared' => array_map('intval', $row['shared'] ?? []),
        ], $data['changes']));

        return response()->json([
            'updated' => $updated,
            'unassigned' => $this->assignments->unassignedCount($companyId),
            'message' => $updated === 1 ? '1 cliente actualizado.' : $updated.' clientes actualizados.',
        ]);
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
