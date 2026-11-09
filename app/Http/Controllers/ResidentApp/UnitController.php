<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Http\Controllers\Controller;
use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class UnitController extends Controller
{
    public function __invoke(): View
    {
        /** @var StructureAppUser $appUser */
        $appUser = Auth::guard('resident')->user();
        $appUser->loadMissing(['member.structure.installation', 'member.memberType', 'client']);

        $structureId = (int) ($appUser->member?->structure_id ?? 0);

        $members = $structureId > 0
            ? StructureMember::query()
                ->with('memberType')
                ->where('client_id', (int) $appUser->client_id)
                ->where('structure_id', $structureId)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
            : collect();

        $vehicles = $structureId > 0
            ? Vehicle::query()
                ->where('client_id', (int) $appUser->client_id)
                ->where('structure_id', $structureId)
                ->where('is_visitor_vehicle', false)
                ->orderBy('plate')
                ->get()
            : collect();

        return view('resident-app.unit', [
            'appUser' => $appUser,
            'members' => $members,
            'vehicles' => $vehicles,
        ]);
    }
}
