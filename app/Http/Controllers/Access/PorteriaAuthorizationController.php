<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Enums\AuthorizationType;
use App\Http\Controllers\Controller;
use App\Repositories\VisitorPreAuthorizationRepository;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PorteriaAuthorizationController extends Controller
{
    public function index(Request $request, TenantContext $tenant, VisitorPreAuthorizationRepository $repo): View
    {
        $tab = in_array($request->query('tab'), VisitorPreAuthorizationRepository::TABS, true) ? $request->query('tab') : 'hoy';
        $clientId = (int) $tenant->clientId();

        return view('modules.access.authorizations.index', [
            'tab' => $tab,
            'authorizations' => $repo->paginate($clientId, $tenant->installationIds(), $tab, $request->only('q', 'type')),
            'counts' => $repo->counts($clientId, $tenant->installationIds()),
            'types' => AuthorizationType::options(),
        ]);
    }
}
