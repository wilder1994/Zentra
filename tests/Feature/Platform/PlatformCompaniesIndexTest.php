<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlatformCompaniesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_companies_kpis(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->first();

        $response = $this->actingAs($admin)->get(route('admin.companies.index'));

        $response->assertOk();
        $response->assertSee('Riesgo comercial');
        $response->assertSee('Total empresas');
        $response->assertSee('Clientes');
        $response->assertSee('Suspendidas');
        $response->assertSee('Vencidas');
        $response->assertSee('Activas');
        $response->assertSee('Archivados');
        $response->assertSee('SJ Seguridad');
        $response->assertSee('Accesos');
        $response->assertDontSee('Empresas de seguridad');
        $response->assertDontSee('Cartera por recuperar');
        $response->assertDontSee('Total conjuntos');
    }

    public function test_guard_cannot_access_companies_index(): void
    {
        $this->seedWithPilot();

        $guard = User::query()->where('email', 'guardia@control-acceso.test')->first();

        $response = $this->actingAs($guard)->get(route('admin.companies.index'));

        $response->assertForbidden();
    }
}
