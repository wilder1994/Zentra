<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Resident\ResidentAppUrl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadsController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('client.app_users.manage'), 403);

        $client = Client::query()->findOrFail((int) $this->tenantContext->clientId());
        abort_unless((bool) $client->has_access, 403);

        return view('modules.client.downloads.index', [
            'client' => $client,
            'webUrl' => ResidentAppUrl::web(),
            'apkReady' => ResidentAppUrl::apkReady(),
            'apkUrl' => route('client.downloads.apk'),
        ]);
    }

    public function apk(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('client.app_users.manage'), 403);

        $client = Client::query()->findOrFail((int) $this->tenantContext->clientId());
        abort_unless((bool) $client->has_access, 403);

        $path = ResidentAppUrl::apkPath();
        abort_unless($path !== null, 404);

        return response()->download($path, 'zentra-residentes.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
