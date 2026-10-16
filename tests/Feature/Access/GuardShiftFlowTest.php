<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\Client;
use App\Models\GuardLog;
use App\Models\GuardShift;
use App\Models\Location;
use App\Models\User;
use App\Support\Company\CompanyOperateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuardShiftFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_guard_must_open_shift_with_template_and_writes_minuta(): void
    {
        [$guard, $client, $door] = $this->palmasGuard();

        $this->actingAs($guard)
            ->withSession($this->tenancy($client))
            ->get(route('access.logs.index'))
            ->assertRedirect(route('access.turnos.open'));

        $payload = $this->guardShiftOpenPayload($door, ['start_notes' => 'Puesto recibido en orden']);
        $this->actingAs($guard)
            ->withSession($this->tenancy($client))
            ->post(route('access.turnos.store'), $payload)
            ->assertRedirect(route('access.dashboard'));

        $shift = GuardShift::query()->where('user_id', $guard->id)->whereNull('ended_at')->first();
        $this->assertNotNull($shift);
        $this->assertSame((int) $payload['shift_template_id'], (int) $shift->shift_template_id);

        $openLog = GuardLog::query()
            ->where('location_id', $door->id)
            ->where('type', 'turno')
            ->where('description', 'like', 'Inicio de turno%')
            ->first();
        $this->assertNotNull($openLog);
        $this->assertStringContainsString('Puesto recibido en orden', (string) $openLog->description);
    }

    public function test_real_guard_cannot_logout_with_open_shift_and_close_writes_minuta(): void
    {
        [$guard, $client, $door] = $this->palmasGuard();

        $this->actingAs($guard)
            ->withSession($this->tenancy($client))
            ->post(route('access.turnos.store'), $this->guardShiftOpenPayload($door))
            ->assertRedirect(route('access.dashboard'));

        $this->actingAs($guard)
            ->withSession(array_merge($this->tenancy($client), [
                \App\Services\Access\PorteriaDoorService::SESSION_KEY => $door->id,
            ]))
            ->post(route('logout'))
            ->assertRedirect(route('access.turnos.index'))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($guard);

        $this->actingAs($guard)
            ->withSession($this->tenancy($client))
            ->post(route('access.turnos.close'), ['end_notes' => 'Entrega sin novedad'])
            ->assertRedirect(route('access.turnos.index'));

        $this->assertNull(GuardShift::query()->where('user_id', $guard->id)->whereNull('ended_at')->first());

        $closeLog = GuardLog::query()
            ->where('location_id', $door->id)
            ->where('type', 'turno')
            ->where('description', 'like', 'Cierre de turno%')
            ->first();
        $this->assertNotNull($closeLog);
        $this->assertStringContainsString('Entrega sin novedad', (string) $closeLog->description);

        $this->actingAs($guard)
            ->withSession($this->tenancy($client))
            ->post(route('logout'))
            ->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_operating_porteria_does_not_require_shift(): void
    {
        $this->seedWithPilot();
        config(['access.shifts.enforced' => true]);
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $session = [
            config('tenancy.session.active_client_key') => $client->id,
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_PORTERIA,
        ];

        $this->actingAs($admin)
            ->withSession($session)
            ->get(route('access.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession($session)
            ->post(route('logout'))
            ->assertRedirect('/');
        $this->assertGuest();
    }

    /** @return array{0: User, 1: Client, 2: Location} */
    private function palmasGuard(): array
    {
        $this->seedWithPilot();
        config(['access.geo.required' => false, 'access.shifts.enforced' => true]);

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $door = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        return [$guard, $client, $door];
    }

    /** @return array<string, int> */
    private function tenancy(Client $client): array
    {
        return [config('tenancy.session.active_client_key') => $client->id];
    }
}
