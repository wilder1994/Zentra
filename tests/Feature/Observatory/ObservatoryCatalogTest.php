<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\InstallationKind;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryReportType;
use App\Models\User;
use App\Services\Observatory\BuildObservatoryBoardService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
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
                'level' => 3,
                'color' => '#22c55e',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('observatory_report_types', [
            'client_id' => $client->id,
            'name' => 'Porte de arma',
            'level' => 3,
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
                'level' => 3,
                'color' => '#111111',
            ])
            ->assertForbidden();

        $colegio = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Catalogo',
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => '176001000199',
            'is_client_site' => false,
            'is_active' => true,
        ]);

        $rector = $this->makeSiteAdmin($company, $client, $colegio, 'rector.cat@test.com', '900111222');

        $this->actingAs($rector)->withSession($session)
            ->post(route('client.observatory.types.store'), [
                'name' => 'Hack rector',
                'level' => 2,
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
            ->assertSee('Calor riesgo', false);
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

    public function test_ranking_uses_type_level(): void
    {
        $client = $this->palmas();
        app(EnsureObservatoryReportTypesService::class)->execute($client);
        $amenaza = ObservatoryReportType::query()->where('client_id', $client->id)->where('slug', 'amenaza')->firstOrFail();
        $otro = ObservatoryReportType::query()->where('client_id', $client->id)->where('slug', 'otro')->firstOrFail();

        $high = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Alto Riesgo',
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => '176001000201',
            'is_client_site' => false,
            'is_active' => true,
        ]);
        $low = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Bajo Riesgo',
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => '176001000202',
            'is_client_site' => false,
            'is_active' => true,
        ]);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $high->id,
            'kind' => $amenaza->slug,
            'body' => 'Amenaza grave en el alto riesgo del colegio.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $low->id,
            'kind' => $otro->slug,
            'body' => 'Novedad menor uno en el colegio de bajo riesgo.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();
        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $low->id,
            'kind' => $otro->slug,
            'body' => 'Novedad menor dos en el colegio de bajo riesgo.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $board = app(BuildObservatoryBoardService::class)
            ->execute(null, (int) $client->id, null, null, null);

        $this->assertSame('IE Alto Riesgo', $board['top'][0]['name']);
        $this->assertSame(3, $board['top'][0]['score']);
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
