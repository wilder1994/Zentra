<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\User;
use App\Support\Company\CompanyOperateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyClientExpedienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_index_only_shows_ver_action(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $response = $this->actingAs($user)->get(route('company.clients.index'));

        $response->assertOk();
        $response->assertSee('Ver');
        $response->assertDontSee('>Operar</');
        $response->assertDontSee('>Editar</');
    }

    public function test_client_show_renders_expediente_and_operate_actions(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $response = $this->actingAs($user)->get(route('company.clients.show', $client));

        $response->assertOk();
        $response->assertSee('Cliente');
        $response->assertSee('Resumen');
        $response->assertSee('Accesos');
        $response->assertSee('Supervisión');
        $response->assertSee('Operar portería');
        $response->assertSee('Operar cliente');
        $response->assertSee('Operar instalación');
        $response->assertSee('Inicio de servicio');
        $response->assertSee('Editar');
        $response->assertSee('Instalaciones');
        $response->assertDontSee('>Puertas</p>', false);
        $response->assertSee('Ficha comercial');
        $response->assertSee('Gestión de módulos');
        $response->assertSee('← Cartera');

        $resumen = $this->actingAs($user)->get(route('company.clients.show', [$client, 'vista' => 'resumen']));
        $resumen->assertOk();
        $resumen->assertSee('Personas (censo)');
        $resumen->assertSee('Usuarios app');
        $resumen->assertSee('Parque vehicular');
        $resumen->assertSee('Guardas asignados');
        $resumen->assertDontSee('Crear instalación');
        $resumen->assertDontSee('Operar portería');
        $resumen->assertDontSee('Operar cliente');

        $sitio = $this->actingAs($user)->get(route('company.clients.show', [$client, 'vista' => 'sitio']));
        $sitio->assertOk();
        $sitio->assertSee('Instalaciones');
        $sitio->assertSee('Portería principal');
        $sitio->assertSee('+ Instalación');
        $sitio->assertDontSee('Personas (censo)');
        $sitio->assertDontSee('Operar portería');
        $sitio->assertDontSee('Operar cliente');
        $sitio->assertSee('Crear');
        $sitio->assertSee('Historial');

        $puertas = $this->actingAs($user)->get(route('company.clients.show', [$client, 'vista' => 'puertas']));
        $puertas->assertOk();
        $puertas->assertSee('Instalaciones');
        $puertas->assertSee('Puerta principal');
        $puertas->assertSee('Crear');
        $puertas->assertDontSee('Personas (censo)');
    }

    public function test_operate_client_opens_client_panel(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $response = $this->actingAs($user)->post(route('company.clients.operate-client', $client));

        $response->assertRedirect(route('client.dashboard'));
        $this->assertEquals(
            $client->id,
            session(config('tenancy.session.active_client_key')),
        );
        $this->assertSame((int) $client->id, CompanyOperateContext::clientId());
        $this->assertSame(CompanyOperateContext::MODE_CLIENTE, CompanyOperateContext::mode());
        $this->assertNull(CompanyOperateContext::installationId());
    }

    public function test_operate_installation_scopes_the_session(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $site = $client->installations()->orderBy('name')->firstOrFail();

        $response = $this->actingAs($user)->post(route('company.clients.operate-installation', $client), [
            'installation_id' => $site->id,
        ]);

        $response->assertRedirect(route('client.dashboard'));
        $this->assertSame((int) $client->id, CompanyOperateContext::clientId());
        $this->assertSame((int) $site->id, CompanyOperateContext::installationId());
    }

    public function test_operate_porteria_and_exit_returns_to_expediente(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $door = \App\Models\Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereNotNull('installation_id')
            ->orderBy('id')
            ->firstOrFail();

        $enter = $this->actingAs($user)->post(route('company.clients.activate', $client), [
            'installation_id' => $door->installation_id,
            'location_id' => $door->id,
        ]);
        $enter->assertRedirect(route('access.dashboard'));
        $this->assertSame(CompanyOperateContext::MODE_PORTERIA, CompanyOperateContext::mode());
        $this->assertSame((int) $door->installation_id, CompanyOperateContext::installationId());
        $this->assertSame((int) $door->id, (int) session(\App\Services\Access\PorteriaDoorService::SESSION_KEY));

        $dashboard = $this->actingAs($user)
            ->withSession([
                config('tenancy.session.active_client_key') => $client->id,
                CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
                CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_PORTERIA,
                CompanyOperateContext::SESSION_INSTALLATION_KEY => $door->installation_id,
                \App\Services\Access\PorteriaDoorService::SESSION_KEY => $door->id,
            ])
            ->get(route('access.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Volver al expediente');
        $dashboard->assertSee($client->name);

        $exit = $this->actingAs($user)
            ->withSession([
                CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
                CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_PORTERIA,
            ])
            ->post(route('company.operate.exit'));

        $exit->assertRedirect(route('company.clients.show', $client));
        $this->assertNull(CompanyOperateContext::clientId());
    }

    public function test_operate_porteria_requires_door_when_client_has_several(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($user)
            ->from(route('company.clients.show', $client))
            ->post(route('company.clients.activate', $client))
            ->assertRedirect(route('company.clients.show', $client))
            ->assertSessionHas('error');
    }

    public function test_operate_client_and_exit_returns_to_expediente(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($user)->post(route('company.clients.operate-client', $client));

        $dashboard = $this->actingAs($user)
            ->withSession([
                config('tenancy.session.active_client_key') => $client->id,
                CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
                CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
            ])
            ->get(route('client.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertSee('Volver al expediente');
        $dashboard->assertSee('panel del cliente');

        $exit = $this->actingAs($user)
            ->withSession([
                CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
                CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
            ])
            ->post(route('company.operate.exit'));

        $exit->assertRedirect(route('company.clients.show', $client));
        $this->assertNull(CompanyOperateContext::clientId());
    }

    public function test_company_can_turn_off_optional_client_modules(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $session = [
            config('tenancy.session.active_client_key') => $client->id,
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
        ];

        $this->actingAs($user)->put(route('company.clients.modules.update', $client), [
            'modules' => [
                'vehicles' => '0',
                'pets' => '1',
                'authorizations' => '0',
                'doors' => '1',
                'observatory' => '1',
            ],
        ])->assertRedirect(route('company.clients.show', $client));

        $client->refresh();
        $this->assertFalse($client->panelModuleEnabled('vehicles'));
        $this->assertTrue($client->panelModuleEnabled('pets'));
        $this->assertFalse($client->panelModuleEnabled('authorizations'));
        $this->assertTrue($client->panelModuleEnabled('doors'));

        $this->actingAs($user)->withSession($session)->get(route('client.vehicles.index'))->assertForbidden();
        $this->actingAs($user)->withSession($session)->get(route('client.authorizations.index'))->assertForbidden();
        $this->actingAs($user)->withSession($session)->get(route('client.pets.index'))->assertOk();

        $this->actingAs($user)->withSession($session)->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Abrir menú', false)
            ->assertDontSee('>Vehículos</', false)
            ->assertDontSee('Autorizaciones')
            ->assertSee('Personas')
            ->assertSee('Usuarios')
            ->assertSee('Accesos')
            ->assertSee('Puertas')
            ->assertDontSee('Consola portería');
    }

    public function test_doors_module_stays_off_when_client_has_no_doors(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $client->locations()->update(['is_active' => false]);

        $this->actingAs($user)->put(route('company.clients.modules.update', $client), [
            'modules' => [
                'vehicles' => '1',
                'pets' => '1',
                'authorizations' => '1',
                'doors' => '1',
                'observatory' => '1',
            ],
        ])->assertRedirect();

        $this->assertFalse($client->fresh()->panelModuleEnabled('doors'));
        $this->assertFalse($client->fresh()->panel_modules['doors']);
    }

    public function test_doors_module_blocks_client_admin_but_not_vigilante(): void
    {
        $this->seedWithPilot();

        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $vigilante = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $tenancy = [config('tenancy.session.active_client_key') => $client->id];

        $this->actingAs($company)->put(route('company.clients.modules.update', $client), [
            'modules' => [
                'vehicles' => '1',
                'pets' => '1',
                'authorizations' => '1',
                'doors' => '0',
                'observatory' => '1',
            ],
        ])->assertRedirect();

        $this->assertFalse($client->fresh()->panelModuleEnabled('doors'));

        $this->actingAs($clientAdmin)->withSession($tenancy)->get(route('access.dashboard'))->assertForbidden();

        $session = $this->porteriaSession($client);
        $door = \App\Models\Location::query()->withoutGlobalScopes()->findOrFail($session[\App\Services\Access\PorteriaDoorService::SESSION_KEY]);
        $this->actingAs($vigilante)->withSession($session)
            ->post(route('access.turnos.store'), $this->guardShiftOpenPayload($door))
            ->assertRedirect();
        $this->actingAs($vigilante)->withSession($session)->get(route('access.dashboard'))->assertOk();

        $this->actingAs($company)->withSession($tenancy + [
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_PORTERIA,
            \App\Services\Access\PorteriaDoorService::SESSION_KEY => $door->id,
        ])->get(route('access.dashboard'))->assertOk();

        $this->actingAs($company)->withSession($tenancy + [
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
        ])->get(route('access.dashboard'))->assertForbidden();
    }
}
