<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Repositories\StructureMemberRepository;
use App\Repositories\StructurePetRepository;
use App\Repositories\StructureRepository;
use App\Repositories\StructureVehicleRepository;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PorteriaDirectoryController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly StructureRepository $structures,
        private readonly StructureMemberRepository $members,
        private readonly StructureVehicleRepository $vehicles,
        private readonly StructurePetRepository $pets,
    ) {}

    public function people(Request $request): View
    {
        $clientId = (int) $this->tenant->clientId();
        $allowed = $this->tenant->installationIds();
        $picker = $this->structures->censusPickerData($clientId, $allowed);
        $installationId = $request->integer('installation_id') ?: null;
        $structureId = $request->integer('structure_id') ?: null;

        return view('modules.access.directory.people', [
            'members' => $this->members->paginateForClient(
                $clientId,
                $request->string('q')->toString() ?: null,
                $structureId,
                $installationId,
                $allowed,
            ),
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'installationId' => $installationId,
            'structureId' => $structureId,
        ]);
    }

    public function vehicles(Request $request): View
    {
        $clientId = (int) $this->tenant->clientId();
        $allowed = $this->tenant->installationIds();
        $picker = $this->structures->censusPickerData($clientId, $allowed);
        $installationId = $request->integer('installation_id') ?: null;
        $structureId = $request->integer('structure_id') ?: null;

        return view('modules.access.directory.vehicles', [
            'vehicles' => $this->vehicles->paginateForClient(
                $clientId,
                $request->string('q')->toString() ?: null,
                $structureId,
                $installationId,
                $allowed,
            ),
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'installationId' => $installationId,
            'structureId' => $structureId,
        ]);
    }

    public function pets(Request $request): View
    {
        $clientId = (int) $this->tenant->clientId();
        $allowed = $this->tenant->installationIds();
        $picker = $this->structures->censusPickerData($clientId, $allowed);
        $installationId = $request->integer('installation_id') ?: null;
        $structureId = $request->integer('structure_id') ?: null;

        return view('modules.access.directory.pets', [
            'pets' => $this->pets->paginateForClient(
                $clientId,
                $request->string('q')->toString() ?: null,
                $structureId,
                $installationId,
                $allowed,
            ),
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'installationId' => $installationId,
            'structureId' => $structureId,
        ]);
    }

    public function sites(): View
    {
        $clientId = (int) $this->tenant->clientId();
        $allowed = $this->tenant->installationIds();
        $sites = Installation::query()
            ->withCount('structures')
            ->where('client_id', $clientId)
            ->when(is_array($allowed) && $allowed !== [], fn ($q) => $q->whereIn('id', $allowed))
            ->orderBy('name')
            ->get();

        $trees = [];
        foreach ($sites as $site) {
            $trees[(int) $site->id] = $this->structures->treeForInstallation($clientId, (int) $site->id);
        }

        return view('modules.access.directory.sites', [
            'sites' => $sites,
            'trees' => $trees,
        ]);
    }
}
