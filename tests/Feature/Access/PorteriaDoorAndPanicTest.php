<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Enums\OperationalAlertType;
use App\Models\Client;
use App\Models\Location;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Access\PorteriaDoorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PorteriaDoorAndPanicTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_porteria_panic_route_is_gone(): void
    {
        $this->assertFalse(Route::has('access.guard_logs.panic'));
    }

    public function test_several_doors_require_selection_before_dashboard(): void
    {
        [$vigilante, $client] = $this->palmasVigilante();

        $this->actingAs($vigilante)
            ->withSession($this->tenancy($client))
            ->get(route('access.dashboard'))
            ->assertRedirect(route('access.turnos.open'));
    }

    public function test_company_admin_operating_porteria_skips_door_picker(): void
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->tenancy($client))
            ->get(route('access.dashboard'))
            ->assertOk()
            ->assertSee('Personas dentro');
    }

    public function test_opening_shift_requires_a_door_and_panic_records_user_and_door(): void
    {
        [$vigilante, $client] = $this->palmasVigilante();
        $door = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('code', 'PA-01')
            ->firstOrFail();

        $this->actingAs($vigilante)
            ->withSession($this->tenancy($client))
            ->from(route('access.turnos.open'))
            ->post(route('access.turnos.store'), [])
            ->assertRedirect(route('access.turnos.open'))
            ->assertSessionHasErrors(['location_id', 'shift_template_id']);

        $payload = $this->guardShiftOpenPayload($door);
        $this->actingAs($vigilante)
            ->withSession($this->tenancy($client))
            ->post(route('access.turnos.store'), $payload)
            ->assertRedirect(route('access.dashboard'));

        $this->assertDatabaseHas('guard_shifts', [
            'user_id' => $vigilante->id,
            'location_id' => $door->id,
            'shift_template_id' => $payload['shift_template_id'],
            'ended_at' => null,
        ]);

        $this->actingAs($vigilante)
            ->withSession(array_merge($this->tenancy($client), [
                PorteriaDoorService::SESSION_KEY => $door->id,
            ]))
            ->postJson(route('access.ops.panic'), ['note' => 'Auxilio'])
            ->assertCreated();

        $alert = OperationalAlert::query()->where('actor_user_id', $vigilante->id)->first();
        $this->assertNotNull($alert);
        $this->assertSame(OperationalAlertType::Panic, $alert->type);
        $this->assertSame($door->id, (int) ($alert->payload['location_id'] ?? 0));
        $this->assertSame('Puerta principal', $alert->payload['location_name'] ?? null);
        $this->assertStringContainsString($vigilante->name, (string) $alert->body);
        $this->assertStringContainsString('Puerta principal', (string) $alert->body);

        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $this->actingAs($companyAdmin)
            ->getJson(route('company.ops.alerts'))
            ->assertOk()
            ->assertJsonPath('alerts.0.type', 'panic');
    }

    public function test_single_door_is_bound_without_asking(): void
    {
        [$vigilante, $client] = $this->palmasVigilante();
        Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('code', '!=', 'PA-01')
            ->update(['is_active' => false]);

        $door = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('code', 'PA-01')
            ->firstOrFail();

        $this->actingAs($vigilante)
            ->withSession($this->tenancy($client))
            ->get(route('access.turnos.open'))
            ->assertOk()
            ->assertSee('Iniciar turno')
            ->assertSee($door->name);

        $this->actingAs($vigilante)
            ->withSession($this->tenancy($client))
            ->post(route('access.turnos.store'), $this->guardShiftOpenPayload($door))
            ->assertRedirect(route('access.dashboard'));
    }

    /** @return array{0: User, 1: Client} */
    private function palmasVigilante(): array
    {
        $this->seedWithPilot();
        config(['access.geo.required' => false, 'access.shifts.enforced' => true]);

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $vigilante = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        return [$vigilante, $client];
    }

    /** @return array<string, int> */
    private function tenancy(Client $client): array
    {
        return [config('tenancy.session.active_client_key') => $client->id];
    }
}
