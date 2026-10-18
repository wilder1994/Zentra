<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\Ops\BuildSigBoardService;
use App\Support\Platform\ActingCompanyResolver;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SigBoardLiveController extends Controller
{
    public function company(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('company.installations.view') || $request->user()?->can('company.dashboard'), 403);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $clientId = $request->integer('client_id') ?: null;

        return response()->json(app(BuildSigBoardService::class)->forCompany($companyId, $clientId));
    }

    public function client(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('client.structures.manage') || $request->user()?->can('ops.sig.view'), 403);

        return response()->json(app(BuildSigBoardService::class)->forTenant(app(TenantContext::class)));
    }
}
