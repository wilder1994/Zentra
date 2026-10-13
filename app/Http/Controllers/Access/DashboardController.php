<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Enums\AuthorizationStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Correspondence;
use App\Models\VisitorPreAuthorization;

class DashboardController extends Controller
{
    public function index()
    {
        $activeLogs = AccessLog::query()
            ->with(['visitor', 'structureMember.structure', 'resident', 'location', 'vehicle'])
            ->where('status', 'active')
            ->latest('entry_time')
            ->get()
            ->map(function (AccessLog $log) {
                $hoursInside = $log->entry_time->diffInHours(now());
                $log->hours_inside = $hoursInside;
                $log->alert_long_stay = $hoursInside >= (int) config('access.alerts.long_stay_hours');
                $log->person_name = $log->subjectName();
                $log->person_type = $log->movementLabel();
                $log->destination = $log->destinationLabel();

                return $log;
            });

        $pendingCorrespondence = Correspondence::query()->where('status', 'pending')->count();
        $pendingAuthorizations = VisitorPreAuthorization::query()
            ->where('status', AuthorizationStatus::Pending->value)
            ->where('valid_from', '<=', now()->endOfDay())
            ->where('valid_until', '>=', now())
            ->count();

        return view('modules.access.dashboard', [
            'peopleInside' => $activeLogs,
            'activeEntries' => $activeLogs->count(),
            'todayEntries' => AccessLog::query()->whereDate('entry_time', today())->count(),
            'pendingCorrespondence' => $pendingCorrespondence,
            'pendingAuthorizations' => $pendingAuthorizations,
        ]);
    }
}
