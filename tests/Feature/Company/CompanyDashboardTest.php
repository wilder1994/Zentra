<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_sees_command_center_kpis(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('company.dashboard'));

        $response->assertOk();
        $response->assertSee('Mi empresa');
        $response->assertSee('Mapa de conjuntos');
        $response->assertSee('Cliente');
        $response->assertSee('Instalación');
        $response->assertSee('Cartera de clientes');
        $response->assertSee('Plan activo');
        $response->assertSee('Activos');
        $response->assertSee('Archivados');
        $response->assertSee('Disponibles Accesos');
        $response->assertSee('Disponibles Supervisión');
        $response->assertSee('Resumen de alertas y registros (hoy)');
        $response->assertSee('Novedades');
        $response->assertSee('Correspondencia');
        $response->assertSee('Pánico');
        $response->assertSee('Bloqueos');
        $response->assertSee('Fuerza laboral actual');
        $response->assertSee('Accesos por conjunto (hoy)');
        $response->assertSee('Turnos abiertos');
        $response->assertSee('Revistas mensuales');
        $response->assertSee('Revistas de supervisión (7 días)');
        $response->assertSee('Supervisión de campo (hoy)');
        $response->assertDontSee('Cumplimiento por conjunto');
        $response->assertDontSee('Próximamente');
    }

    public function test_company_dashboard_live_json(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->getJson(route('company.dashboard.live'))->assertUnauthorized();

        $this->actingAs($admin)
            ->getJson(route('company.dashboard.live'))
            ->assertOk()
            ->assertJsonStructure([
                'metrics' => ['access_remaining', 'package_label'],
                'ops' => ['kpis', 'workforce', 'portfolio', 'open_shifts_table', 'revista_monthly', 'revista_week'],
            ]);

        $this->actingAs($admin)
            ->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('data-live-url', false);
    }

    public function test_guard_cannot_access_company_dashboard(): void
    {
        $this->seedWithPilot();

        $guard = User::query()->where('email', 'guardia@control-acceso.test')->first();
        $this->assertNotNull($guard);

        $response = $this->actingAs($guard)->get(route('company.dashboard'));

        $response->assertForbidden();
    }
}
