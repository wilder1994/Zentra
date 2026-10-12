<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\InstallationKind;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObservatoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_and_docs_are_public(): void
    {
        $this->get('/docs/observatory')
            ->assertOk()
            ->assertSee('API del Observatorio', false);

        $this->getJson('/api/observatory/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'Zentra Observatorio API')
            ->assertJsonStructure(['paths' => ['/api/observatory/events', '/api/observatory/reports']]);
    }

    public function test_client_admin_reads_and_writes_only_own_observatory(): void
    {
        [$client, $colegio] = $this->sites();
        $token = $this->token('admin@palmasdelingenio.test', 'Cliente123!');

        $this->withToken($token)->getJson('/api/observatory/events')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);

        $emptyBoard = $this->withToken($token)->getJson('/api/observatory/board')->assertOk();
        $this->assertSame(0, $emptyBoard->json('board.total'));
        $this->assertSame(0, $emptyBoard->json('board.closed_rate'));
        $this->assertSame(0, $emptyBoard->json('board.load_rate'));
        $this->assertNotEmpty($emptyBoard->json('board.trend.labels'));
        $this->assertNotEmpty($emptyBoard->json('board.trend.series'));
        $this->assertSame(0, array_sum($emptyBoard->json('board.trend.series.0.values')));
        $this->assertContains(0, $emptyBoard->json('board.kinds.values'));

        $this->withToken($token)->getJson('/api/observatory/sites')
            ->assertOk()
            ->assertJsonFragment(['id' => $colegio->id, 'name' => 'IE Santa Librada']);

        $created = $this->withToken($token)->postJson('/api/observatory/reports', [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Integración de Secretaría reporta un hurto en el muro.',
        ]);
        $created->assertCreated()
            ->assertJsonPath('report.merged', false);
        $folio = $created->json('report.folio');
        $eventId = (int) $created->json('report.event_id');

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertSame('api', $report->source->value);
        $this->assertSame('integracion', $report->reporter_role->value);

        $this->withToken($token)->getJson('/api/observatory/events?status=nuevo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', $folio);

        $this->withToken($token)->getJson('/api/observatory/events/'.$eventId)
            ->assertOk()
            ->assertJsonPath('event.reports.0.source', 'api');

        $this->withToken($token)->postJson('/api/observatory/reports', [
            'installation_id' => $colegio->id,
            'kind' => 'hurto',
            'body' => 'Segundo aviso del mismo hurto en menos de una hora.',
        ])->assertCreated()->assertJsonPath('report.merged', true);

        $this->assertSame(1, ObservatoryEvent::query()->count());
        $this->assertSame(2, ObservatoryReport::query()->count());
    }

    public function test_company_reads_and_cannot_write(): void
    {
        [$client, $colegio] = $this->sites();
        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $colegio->id,
            'kind' => 'rina',
            'body' => 'Riña en el patio vista desde la comunidad.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $token = $this->token('empresa@sj-seguridad.test', 'Empresa123!');

        $this->withToken($token)->getJson('/api/observatory/events')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($token)->postJson('/api/observatory/reports', [
            'installation_id' => $colegio->id,
            'kind' => 'otro',
            'body' => 'La empresa no debe crear reportes por esta API.',
        ])->assertForbidden();
    }

    public function test_site_admin_is_scoped_and_guard_is_denied(): void
    {
        [$client, $colegio, , $other] = $this->sites();
        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $rector = $this->makeSiteAdmin($company, $client, $colegio, 'api.rector@palmas.test', '1098001771');
        $this->assertSame([$colegio->id], $rector->assignedInstallationIds());

        $this->actingAs($rector, 'sanctum')->getJson('/api/observatory/sites')
            ->assertOk()
            ->assertJsonFragment(['id' => $colegio->id, 'name' => 'IE Santa Librada'])
            ->assertJsonMissing(['name' => 'IE Republica del Peru']);

        $this->actingAs($rector, 'sanctum')->postJson('/api/observatory/reports', [
            'installation_id' => $other->id,
            'kind' => 'otro',
            'body' => 'No puede reportar un colegio que no administra.',
        ])->assertForbidden();

        $this->actingAs($rector, 'sanctum')->postJson('/api/observatory/reports', [
            'installation_id' => $colegio->id,
            'kind' => 'amenaza',
            'body' => 'Rector reporta una amenaza desde su software.',
        ])->assertCreated();

        $this->assertSame('panel', ObservatoryReport::query()->firstOrFail()->source->value);
        $this->assertSame('rector', ObservatoryReport::query()->firstOrFail()->reporter_role->value);

        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        $this->actingAs($guard, 'sanctum')->getJson('/api/observatory/events')->assertForbidden();
    }

    public function test_anonymous_api_report_hides_identity(): void
    {
        [, $colegio] = $this->sites();
        $token = $this->token('admin@palmasdelingenio.test', 'Cliente123!');

        $this->withToken($token)->postJson('/api/observatory/reports', [
            'installation_id' => $colegio->id,
            'kind' => 'otro',
            'body' => 'Denuncia anónima llegada por el software de Secretaría.',
            'is_anonymous' => true,
            'reporter_name' => 'No debe quedar',
            'reporter_phone' => '3000000000',
        ])->assertCreated();

        $report = ObservatoryReport::query()->firstOrFail();
        $this->assertTrue($report->is_anonymous);
        $this->assertNull($report->reporter_name);
        $this->assertNull($report->reporter_phone);
        $this->assertNull($report->reported_by_user_id);
    }

    private function token(string $email, string $password): string
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit',
        ]);
        $login->assertOk();

        return (string) $login->json('token');
    }

    /** @return array{0: Client, 1: Installation, 2: Installation, 3: Installation} */
    private function sites(): array
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $conjunto = Installation::query()->where('client_id', $client->id)->firstOrFail();
        $conjunto->update(['kind' => InstallationKind::Conjunto->value]);

        $colegio = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Santa Librada',
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => '176001000201',
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        $other = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Republica del Peru',
            'kind' => InstallationKind::Colegio->value,
            'dane_code' => '176001000202',
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        return [$client, $colegio, $conjunto, $other];
    }

    private function makeSiteAdmin(User $companyAdmin, Client $client, Installation $site, string $email, string $document): User
    {
        $suffix = substr($document, -4);

        $this->actingAs($companyAdmin)->post(route('company.users.store'), [
            'role' => 'client-installation-admin',
            'origin' => 'external',
            'name' => 'Admin '.$suffix,
            'document_number' => $document,
            'job_title' => 'Rector',
            'email' => $email,
            'username' => 'admin.api.'.$suffix,
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
