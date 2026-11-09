<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\SecurityCompany;
use App\Support\Catalog\CompanyEntitlements;
use App\Support\Platform\ActingCompanyResolver;
use App\Support\Resident\ResidentAppUrl;
use App\Support\Supervision\SupervisionAppUrl;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('company.downloads.view'), 403);

        $company = SecurityCompany::query()->findOrFail(
            app(ActingCompanyResolver::class)->requireId($request->user())
        );
        $entitlements = CompanyEntitlements::for($company);
        abort_unless($entitlements->hasAccess() || $entitlements->hasSupervision(), 403);

        return view('modules.company.downloads.index', [
            'showSupervision' => $entitlements->hasSupervision(),
            'showResident' => $entitlements->hasAccess(),
            'pwaUrl' => SupervisionAppUrl::pwa(),
            'apkReady' => SupervisionAppUrl::apkReady(),
            'residentWebUrl' => ResidentAppUrl::web(),
            'residentApkReady' => ResidentAppUrl::apkReady(),
        ]);
    }

    public function apk(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('company.downloads.view'), 403);

        $company = SecurityCompany::query()->findOrFail(
            app(ActingCompanyResolver::class)->requireId($request->user())
        );
        abort_unless(CompanyEntitlements::for($company)->hasSupervision(), 403);

        $path = SupervisionAppUrl::apkPath();
        abort_unless($path !== null, 404);

        return response()->download($path, 'zentra-supervision.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    public function residentApk(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('company.downloads.view'), 403);

        $company = SecurityCompany::query()->findOrFail(
            app(ActingCompanyResolver::class)->requireId($request->user())
        );
        abort_unless(CompanyEntitlements::for($company)->hasAccess(), 403);

        $path = ResidentAppUrl::apkPath();
        abort_unless($path !== null, 404);

        return response()->download($path, 'zentra-residentes.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
