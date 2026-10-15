<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreBookingRequest;
use App\Http\Requests\Client\StoreCommonZoneRequest;
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

final class BookingController extends Controller
{
    public function __construct(
        private readonly CommonZoneBookingRepository $bookings,
        private readonly ZoneBookingService $service,
        private readonly StructureRepository $structures,
        private readonly TenantContext $tenant,
    ) {}

    public function index(Request $request): View
    {
        $clientId = (int) $this->tenant->clientId();
        $scope = $this->tenant->installationIds();
        $zones = $this->bookings->zones($clientId, $scope, true);
        $zone = $zones->firstWhere('id', $request->integer('zone')) ?? $zones->first();
        $month = $this->month($request->query('month'));
        $picker = $this->structures->censusPickerData($clientId, $scope);

        return view('modules.client.bookings.index', [
            'zones' => $zones,
            'zone' => $zone,
            'month' => $month,
            'monthBookings' => $zone !== null ? $this->bookings->forMonth($zone, $month) : collect(),
            'pending' => $this->bookings->pending($clientId, $scope),
            'nodeOptions' => $picker['nodeOptions'],
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $zone = CommonZone::query()->findOrFail($request->integer('common_zone_id'));
        $booking = $this->service->create($request->user(), $zone, $request->validated());

        return $this->backToCalendar($booking, 'Reserva confirmada'.($booking->authorization_id ? ' y autorización del evento creada para portería.' : '.'));
    }

    public function approve(Request $request, CommonZoneBooking $booking): RedirectResponse
    {
        $booking = $this->service->approve($request->user(), $booking);

        return $this->backToCalendar($booking, 'Solicitud aprobada.');
    }

    public function approveModified(StoreBookingRequest $request, CommonZoneBooking $booking): RedirectResponse
    {
        $booking = $this->service->approve($request->user(), $booking, $request->validated());

        return $this->backToCalendar($booking, 'Solicitud aprobada con cambios.');
    }

    public function reject(Request $request, CommonZoneBooking $booking): RedirectResponse
    {
        $request->validate(['review_note' => ['nullable', 'string', 'max:500']]);
        $this->service->reject($request->user(), $booking, $request->input('review_note'));

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function cancel(Request $request, CommonZoneBooking $booking): RedirectResponse
    {
        $request->validate(['review_note' => ['nullable', 'string', 'max:500']]);
        $this->service->cancel($request->user(), $booking, $request->input('review_note'));

        return back()->with('success', 'Reserva cancelada.');
    }

    public function zones(): View
    {
        $clientId = (int) $this->tenant->clientId();
        $scope = $this->tenant->installationIds();

        return view('modules.client.bookings.zones', [
            'zones' => $this->bookings->zones($clientId, $scope),
            'installations' => $this->structures->censusPickerData($clientId, $scope)['installations'],
            'weekdays' => CommonZone::WEEKDAYS,
        ]);
    }

    public function storeZone(StoreCommonZoneRequest $request): RedirectResponse
    {
        $zone = $this->service->saveZone($request->user(), null, $request->validated());

        return redirect()->route('client.bookings.zones')->with('success', "Zona «{$zone->name}» creada.");
    }

    public function updateZone(StoreCommonZoneRequest $request, CommonZone $zone): RedirectResponse
    {
        $zone = $this->service->saveZone($request->user(), $zone, $request->validated());

        return redirect()->route('client.bookings.zones')->with('success', "Zona «{$zone->name}» actualizada.");
    }

    public function destroyZone(Request $request, CommonZone $zone): RedirectResponse
    {
        $name = $zone->name;
        $this->service->deleteZone($request->user(), $zone);

        return redirect()->route('client.bookings.zones')->with('success', "Zona «{$name}» eliminada.");
    }

    private function month(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        return today()->startOfMonth();
    }

    private function backToCalendar(CommonZoneBooking $booking, string $message): RedirectResponse
    {
        return redirect()
            ->route('client.bookings.index', ['zone' => $booking->common_zone_id, 'month' => $booking->date->format('Y-m')])
            ->with('success', $message);
    }
}
