<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Models\Client;
use App\Models\Location;
use App\Models\MemberType;
use App\Models\Structure;
use App\Models\User;
use App\Services\Access\PorteriaDoorService;
use App\Support\Company\CompanyOperateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperateAsRoleTest extends TestCase
{
    use RefreshDatabase;

    private function palmas(): Client
    {
        return Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function operating(Client $client, string $mode, ?int $installationId = null): array
    {
        return array_filter([
            config('tenancy.session.active_client_key') => $client->id,
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => $mode,
            CompanyOperateContext::SESSION_INSTALLATION_KEY => $installationId,
        ], fn ($v) => $v !== null);
    }

    public function test_super_admin_operating_porteria_sees_only_what_the_guard_sees(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $session = $this->operating($this->palmas(), CompanyOperateContext::MODE_PORTERIA);

        $this->actingAs($admin)->withSession($session)->get(route('access.dashboard'))
            ->assertOk()
            ->assertSee('Ingreso y salida')
            ->assertSee('Turnos')
            ->assertDontSee(route('access.sites.index'), false)
            ->assertDontSee(route('client.dashboard'), false);

        $this->actingAs($admin)->withSession($session)->get(route('access.sites.index'))->assertForbidden();
        $this->actingAs($admin)->withSession($session)->get(route('access.locations.create'))->assertForbidden();
        $this->actingAs($admin)->withSession($session)->get(route('access.reports.index'))->assertForbidden();
        $this->actingAs($admin)->withSession($session)->get(route('access.blocklist.index'))
            ->assertOk()
            ->assertDontSee('Remover');
    }

    public function test_operating_client_uses_client_admin_permissions(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $session = $this->operating($this->palmas(), CompanyOperateContext::MODE_CLIENTE);

        $this->actingAs($admin)->withSession($session)->get(route('access.sites.index'))->assertOk();
        $this->actingAs($admin)->withSession($session)->get(route('client.dashboard'))->assertOk();
        $this->actingAs($admin)->withSession($session)->get(route('client.employees.index'))->assertForbidden();
    }

    public function test_operating_client_can_register_census_member(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $client = $this->palmas();
        $session = $this->operating($client, CompanyOperateContext::MODE_CLIENTE);
        $node = Structure::query()->withoutGlobalScopes()->where('client_id', $client->id)->firstOrFail();
        $type = MemberType::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->firstOrFail();

        $response = $this->actingAs($admin)->withSession($session)->post(route('client.members.store'), [
            'first_name' => 'Wilder',
            'last_name' => 'Rivera',
            'document_type' => 'CC',
            'document_number' => '1112627546',
            'birth_date' => '1994-06-02',
            'structure_id' => $node->id,
            'member_type_id' => $type->id,
            'phone_primary' => '3154785605',
            'email' => 'wilder@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('structure_members', ['client_id' => $client->id, 'document_number' => '1112627546']);

        $this->actingAs($admin)->withSession($session)->post(route('client.members.store'), [
            'first_name' => 'Menor',
            'last_name' => 'Sin Autorizacion',
            'document_type' => 'TI',
            'document_number' => '1112000001',
            'birth_date' => now()->subYears(10)->toDateString(),
            'structure_id' => $node->id,
            'member_type_id' => $type->id,
        ])->assertSessionHasErrors('minor_treatment_accepted');
    }

    public function test_operating_installation_hides_client_users_module(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $client = $this->palmas();
        $site = $client->installations()->orderBy('name')->firstOrFail();
        $session = $this->operating($client, CompanyOperateContext::MODE_CLIENTE, (int) $site->id);

        $this->actingAs($admin)->withSession($session)->get(route('client.users.index'))->assertForbidden();
        $this->actingAs($admin)->withSession($session)->get(route('client.members.index'))->assertOk();
    }

    public function test_company_panel_keeps_full_permissions_while_operating(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $session = $this->operating($this->palmas(), CompanyOperateContext::MODE_PORTERIA);

        $this->actingAs($user)->withSession($session)->get(route('company.clients.show', $this->palmas()))->assertOk();
    }

    public function test_real_guard_cannot_open_admin_porteria_urls(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $client = $this->palmas();
        $door = Location::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->firstOrFail();
        $session = [
            config('tenancy.session.active_client_key') => $client->id,
            PorteriaDoorService::SESSION_KEY => $door->id,
        ];

        foreach (['access.locations.create', 'access.buildings.create', 'access.housing_units.create', 'access.sites.index', 'access.reports.index', 'access.reports.export'] as $route) {
            $this->actingAs($guard)->withSession($session)->get(route($route))->assertForbidden();
        }

        $this->actingAs($guard)->withSession($session)->get(route('access.blocklist.index'))->assertOk();
    }
}
