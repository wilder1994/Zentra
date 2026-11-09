<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\CommonZone;
use App\Models\CommonZoneBooking;
use App\Models\StructureAppUser;
use App\Services\Access\ZoneBookingService;
use App\Support\Resident\ResidentTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function __construct(
        private readonly ZoneBookingService $bookings,
        private readonly TenantContext $tenant,
    ) {}

    public function index(): View
    {
        $appUser = $this->appUser();
        ResidentTenant::bind($appUser, $this->tenant);

        $structureId = (int) ($appUser->member?->structure_id ?? 0);
        $rows = CommonZoneBooking::query()
            ->with('zone')
            ->where('client_id', (int) $appUser->client_id)
            ->where('structure_id', $structureId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return view('resident-app.bookings', [
            'appUser' => $appUser,
            'bookings' => $rows,
        ]);
    }

    public function create(): View
    {
        $appUser = $this->appUser();
        ResidentTenant::bind($appUser, $this->tenant);

        $installationId = (int) ($appUser->member?->structure?->installation_id ?? 0);
        $zones = CommonZone::query()
            ->where('client_id', (int) $appUser->client_id)
            ->where('installation_id', $installationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('resident-app.bookings-create', [
            'appUser' => $appUser,
            'zones' => $zones,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $appUser = $this->appUser();
        ResidentTenant::bind($appUser, $this->tenant);

        $structureId = (int) ($appUser->member?->structure_id ?? 0);
        abort_unless($structureId > 0, 403);

        $validated = $request->validate([
            'common_zone_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'full_day' => ['sometimes', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'people_count' => ['required', 'integer', 'min:1', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $zone = CommonZone::query()->findOrFail((int) $validated['common_zone_id']);
        $fullDay = $request->boolean('full_day');

        $this->bookings->request(null, $zone, [
            'structure_id' => $structureId,
            'structure_member_id' => $appUser->member_id,
            'date' => $validated['date'],
            'date_until' => $validated['date_until'] ?? null,
            'full_day' => $fullDay,
            'start_time' => $fullDay ? null : ($validated['start_time'] ?? null),
            'end_time' => $fullDay ? null : ($validated['end_time'] ?? null),
            'people_count' => $validated['people_count'],
            'notes' => $validated['notes'] ?? null,
            'guests' => [],
        ], 'resident_app');

        return redirect()
            ->route('resident-app.bookings')
            ->with('success', 'Solicitud enviada. Queda pendiente de aprobación.');
    }

    public function destroy(CommonZoneBooking $booking): RedirectResponse
    {
        $appUser = $this->appUser();
        ResidentTenant::bind($appUser, $this->tenant);

        abort_unless((int) $booking->client_id === (int) $appUser->client_id, 404);
        abort_unless((int) $booking->structure_id === (int) $appUser->member?->structure_id, 403);
        abort_unless(in_array($booking->status->value, BookingStatus::open(), true), 422);

        $this->bookings->cancel(null, $booking, 'Cancelada desde la app de residentes.');

        return redirect()
            ->route('resident-app.bookings')
            ->with('success', 'Reserva cancelada.');
    }

    private function appUser(): StructureAppUser
    {
        /** @var StructureAppUser $user */
        $user = Auth::guard('resident')->user();
        $user->loadMissing(['member.structure.installation', 'client']);

        return $user;
    }
}
