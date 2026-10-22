<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\InstallationKind;
use App\Models\ApiIntegrationKey;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryReport;
use App\Models\SecurityCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiIntegrationKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_is_for_platform_admins(): void
    {
        [$company] = $this->setUpCompany();
        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($companyAdmin)
            ->get(route('admin.companies.integrations.index', $company))
            ->assertForbidden();

        $this->actingAs($this->super())
            ->get(route('admin.companies.integrations.index', $company))
            ->assertOk()
            ->assertSee('Integraciones API', false)
            ->assertSee('Nueva API key', false)
            ->assertSee('cómo funciona', false)
            ->assertSee('Entrar', false)
            ->assertDontSee('Entrar como empresa', false)
            ->assertSee('Manual de API Key', false)
            ->assertSee('Manual de Integración', false);

        foreach (['api-key' => 'manual-api-key-zentra.pdf', 'integracion' => 'manual-integracion-api-zentra.pdf'] as $type => $file) {
            $this->actingAs($this->super())
                ->get(route('admin.companies.integrations.manual', [$company, $type]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertDownload($file);
        }

        $this->actingAs($companyAdmin)
            ->get(route('admin.companies.integrations.manual', [$company, 'api-key']))
            ->assertForbidden();
    }

    public function test_read_key_of_existing_user_only_opens_granted_endpoints(): void
    {
        [$company] = $this->setUpCompany();
        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $plain = $this->createKey($company, [
            'name' => 'Tablero BI',
            'user_mode' => 'existing',
            'user_id' => $companyAdmin->id,
            'levels' => ['observatory' => 'read', 'access' => 'none'],
        ]);

        $key = ApiIntegrationKey::query()->firstOrFail();
        $this->assertSame(['observatory.events.read', 'observatory.board.read', 'observatory.sites.read'], $key->abilities);
        $this->assertSame((int) $companyAdmin->id, (int) $key->user_id);

        $this->withToken($plain)->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.id', $companyAdmin->id);
        $this->withToken($plain)->getJson('/api/observatory/events')->assertOk();
        $this->withToken($plain)->postJson('/api/observatory/reports', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'api_key_ability');
        $this->withToken($plain)->getJson('/api/pre-authorizations')->assertForbidden();
        $this->withToken($plain)->postJson('/api/auth/logout')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', [
            'email' => $companyAdmin->email,
            'password' => 'Empresa123!',
            'replace_session' => true,
        ])->assertOk();
        $this->assertNotNull($key->fresh()->token, 'El login personal no borra la API key.');
    }

    public function test_external_user_key_writes_only_in_its_clients(): void
    {
        [$company, $palmas, $colegio, $foreign] = $this->setUpCompany();

        $plain = $this->createKey($company, [
            'name' => 'Secretaría de Educación',
            'user_mode' => 'new',
            'new_user_name' => 'Integración Secretaría',
            'client_ids' => [$palmas->id],
            'levels' => ['observatory' => 'custom', 'access' => 'none'],
            'custom' => ['observatory.sites.read', 'observatory.reports.write'],
        ]);

        $user = User::query()->where('name', 'Integración Secretaría')->firstOrFail();
        $this->assertTrue($user->hasRole('colaborador'));
        $this->assertSame((int) $company->id, (int) $user->security_company_id);

        $this->withToken($plain)->getJson('/api/observatory/events')->assertForbidden();
        $this->withToken($plain)->getJson('/api/observatory/sites')
            ->assertOk()
            ->assertJsonFragment(['name' => 'IE Santa Librada'])
            ->assertJsonMissing(['name' => 'IE Torres Norte']);

        $this->withToken($plain)->postJson('/api/observatory/reports', [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'La Secretaría reporta un hurto en el muro del colegio.',
        ])->assertCreated();
        $this->assertSame('api', ObservatoryReport::query()->firstOrFail()->source->value);

        $this->withToken($plain)->postJson('/api/observatory/reports', [
            'installation_id' => $foreign->id,
            'kind' => 'hurto',
            'body' => 'No puede reportar en un cliente que no se le dio.',
        ])->assertForbidden();
    }

    public function test_revoke_and_regenerate(): void
    {
        [$company] = $this->setUpCompany();
        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $plain = $this->createKey($company, [
            'name' => 'Clave rotativa',
            'user_mode' => 'existing',
            'user_id' => $companyAdmin->id,
            'levels' => ['observatory' => 'read'],
        ]);
        $key = ApiIntegrationKey::query()->firstOrFail();

        $this->actingAs($this->super())
            ->post(route('admin.companies.integrations.regenerate', [$company, $key]))
            ->assertRedirect()
            ->assertSessionHas('api_key_plain');
        $fresh = (string) session('api_key_plain')['token'];
        $this->assertNotSame($plain, $fresh);

        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/observatory/events')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($fresh)->getJson('/api/observatory/events')->assertOk();

        $this->actingAs($this->super())
            ->post(route('admin.companies.integrations.revoke', [$company, $key]))
            ->assertRedirect();
        $this->assertSame(ApiIntegrationKey::STATUS_REVOKED, $key->fresh()->status());

        $this->app['auth']->forgetGuards();
        $this->withToken($fresh)->getJson('/api/observatory/events')->assertUnauthorized();
    }

    public function test_key_with_expiration_stops_after_the_date(): void
    {
        [$company] = $this->setUpCompany();
        $companyAdmin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $plain = $this->createKey($company, [
            'name' => 'Prueba temporal',
            'user_mode' => 'existing',
            'user_id' => $companyAdmin->id,
            'levels' => ['observatory' => 'read'],
            'expires_at' => now()->addDays(3)->toDateString(),
        ]);
        $key = ApiIntegrationKey::query()->firstOrFail();

        $this->withToken($plain)->getJson('/api/observatory/events')->assertOk();

        $this->travel(5)->days();
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/observatory/events')->assertUnauthorized();
        $this->assertSame(ApiIntegrationKey::STATUS_EXPIRED, $key->fresh()->status());

        $this->actingAs($this->super())
            ->post(route('admin.companies.integrations.regenerate', [$company, $key]))
            ->assertSessionHasErrors('key');
    }

    /** @param array<string, mixed> $payload */
    private function createKey(SecurityCompany $company, array $payload): string
    {
        $this->actingAs($this->super())
            ->post(route('admin.companies.integrations.store', $company), $payload)
            ->assertRedirect(route('admin.companies.integrations.index', $company))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('api_key_plain');

        $plain = (string) session('api_key_plain')['token'];
        $this->app['auth']->forgetGuards();

        return $plain;
    }

    private function super(): User
    {
        return User::query()->role('super-admin')->firstOrFail();
    }

    /** @return array{0: SecurityCompany, 1: Client, 2: Installation, 3: Installation} */
    private function setUpCompany(): array
    {
        $this->seedWithPilot();
        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $torres = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $company = SecurityCompany::query()->findOrFail($palmas->security_company_id);

        $make = fn (Client $client, string $name, string $dane): Installation => Installation::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => $dane,
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        return [
            $company,
            $palmas,
            $make($palmas, 'IE Santa Librada', '176001000401'),
            $make($torres, 'IE Torres Norte', '176001000402'),
        ];
    }
}
