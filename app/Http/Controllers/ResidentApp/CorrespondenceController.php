<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Models\StructureAppUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class CorrespondenceController extends Controller
{
    public function index(): View
    {
        /** @var StructureAppUser $appUser */
        $appUser = Auth::guard('resident')->user();
        $appUser->loadMissing(['member.structure']);

        $structureId = (int) ($appUser->member?->structure_id ?? 0);
        $memberId = (int) ($appUser->member_id ?? 0);

        $items = Correspondence::query()
            ->where('client_id', (int) $appUser->client_id)
            ->whereNull('voided_at')
            ->where(function ($q) use ($structureId, $memberId): void {
                if ($structureId > 0) {
                    $q->where('structure_id', $structureId);
                }
                if ($memberId > 0) {
                    $q->orWhere('structure_member_id', $memberId);
                }
            })
            ->orderByDesc('received_at')
            ->limit(40)
            ->get();

        return view('resident-app.correspondence', [
            'appUser' => $appUser,
            'items' => $items,
        ]);
    }
}
