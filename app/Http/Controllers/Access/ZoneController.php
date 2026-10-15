<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreBookingRequest;
use App\Models\CommonZone;
use App\Models\CommonZoneBooking;
use App\Repositories\CommonZoneBookingRepository;
use App\Repositories\StructureRepository;
use App\Services\Access\ZoneBookingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

final class ZoneController extends Controller
{
    public function __construct(
        private readonly CommonZoneBookingRepository $bookings,
        private readonly ZoneBookingService $service,
        private readonly StructureRepository $structures,
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

    public function calendar(Request $request): View
    {
        $clientId = (int) $this->tenant->clientId();
        $scope = $this->tenant->installationIds();
        $zones = $this->bookings->zones($clientId, $scope, true);
        $zone = $zones->firstWhere('id', $request->integer('zone')) ?? $zones->first();
        $month = $this->month($request->query('month'));

        return view('modules.access.zones.calendar', [
            'zones' => $zones,
            'zone' => $zone,
            'month' => $month,
            'monthBookings' => $zone !== null ? $this->bookings->forMonth($zone, $month) : collect(),
            'pending' => $this->bookings->pending($clientId, $scope),
            'nodeOptions' => $this->structures->censusPickerData($clientId, $scope)['nodeOptions'],
        ]);
    }

    public function requestBooking(StoreBookingRequest $request): RedirectResponse
    {
        $zone = CommonZone::query()->findOrFail($request->integer('common_zone_id'));
        $booking = $this->service->request($request->user(), $zone, $request->validated(), 'porteria');

        return redirect()
            ->route('access.zones.calendar', ['zone' => $booking->common_zone_id, 'month' => $booking->date->format('Y-m')])
            ->with('success', 'Solicitud enviada: queda por aprobar por la administración.');
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

    private function month(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        return today()->startOfMonth();
    }
}
