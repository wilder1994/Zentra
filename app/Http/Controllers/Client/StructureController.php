<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domain\Structure\Data\CreateStructureData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreStructureRequest;
use App\Models\Client;
use App\Models\Structure;
use App\Repositories\StructureRepository;
use App\Services\Structure\CreateStructureService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StructureController extends Controller
{
    public function __construct(
        private readonly StructureRepository $structureRepository,
        private readonly CreateStructureService $createStructureService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Structure::class);

        $selectedId = $request->integer('installation_id');
        if ($selectedId > 0 && $this->tenantContext->allowsInstallation($selectedId)) {
            return redirect()->route('client.installations.show', $selectedId);
        }

        return redirect()->route('client.installations.index');
    }

    public function store(StoreStructureRequest $request): RedirectResponse
    {
        $clientId = (int) $this->tenantContext->clientId();
        $installationId = (int) $request->validated('installation_id');

        $this->createStructureService->execute(new CreateStructureData(
            clientId: $clientId,
            installationId: $installationId,
            parentId: $request->validated('parent_id') !== null
                ? (int) $request->validated('parent_id')
                : null,
            name: $request->validated('name'),
            maxOccupancy: (int) $request->validated('max_occupancy', 0),
            isActive: $request->boolean('is_active', true),
        ));

        return redirect()
            ->route('client.installations.show', $installationId)
            ->with('success', 'Agregado correctamente a la estructura.');
    }

    public function show(Structure $structure): View
    {
        $this->authorize('view', $structure);

        $structure->load(['members.memberType', 'pets', 'vehicles', 'parent', 'structureType', 'installation']);

        return view('modules.client.structures.show', compact('structure'));
    }
}
