<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryReportType;
use App\Models\User;
use App\Services\Observatory\BuildInstallationRiskService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObservatoryRiskBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_types_are_shared_and_the_board_scores_a_fresh_report(): void
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $other = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $ensure = app(EnsureObservatoryReportTypesService::class);
        $ensure->execute($client);
        $ensure->execute($other);

        $this->assertSame(1, ObservatoryReportType::query()->where('slug', 'amenaza')->count());

        $type = ObservatoryReportType::query()
            ->where('security_company_id', $client->security_company_id)
            ->where('slug', 'hurto')
            ->firstOrFail();
        $type->update(['impacto' => 10, 'urgencia' => 10]);

        $site = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'IE Riesgo Motor',
            'installation_profile_id' => $this->installationProfileId(),
            'dane_code' => '176001009901',
            'is_client_site' => false,
            'is_active' => true,
            'city' => 'Cali',
        ]);

        $this->post(route('observatory.public.store', $client->slug), [
            'installation_id' => $site->id,
            'kind' => 'hurto',
            'body' => 'Hurto de prueba para el motor de riesgo de la sede.',
            'is_anonymous' => '1',
            'reporter_role' => 'padre',
        ])->assertRedirect();

        $board = app(BuildInstallationRiskService::class)->execute((int) $client->security_company_id, (int) $client->id, [(int) $site->id]);
        $row = $board['rows'][0];

        $this->assertSame('IE Riesgo Motor', $row['name']);
        $this->assertSame(25.0, $row['r_installation']);
        $this->assertSame(11.3, $row['r_final']);
        $this->assertSame('BAJO', $row['band']);

        $company = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $this->actingAs($company)
            ->get(route('company.observatory.events.index', ['installation_id' => $site->id]))
            ->assertOk()
            ->assertSee('Riesgo por instalación', false)
            ->assertSee('IE Riesgo Motor', false)
            ->assertSee('11.3', false);

        $this->actingAs($company)
            ->get(route('company.observatory.types.index'))
            ->assertOk()
            ->assertSee('Parametrización de riesgos', false)
            ->assertSee('Catálogo y matriz de delitos y reportes', false);

        $this->actingAs($company)
            ->get(route('company.observatory.types.index', ['seccion' => 'catalogo']))
            ->assertOk()
            ->assertSee('Riesgo exógeno (Comuna)', false)
            ->assertSee('Matriz 10×10 de delitos', false)
            ->assertSee('100.0', false);
    }
}
