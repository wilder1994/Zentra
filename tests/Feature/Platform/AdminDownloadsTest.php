<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminDownloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_downloads(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.downloads.index'))
            ->assertOk()
            ->assertSee('Descargas')
            ->assertSee('App de Supervisión')
            ->assertSee('zentra_supervision.test')
            ->assertSee('Descargar APK')
            ->assertSee('Abrir web');
    }

    public function test_company_admin_cannot_open_platform_downloads(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($user)->get(route('admin.downloads.index'))->assertForbidden();
    }
}
