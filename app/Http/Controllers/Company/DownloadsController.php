<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Support\Supervision\SupervisionAppUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('company.downloads.view'), 403);

        return view('modules.company.downloads.index', [
            'pwaUrl' => SupervisionAppUrl::pwa(),
            'apkReady' => SupervisionAppUrl::apkReady(),
        ]);
    }

    public function apk(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('company.downloads.view'), 403);
        $path = SupervisionAppUrl::apkPath();
        abort_unless($path !== null, 404);

        return response()->download($path, 'zentra-supervision.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
