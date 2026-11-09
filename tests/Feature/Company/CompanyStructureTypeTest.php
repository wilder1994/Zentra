<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\StructureType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyStructureTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_structure_types(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $companyId = (int) $admin->security_company_id;

        $this->actingAs($admin)
            ->get(route('company.structure-types.index'))
            ->assertOk()
            ->assertSee('Estructuras')
            ->assertDontSee('Unidad ocupable');

        $this->actingAs($admin)
            ->post(route('company.structure-types.store'), [
                'name' => 'Clínica',
                'is_active' => true,
            ])
            ->assertRedirect(route('company.structure-types.index'));

        $type = StructureType::query()
            ->where('security_company_id', $companyId)
            ->where('name', 'Clínica')
            ->firstOrFail();
        $this->assertSame('clinica', $type->code);

        $this->actingAs($admin)
            ->put(route('company.structure-types.update', $type), [
                'name' => 'Clínica ambulatoria',
                'is_active' => false,
            ])
            ->assertRedirect(route('company.structure-types.index'));

        $this->assertDatabaseHas('structure_types', [
            'id' => $type->id,
            'security_company_id' => $companyId,
            'code' => 'clinica',
            'name' => 'Clínica ambulatoria',
            'is_active' => 0,
        ]);
    }

    public function test_guard_cannot_manage_structure_types(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)->get(route('company.structure-types.index'))->assertForbidden();
    }

    public function test_platform_settings_no_longer_lists_structure_types(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.settings.document-types.index'))
            ->assertOk()
            ->assertDontSee('Tipos de estructura');
    }
}
