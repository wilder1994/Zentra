<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\BookingStatus;
use App\Models\Client;
use App\Models\CommonZone;
use App\Models\CommonZoneBooking;
use App\Models\Installation;
use App\Models\Location;
use App\Models\Structure;
use App\Models\User;
use App\Models\VisitorPreAuthorization;
use App\Services\Access\PorteriaDoorService;
use App\Services\Access\ZoneBookingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class BookingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_configures_zone_and_books_with_guests_creating_event_authorization(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $session = $this->clientSession($client);

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.bookings.zones.store'), [
                'installation_id' => $unit->installation_id,
                'name' => 'Salón social',
                'booking_mode' => 'exclusive',
                'capacity' => 60,
                'open_time' => '08:00',
                'close_time' => '23:00',
                'allow_full_day' => '1',
                'weekdays' => [1, 2, 3, 4, 5, 6, 7],
                'min_hours' => 2,
                'buffer_minutes' => 60,
                'max_advance_days' => 60,
                'is_active' => '1',
            ])
            ->assertRedirect(route('client.bookings.zones'));

        $zone = CommonZone::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertNull($zone->weekdays);
        $this->assertSame(120, $zone->min_minutes);

        $this->actingAs($admin)->withSession($session)
            ->get(route('client.bookings.zones'))
            ->assertOk()
            ->assertSee('Exclusiva · hasta 60 personas');

        $day = today()->addDays(3);
        $this->actingAs($admin)->withSession($session)
            ->get(route('client.bookings.index', ['zone' => $zone->id, 'month' => $day->format('Y-m')]))
            ->assertOk()
            ->assertSee('Solicitudes por aprobar')
            ->assertSee('Salón social');

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.bookings.store'), [
                'common_zone_id' => $zone->id,
                'structure_id' => $unit->id,
                'date' => $day->toDateString(),
                'full_day' => '0',
                'start_time' => '14:00',
                'end_time' => '18:00',
                'people_count' => 25,
                'notes' => 'Cumpleaños',
                'guests' => [
                    ['document_type' => 'CC', 'document_number' => '1088000001', 'first_name' => 'Ana', 'last_name' => 'Ríos'],
                    ['document_type' => 'CC', 'document_number' => '1088000002', 'first_name' => 'Luis', 'last_name' => 'Mora'],
                ],
            ])
            ->assertRedirect(route('client.bookings.index', ['zone' => $zone->id, 'month' => $day->format('Y-m')]));

        $booking = CommonZoneBooking::query()->where('common_zone_id', $zone->id)->firstOrFail();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('14:00', $booking->startTime());
        $authorization = VisitorPreAuthorization::query()->findOrFail($booking->authorization_id);
        $this->assertSame('event', $authorization->type->value);
        $this->assertCount(2, $authorization->guests);
        $this->assertSame('14:00 – 18:00', $authorization->timeWindowLabel());
        $this->assertDatabaseHas('visitors', ['client_id' => $client->id, 'document_number' => '1088000002']);

        $this->actingAs($admin)->withSession($session)
            ->get(route('client.bookings.index', ['zone' => $zone->id, 'month' => $day->format('Y-m')]))
            ->assertSee('14:00 · '.$unit->name);

        $this->actingAs($admin)->withSession($session)
            ->patch(route('client.bookings.cancel', $booking), ['review_note' => 'Se aplazó'])
            ->assertRedirect();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(VisitorPreAuthorization::STATE_CANCELLED, $authorization->fresh()->state());
    }

    public function test_zone_names_are_unique_per_installation_and_unused_zones_can_be_deleted(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $session = $this->clientSession($client);
        $zone = $this->zone($client, $unit, ['name' => 'Piscina zona 1']);
        $payload = [
            'installation_id' => $unit->installation_id,
            'name' => 'Piscina zona 1',
            'booking_mode' => 'shared',
            'capacity' => 20,
            'open_time' => '08:00',
            'close_time' => '20:00',
            'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'min_hours' => 1,
            'buffer_minutes' => 0,
            'max_advance_days' => 30,
            'is_active' => '1',
        ];

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.bookings.zones.store'), $payload)
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->withSession($session)
            ->put(route('client.bookings.zones.update', $zone), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.bookings.zones.store'), [...$payload, 'name' => 'Piscina zona 2'])
            ->assertSessionHasNoErrors();

        $this->service($client)->create($admin, $zone, $this->slot($unit, today()->addDay()->toDateString(), '10:00', '12:00'));
        $this->actingAs($admin)->withSession($session)
            ->delete(route('client.bookings.zones.destroy', $zone))
            ->assertSessionHasErrors('zone');

        $empty = CommonZone::query()->where('name', 'Piscina zona 2')->firstOrFail();
        $this->actingAs($admin)->withSession($session)
            ->delete(route('client.bookings.zones.destroy', $empty))
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted($empty);

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.bookings.zones.store'), [...$payload, 'name' => 'Piscina zona 2'])
            ->assertSessionHasNoErrors();
    }

    public function test_exclusive_zone_blocks_overlaps_and_respects_cleaning_buffer(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $service = $this->service($client);
        $zone = $this->zone($client, $unit, ['buffer_minutes' => 30]);
        $day = today()->addDays(2)->toDateString();

        $service->create($admin, $zone, $this->slot($unit, $day, '10:00', '12:00'));
        $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, $day, '11:00', '13:00')), 'date');
        $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, $day, '12:00', '14:00')), 'date');
        $service->create($admin, $zone, $this->slot($unit, $day, '12:30', '14:30'));
        $this->assertFails(fn () => $service->create($admin, $zone, [...$this->slot($unit, $day, '', ''), 'full_day' => true]), 'date');
    }

    public function test_shared_zone_uses_capacity(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $service = $this->service($client);
        $zone = $this->zone($client, $unit, ['booking_mode' => 'shared', 'capacity' => 10]);
        $day = today()->addDays(2)->toDateString();

        $service->create($admin, $zone, [...$this->slot($unit, $day, '09:00', '11:00'), 'people_count' => 6]);
        $this->assertFails(fn () => $service->create($admin, $zone, [...$this->slot($unit, $day, '10:00', '12:00'), 'people_count' => 5]), 'people_count');
        $service->create($admin, $zone, [...$this->slot($unit, $day, '10:00', '12:00'), 'people_count' => 4]);
    }

    public function test_requests_wait_for_approval_and_can_be_modified_or_rejected(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $service = $this->service($client);
        $zone = $this->zone($client, $unit);
        $day = today()->addDays(4)->toDateString();

        $first = $service->request(null, $zone, $this->slot($unit, $day, '10:00', '12:00'));
        $second = $service->request(null, $zone, $this->slot($unit, $day, '10:00', '12:00'));
        $this->assertSame(BookingStatus::Pending, $first->status);

        $service->approve($admin, $first);
        $this->assertFails(fn () => $service->approve($admin, $second), 'date');

        $service->approve($admin, $second, [...$this->slot($unit, $day, '15:00', '17:00'), 'review_note' => 'Se movió a la tarde']);
        $second->refresh();
        $this->assertSame(BookingStatus::Confirmed, $second->status);
        $this->assertSame('15:00', $second->startTime());
        $this->assertSame('Se movió a la tarde', $second->review_note);

        $third = $service->request(null, $zone, $this->slot($unit, $day, '19:00', '21:00'));
        $this->actingAs($admin)->withSession($this->clientSession($client))
            ->patch(route('client.bookings.reject', $third), ['review_note' => 'Mantenimiento'])
            ->assertRedirect();
        $this->assertSame(BookingStatus::Rejected, $third->fresh()->status);
    }

    public function test_zone_rules_are_enforced(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $service = $this->service($client);
        $target = today()->addDays(3);
        $zone = $this->zone($client, $unit, [
            'weekdays' => array_values(array_diff([1, 2, 3, 4, 5, 6, 7], [$target->dayOfWeekIso])),
            'allow_full_day' => false,
            'max_per_unit_month' => 1,
        ]);

        $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, $target->toDateString(), '10:00', '12:00')), 'date');
        $other = $target->copy()->addDay()->toDateString();
        $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, $other, '06:00', '08:00')), 'start_time');
        $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, $other, '10:00', '10:30')), 'end_time');
        $this->assertFails(fn () => $service->create($admin, $zone, [...$this->slot($unit, $other, '', ''), 'full_day' => true]), 'full_day');
        $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, today()->subDay()->toDateString(), '10:00', '12:00')), 'date');

        $booked = $service->create($admin, $zone, $this->slot($unit, $other, '10:00', '12:00'));
        $sameMonth = $booked->date->copy()->addDay();
        if ($sameMonth->isSameMonth($booked->date) && $sameMonth->dayOfWeekIso !== $target->dayOfWeekIso) {
            $this->assertFails(fn () => $service->create($admin, $zone, $this->slot($unit, $sameMonth->toDateString(), '14:00', '16:00')), 'structure_id');
        }
    }

    public function test_installation_admin_cannot_touch_other_installations(): void
    {
        [$admin, $client] = $this->admin();
        $unit = $this->unit($client);
        $zone = $this->zone($client, $unit);
        $other = Installation::query()->create(['client_id' => $client->id, 'name' => 'Otra sede', 'is_active' => true]);

        $tenant = app(TenantContext::class);
        $tenant->setClientId($client->id);
        $tenant->setInstallationIds([(int) $other->id]);

        $this->expectException(HttpException::class);
        app(ZoneBookingService::class)->create($admin, $zone, $this->slot($unit, today()->addDay()->toDateString(), '10:00', '12:00'));
    }

    public function test_guard_checks_in_today_and_finishes(): void
    {
        $this->seedWithPilot();
        config(['access.geo.required' => false, 'access.shifts.enforced' => true]);
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $unit = $this->unit($client);
        $zone = $this->zone($client, $unit);
        $service = $this->service($client);
        $today = $service->create($admin, $zone, [...$this->slot($unit, today()->toDateString(), '', ''), 'full_day' => true]);
        $tomorrow = $service->create($admin, $zone, [...$this->slot($unit, today()->addDay()->toDateString(), '', ''), 'full_day' => true]);

        $door = Location::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->firstOrFail();
        $session = $this->porteriaSession($client, (int) $door->id);
        $this->actingAs($guard)->withSession($session)->post(route('access.turnos.store'), ['location_id' => $door->id])->assertRedirect();
        $session[PorteriaDoorService::SESSION_KEY] = $door->id;

        $this->actingAs($guard)->withSession($session)
            ->get(route('access.zones.index'))
            ->assertOk()
            ->assertSee('Dar uso')
            ->assertSee($unit->name);

        $this->actingAs($guard)->withSession($session)->patch(route('access.zones.checkin', $tomorrow))->assertSessionHasErrors('booking');
        $this->actingAs($guard)->withSession($session)->patch(route('access.zones.checkin', $today))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::CheckedIn, $today->fresh()->status);
        $this->actingAs($guard)->withSession($session)->patch(route('access.zones.complete', $today))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Completed, $today->fresh()->status);
    }

    public function test_guard_sees_calendar_and_requests_booking_pending_admin_approval(): void
    {
        $this->seedWithPilot();
        config(['access.geo.required' => false, 'access.shifts.enforced' => true]);
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $unit = $this->unit($client);
        $zone = $this->zone($client, $unit);
        $day = today()->addDays(2);
        $this->service($client)->create($admin, $zone, $this->slot($unit, $day->toDateString(), '10:00', '12:00'));

        $door = Location::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->firstOrFail();
        $session = $this->porteriaSession($client, (int) $door->id);
        $this->actingAs($guard)->withSession($session)->post(route('access.turnos.store'), ['location_id' => $door->id])->assertRedirect();
        $session[PorteriaDoorService::SESSION_KEY] = $door->id;

        $this->actingAs($guard)->withSession($session)
            ->get(route('access.zones.calendar', ['zone' => $zone->id, 'month' => $day->format('Y-m')]))
            ->assertOk()
            ->assertSee('Solicitar reserva')
            ->assertSee('10:00 · '.$unit->name)
            ->assertDontSee('Aprobar con cambios');

        $request = fn (string $start, string $end, string $date) => [
            'common_zone_id' => $zone->id,
            'structure_id' => $unit->id,
            'date' => $date,
            'full_day' => '0',
            'start_time' => $start,
            'end_time' => $end,
            'people_count' => 8,
        ];

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.zones.request'), $request('11:00', '13:00', $day->toDateString()))
            ->assertSessionHasErrors('date');

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.zones.request'), $request('15:00', '17:00', $day->toDateString()))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('access.zones.calendar', ['zone' => $zone->id, 'month' => $day->format('Y-m')]));

        $pending = CommonZoneBooking::query()->where('origin', 'porteria')->firstOrFail();
        $this->assertSame(BookingStatus::Pending, $pending->status);
        $this->assertSame($guard->id, $pending->user_id);
        $this->assertStringContainsString('vía portería', $pending->requesterLabel());

        $this->actingAs($guard)->withSession($session)
            ->post(route('access.zones.request'), $request('09:00', '10:00', today()->toDateString()))
            ->assertSessionHasNoErrors();
        $this->actingAs($guard)->withSession($session)
            ->get(route('access.zones.index'))
            ->assertOk()
            ->assertSee('Por aprobar')
            ->assertDontSee('Dar uso');

        $this->actingAs($admin)->withSession($this->clientSession($client))
            ->get(route('client.bookings.index', ['zone' => $zone->id]))
            ->assertSee('09:00 · '.$unit->name);
    }

    /** @return array{0: User, 1: Client} */
    private function admin(): array
    {
        $this->seedWithPilot();

        return [
            User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail(),
            Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail(),
        ];
    }

    private function service(Client $client): ZoneBookingService
    {
        $tenant = app(TenantContext::class);
        $tenant->setClientId($client->id);
        $tenant->setInstallationIds(null);

        return app(ZoneBookingService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function zone(Client $client, Structure $unit, array $overrides = []): CommonZone
    {
        return CommonZone::query()->create([
            'client_id' => $client->id,
            'installation_id' => $unit->installation_id,
            'name' => 'Salón social',
            'booking_mode' => 'exclusive',
            'capacity' => 40,
            'open_time' => '08:00',
            'close_time' => '22:00',
            'allow_full_day' => true,
            'min_minutes' => 60,
            'buffer_minutes' => 0,
            'max_advance_days' => 60,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /** @return array<string, mixed> */
    private function slot(Structure $unit, string $date, string $start, string $end): array
    {
        return [
            'structure_id' => $unit->id,
            'date' => $date,
            'full_day' => false,
            'start_time' => $start,
            'end_time' => $end,
            'people_count' => 10,
        ];
    }

    private function assertFails(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail("Debió fallar con error en {$field}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
        }
    }

    private function unit(Client $client): Structure
    {
        return Structure::query()->where('client_id', $client->id)->whereNotNull('installation_id')->orderBy('id')->firstOrFail();
    }

    /** @return array<string, int> */
    private function clientSession(Client $client): array
    {
        return [config('tenancy.session.active_client_key') => $client->id];
    }
}
