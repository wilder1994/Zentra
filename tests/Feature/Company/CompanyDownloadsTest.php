<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyDownloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_open_downloads(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('company.downloads.index'))
            ->assertOk()
            ->assertSee('Descargas')
            ->assertSee('App de Supervisión')
            ->assertSee('zentra_supervision.test')
            ->assertSee('Descargar APK')
            ->assertSee('Abrir web')
            ->assertDontSee('API de Zentra');
    }

    public function test_guard_cannot_open_company_downloads(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)->get(route('company.downloads.index'))->assertForbidden();
    }

    public function test_company_admin_can_download_supervision_apk(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $response = $this->actingAs($user)->get(route('company.downloads.apk'));
        $response->assertOk();
        $this->assertStringContainsString(
            'zentra-supervision.apk',
            (string) $response->headers->get('content-disposition'),
        );
    }
}
