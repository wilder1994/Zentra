<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Http\Controllers\Controller;
use App\Models\Blocklist;
use App\Models\StructureAppUser;
use App\Support\Resident\ResidentTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class BlocklistController extends Controller
{
    public function __invoke(TenantContext $tenant): View
    {
        /** @var StructureAppUser $appUser */
        $appUser = Auth::guard('resident')->user();
        ResidentTenant::bind($appUser, $tenant);

        $rows = Blocklist::query()
            ->where('client_id', (int) $appUser->client_id)
            ->active()
            ->orderByDesc('blocked_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return view('resident-app.blocklist', [
            'appUser' => $appUser,
            'entries' => $rows,
        ]);
    }
}
