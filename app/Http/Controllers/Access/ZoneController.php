<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\CommonZoneBooking;
use App\Repositories\CommonZoneBookingRepository;
use App\Services\Access\ZoneBookingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ZoneController extends Controller
{
    public function __construct(
        private readonly CommonZoneBookingRepository $bookings,
        private readonly ZoneBookingService $service,
        private readonly TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), CommonZoneBookingRepository::TABS, true) ? $request->query('tab') : 'hoy';
        $clientId = (int) $this->tenant->clientId();
        $scope = $this->tenant->installationIds();

        return view('modules.access.zones.index', [
            'tab' => $tab,
            'bookings' => $this->bookings->paginate($clientId, $scope, $tab, $request->only('q', 'zone')),
            'counts' => $this->bookings->counts($clientId, $scope),
            'zones' => $this->bookings->zones($clientId, $scope, true),
        ]);
    }

    public function checkin(Request $request, CommonZoneBooking $booking): RedirectResponse
    {
        $this->service->checkIn($request->user(), $booking);

        return back()->with('success', "Uso habilitado: {$booking->zone?->name} · {$booking->unitLabel()}.");
    }

    public function complete(Request $request, CommonZoneBooking $booking): RedirectResponse
    {
        $this->service->complete($request->user(), $booking);

        return back()->with('success', 'Reserva finalizada.');
    }
}
