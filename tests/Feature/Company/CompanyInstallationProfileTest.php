<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\InstallationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyInstallationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_custom_profiles_but_not_colegio(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $companyId = (int) $admin->security_company_id;

        $this->actingAs($admin)
            ->get(route('company.installation-profiles.index'))
            ->assertOk()
            ->assertSee('Perfiles de sitio', false)
            ->assertSee('Colegio', false)
            ->assertSee('Clientes / sitio', false);

        $this->actingAs($admin)
            ->post(route('company.installation-profiles.store'), [
                'name' => 'Clínica',
                'requires_dane' => false,
                'observatory_community' => false,
                'is_active' => true,
            ])
            ->assertRedirect(route('company.installation-profiles.index'));

        $profile = InstallationProfile::query()
            ->where('security_company_id', $companyId)
            ->where('name', 'Clínica')
            ->firstOrFail();
        $this->assertSame('clinica', $profile->code);
        $this->assertFalse($profile->is_system);

        $this->actingAs($admin)
            ->put(route('company.installation-profiles.update', $profile), [
                'name' => 'Clínica ambulatoria',
                'requires_dane' => true,
                'observatory_community' => false,
                'is_active' => false,
            ])
            ->assertRedirect(route('company.installation-profiles.index'));

        $this->assertDatabaseHas('installation_profiles', [
            'id' => $profile->id,
            'name' => 'Clínica ambulatoria',
            'requires_dane' => 1,
            'is_active' => 0,
        ]);

        $colegio = InstallationProfile::query()
            ->where('security_company_id', $companyId)
            ->where('code', InstallationProfile::CODE_COLEGIO)
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('company.installation-profiles.update', $colegio), [
                'name' => 'Hack',
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('company.installation-profiles.destroy', $colegio))
            ->assertForbidden();
    }

    public function test_guard_cannot_manage_profiles(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)
            ->get(route('company.installation-profiles.index'))
            ->assertForbidden();
    }
}
