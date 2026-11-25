<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use App\Support\Client\ClientPanelModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObservatoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_admin_creates_type_and_intake_shows_it(): void
    {
        $client = $this->palmas();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $session = ['tenancy.active_client_id' => $client->id];

        $this->actingAs($admin)->withSession($session)
            ->post(route('client.observatory.types.store'), [
                'name' => 'Porte de arma',
                'impacto' => 8,
                'urgencia' => 8,
                'color' => '#22c55e',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('observatory_report_types', [
            'security_company_id' => $client->security_company_id,
            'name' => 'Porte de arma',
            'impacto' => 8,
            'urgencia' => 8,
            'color' => '#22c55e',
        ]);

        $this->get(route('observatory.public.show', $client->slug))
            ->assertOk()
            ->assertSee('Porte de arma', false);
    }

    public function test_company_and_rector_cannot_create_types(): void
    {
        $client = $this->palmas();
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $session = ['tenancy.active_client_id' => $client->id];

        $this->actingAs($company)->withSession($session)
            ->post(route('client.observatory.types.store'), [
                'name' => 'Hack',
                'impacto' => 8,
                'urgencia' => 8,
                'color' => '#111111',
            ])
            ->assertForbidden();

        $colegio = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Catalogo',
            'installation_profile_id' => $this->installationProfileId(),
            'dane_code' => '176001000199',
            'is_client_site' => false,
            'is_active' => true,
        ]);

        $rector = $this->makeSiteAdmin($company, $client, $colegio, 'rector.cat@test.com', '900111222');

        $this->actingAs($rector)->withSession($session)
            ->post(route('client.observatory.types.store'), [
                'name' => 'Hack rector',
                'impacto' => 6,
                'urgencia' => 6,
                'color' => '#222222',
            ])
            ->assertForbidden();
    }

    public function test_company_can_filter_board_by_client(): void
    {
        $client = $this->palmas();
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee($client->name, false)
            ->assertSee('Mapa de calor', false)
            ->assertSee('Riesgo por instalación', false);
    }

    public function test_module_off_hides_client_observatory(): void
    {
        $client = $this->palmas();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($company)->put(route('company.clients.modules.update', $client), [
            'modules' => [
                'vehicles' => '1',
                'pets' => '1',
                'authorizations' => '1',
                'doors' => '1',
                'observatory' => '0',
            ],
        ])->assertRedirect();

        $this->assertFalse($client->fresh()->panelModuleEnabled(ClientPanelModules::OBSERVATORY));

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.observatory.events.index'))
            ->assertForbidden();
    }

    private function palmas(): Client
    {
        $this->seedWithPilot();

        return Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
    }

    private function makeSiteAdmin(User $companyAdmin, Client $client, Installation $site, string $email, string $document): User
    {
        $this->actingAs($companyAdmin)->post(route('company.users.store'), [
            'role' => 'client-installation-admin',
            'origin' => 'external',
            'name' => 'Rector cat',
            'document_number' => $document,
            'job_title' => 'Rector',
            'email' => $email,
            'username' => 'rector.cat.'.substr($document, -4),
            'password' => 'Cliente123!',
            'password_confirmation' => 'Cliente123!',
            'client_ids' => [$client->id],
            'installation_ids' => [$site->id],
            'installation_permission' => 'admin',
            'is_active' => '1',
        ])->assertRedirect();

        $user = User::query()->where('email', $email)->firstOrFail();
        $user->update(['must_change_password' => false]);

        return $user;
    }
}
