<?php

declare(strict_types=1);

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use Illuminate\View\View;

final class CorrespondenceController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $correspondence = Correspondence::with('visitor', 'location')
            ->where('host_id', $user->id)
            ->latest('received_at')
            ->paginate(20);

        return view('modules.resident.correspondence.index', compact('correspondence'));
    }

    public function show(Correspondence $correspondence): View
    {
        if ($correspondence->host_id !== auth()->id()) {
            abort(403);
        }

        $correspondence->load('visitor', 'location', 'receiver', 'deliverer');

        return view('modules.resident.correspondence.show', compact('correspondence'));
    }
}
