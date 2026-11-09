<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Installation;
use App\Repositories\StructureRepository;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InstallationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StructureRepository $structureRepository,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\Structure::class);

        $clientId = (int) $this->tenantContext->clientId();
        $scoped = $this->scopedQuery($clientId)->orderBy('name')->get(['id', 'name']);

        if ($scoped->count() === 1) {
            return redirect()->route('client.installations.show', $scoped->first()->id);
        }

        $search = $request->string('q')->trim()->toString();

        $rows = $this->scopedQuery($clientId)
            ->with(['rector', 'assignedAdmins'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%')
                        ->orWhere('dane_code', 'like', '%'.$search.'%')
                        ->orWhere('commune', 'like', '%'.$search.'%')
                        ->orWhereHas('assignedAdmins', fn ($u) => $u->where('users.name', 'like', '%'.$search.'%')
                            ->orWhere('users.job_title', 'like', '%'.$search.'%'))
                        ->orWhereHas('rector', fn ($r) => $r->where('name', 'like', '%'.$search.'%')
                            ->orWhere('job_title', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('modules.client.installations.index', [
            'installations' => $rows,
            'search' => $search,
        ]);
    }

    public function show(Request $request, Installation $installation): View
    {
        $this->authorize('viewAny', \App\Models\Structure::class);
        $this->assertVisible($installation);

        $clientId = (int) $this->tenantContext->clientId();
        $client = Client::query()->with('structureType')->findOrFail($clientId);
        $search = $request->string('q')->trim()->toString();

        $tree = $this->structureRepository->treeForInstallation($clientId, (int) $installation->id, $search !== '' ? $search : null);
        $census = $this->structureRepository->censusCounts($clientId);
        $parentOptions = $this->structureRepository->parentOptionsForInstallation($clientId, (int) $installation->id);
        $hasMultipleSites = $this->scopedQuery($clientId)->count() > 1;

        return view('modules.client.installations.show', [
            'client' => $client,
            'installation' => $installation,
            'tree' => $tree,
            'census' => $census,
            'parentOptions' => $parentOptions,
            'search' => $search,
            'hasMultipleSites' => $hasMultipleSites,
        ]);
    }

    private function scopedQuery(int $clientId)
    {
        $query = Installation::query()
            ->where('client_id', $clientId)
            ->where('is_active', true);

        $allowedIds = $this->tenantContext->installationIds();
        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }

        return $query;
    }

    private function assertVisible(Installation $installation): void
    {
        $clientId = (int) $this->tenantContext->clientId();
        abort_unless((int) $installation->client_id === $clientId, 404);
        abort_unless($this->tenantContext->allowsInstallation((int) $installation->id), 403);
    }
}
