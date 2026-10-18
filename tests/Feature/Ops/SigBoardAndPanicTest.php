<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Enums\OperationalAlertType;
use App\Models\Client;
use App\Models\User;
use App\Support\Company\CompanyOperateContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SigBoardAndPanicTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_dashboard_shows_sig_board(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($user)
            ->withSession([
                config('tenancy.session.active_client_key') => $client->id,
                CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
                CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
            ])
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertDontSee('Panel del cliente')
            ->assertSee('Novedades de servicio')
            ->assertSee('Revistas del supervisor')
            ->assertSee('Servicios actuales')
            ->assertSee('Salud afiliatoria');
    }

    public function test_company_installations_index_shows_sig_kpis(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('company.installations.index'))
            ->assertOk()
            ->assertSee('Novedades de servicio')
            ->assertSee('Servicios hoy')
            ->assertSee('Revistas del supervisor')
            ->assertSee('Instalaciones y servicios')
            ->assertSee('Satélite')
            ->assertDontSee('Personal operativo');
    }

    public function test_panic_is_hidden_from_actor_and_from_client_admins(): void
    {
        $this->seedWithPilot();
        $actor = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $other = User::factory()->create([
            'security_company_id' => $actor->security_company_id,
            'name' => 'Otro admin',
            'username' => 'otro.admin.9901',
            'is_active' => true,
        ]);
        $other->assignRole('company-admin');
        $this->grantCompanyAdminCatalog($other);

        $this->actingAs($actor)
            ->postJson(route('company.ops.panic'), ['note' => 'Prueba'])
            ->assertCreated();

        $this->assertDatabaseHas('operational_alerts', [
            'type' => OperationalAlertType::Panic->value,
            'actor_user_id' => $actor->id,
        ]);

        $this->actingAs($actor)
            ->getJson(route('company.ops.alerts'))
            ->assertOk()
            ->assertJsonCount(0, 'alerts');

        $this->actingAs($other)
            ->getJson(route('company.ops.alerts'))
            ->assertOk()
            ->assertJsonPath('alerts.0.type', 'panic');

        $clientAdmin = User::query()->where('email', 'laura.c@example.com')->first()
            ?? User::query()->role('client-admin')->first();
        if ($clientAdmin !== null) {
            $this->actingAs($clientAdmin)
                ->getJson(route('client.ops.alerts'))
                ->assertOk()
                ->assertJsonCount(0, 'alerts');
        }
    }

    public function test_live_json_refreshes_observatory_and_sig(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $session = [
            config('tenancy.session.active_client_key') => $client->id,
            CompanyOperateContext::SESSION_CLIENT_KEY => $client->id,
            CompanyOperateContext::SESSION_MODE_KEY => CompanyOperateContext::MODE_CLIENTE,
        ];

        $this->getJson(route('company.observatory.live'))->assertUnauthorized();

        $this->actingAs($user)
            ->getJson(route('company.observatory.live'))
            ->assertOk()
            ->assertJsonStructure([
                'board' => ['total', 'nuevo', 'en_atencion', 'cerrado', 'load_rate', 'top'],
                'map' => ['sites', 'points'],
                'events',
            ]);

        $this->actingAs($user)
            ->get(route('company.observatory.events.index'))
            ->assertOk()
            ->assertSee('data-live-url', false);

        $this->actingAs($user)
            ->getJson(route('company.sig.live'))
            ->assertOk()
            ->assertJsonStructure(['installations_count', 'services_today', 'feed', 'reviews', 'services', 'chart']);

        $this->actingAs($user)
            ->withSession($session)
            ->getJson(route('client.sig.live'))
            ->assertOk()
            ->assertJsonStructure(['installations_count', 'posts_count']);

        $this->actingAs($user)
            ->withSession($session)
            ->getJson(route('client.observatory.live'))
            ->assertOk();
    }
}
