<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\SecurityCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_company_from_admin_panel(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.companies.store'), [
            'legal_name' => 'Nueva Seguridad S.A.S.',
            'trade_name' => 'Nueva Seguridad',
            'tax_id' => '901777666-3',
            'party_type' => 'legal_entity',
            'email' => 'contacto@nueva-seguridad.test',
            'phone' => '+57 300 999 0000',
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
            'department' => 'Antioquia',
            'latitude' => 4.71,
            'longitude' => -74.07,
            'manual_seats' => 1,
            'billing_cycle' => 'monthly',
        ]);

        $company = SecurityCompany::query()->where('tax_id', '901777666-3')->firstOrFail();
        $response->assertRedirect(route('admin.companies.first-admin.create', $company));
        $this->assertSame('Nueva Seguridad', $company->trade_name);
        $this->assertSame('pack_1_manual', $company->package_sku?->value);
        $this->assertNull($company->supervision_package_sku);
        $this->assertFalse($company->hasCompanyAdmin());
    }

    public function test_cannot_create_company_without_package(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.companies.create'))
            ->post(route('admin.companies.store'), [
                'legal_name' => 'Sin Paquete S.A.S.',
                'trade_name' => 'Sin Paquete',
                'tax_id' => '901888777-1',
                'party_type' => 'legal_entity',
                'email' => 'sin@paquete.test',
                'phone' => '+57 300 111 2222',
                'address' => 'Calle 1 # 2-3',
                'city' => 'Medellín',
                'department' => 'Antioquia',
                'latitude' => 4.71,
                'longitude' => -74.07,
                'billing_cycle' => 'monthly',
            ])
            ->assertRedirect(route('admin.companies.create'))
            ->assertSessionHasErrors('manual_seats');
    }

    public function test_supervision_only_company_can_be_created(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.companies.create'))
            ->post(route('admin.companies.store'), [
                'legal_name' => 'Con Accesos S.A.S.',
                'trade_name' => 'Con Accesos',
                'tax_id' => '901888777-2',
                'party_type' => 'legal_entity',
                'email' => 'accesos@empresa.test',
                'phone' => '+57 300 111 2222',
                'address' => 'Calle 1 # 2-3',
                'city' => 'Medellín',
                'department' => 'Antioquia',
                'latitude' => 4.71,
                'longitude' => -74.07,
                'supervision_seats' => 3,
                'billing_cycle' => 'monthly',
            ])
            ->assertSessionHasNoErrors();

        $company = SecurityCompany::query()->where('tax_id', '901888777-2')->firstOrFail();
        $this->assertFalse($company->hasAccessPackage());
        $this->assertSame('sup_3', $company->supervision_package_sku?->value);
    }

    public function test_super_admin_creates_company_with_access_and_supervision(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.companies.store'), [
            'legal_name' => 'Accesos y Sup S.A.S.',
            'trade_name' => 'Accesos y Sup',
            'tax_id' => '901888777-3',
            'party_type' => 'legal_entity',
            'email' => 'ambos@empresa.test',
            'phone' => '+57 300 111 2222',
            'address' => 'Calle 1 # 2-3',
            'city' => 'Medellín',
            'department' => 'Antioquia',
            'latitude' => 4.71,
            'longitude' => -74.07,
            'manual_seats' => 10,
            'billing_cycle' => 'monthly',
            'supervision_seats' => 10,
        ]);

        $company = SecurityCompany::query()->where('tax_id', '901888777-3')->firstOrFail();
        $response->assertRedirect(route('admin.companies.first-admin.create', $company));
        $this->assertSame('pack_10_manual', $company->package_sku?->value);
        $this->assertSame('sup_10', $company->supervision_package_sku?->value);
    }

    public function test_show_redirects_to_first_admin_when_company_has_none(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = $this->companyWithoutAdmin('Sin Admin S.A.S.', 'Sin Admin', '901111222-3');

        $this->actingAs($admin)
            ->get(route('admin.companies.show', $company))
            ->assertRedirect(route('admin.companies.first-admin.create', $company));
    }

    public function test_first_admin_form_uses_employee_ficha_without_seeding_catalogs(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = $this->companyWithoutAdmin('Ficha Admin S.A.S.', 'Ficha Admin', '901222333-4');

        $this->actingAs($admin)
            ->get(route('admin.companies.first-admin.create', $company))
            ->assertOk()
            ->assertSee('Apellido paterno')
            ->assertSee('Tipo de colaborador')
            ->assertSee('Departamento de expedición')
            ->assertSee('Crea el primer tipo y el primer cargo')
            ->assertSee('Contraseña temporal');

        $this->assertDatabaseMissing('company_job_titles', [
            'security_company_id' => $company->id,
        ]);
        $this->assertDatabaseMissing('company_collaborator_types', [
            'security_company_id' => $company->id,
        ]);
    }

    public function test_super_admin_can_create_first_type_and_job_title_from_form(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = $this->companyWithoutAdmin('Catalogo Admin S.A.S.', 'Catalogo Admin', '901222444-5');

        $this->actingAs($admin)
            ->postJson(route('admin.companies.first-admin.catalog', $company), [
                'collaborator_type_name' => 'OPERATIVO',
                'job_title_name' => 'Administrador',
            ])
            ->assertOk()
            ->assertJsonPath('collaborator_type.name', 'OPERATIVO')
            ->assertJsonPath('job_title.name', 'Administrador')
            ->assertJsonPath('message', 'Ya puedes seleccionar el tipo y el cargo.');

        $this->assertDatabaseHas('company_job_titles', [
            'security_company_id' => $company->id,
            'name' => 'Administrador',
        ]);
        $this->assertDatabaseHas('company_collaborator_types', [
            'security_company_id' => $company->id,
            'name' => 'OPERATIVO',
        ]);
    }

    public function test_super_admin_creates_first_employee_and_company_admin(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = $this->companyWithoutAdmin('Con Admin S.A.S.', 'Con Admin', '901333444-5');

        $response = $this->actingAs($admin)->post(
            route('admin.companies.first-admin.store', $company),
            $this->firstAdminPayload($company),
        );

        $response->assertRedirect(route('admin.companies.show', $company));
        $response->assertSessionHas('issued_login', 'ana.perez.4821');
        $response->assertSessionHas('issued_password', 'ClaveSegura12');

        $this->assertTrue($company->fresh()->hasCompanyAdmin());
        $this->assertDatabaseHas('employees', [
            'security_company_id' => $company->id,
            'document_number' => '12345678',
            'email' => 'ana.perez@empresa.test',
            'sex' => 'mujer',
            'nationality' => 'COLOMBIANA',
            'birth_department' => 'Antioquia',
            'birth_city' => 'Medellín',
            'document_issue_department' => 'Antioquia',
            'document_issue_city' => 'Medellín',
        ]);
        $user = User::query()->where('username', 'ana.perez.4821')->firstOrFail();
        $this->assertTrue($user->hasRole('company-admin'));
        $this->assertSame($company->id, $user->security_company_id);
        $this->assertTrue($user->must_change_password);
        $this->assertSame($user->employee_id, $user->employee?->id);
    }

    public function test_cannot_create_a_second_first_admin(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = $this->companyWithoutAdmin('Ya Tiene Admin S.A.S.', 'Ya Tiene', '901555666-7');

        $this->actingAs($admin)->post(
            route('admin.companies.first-admin.store', $company),
            $this->firstAdminPayload($company),
        )->assertRedirect(route('admin.companies.show', $company));

        $this->actingAs($admin)
            ->get(route('admin.companies.first-admin.create', $company))
            ->assertRedirect(route('admin.companies.show', $company));
    }

    private function companyWithoutAdmin(string $legalName, string $tradeName, string $taxId): SecurityCompany
    {
        return SecurityCompany::query()->create([
            'legal_name' => $legalName,
            'trade_name' => $tradeName,
            'tax_id' => $taxId,
            'party_type' => 'legal_entity',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function firstAdminPayload(SecurityCompany $company, array $overrides = []): array
    {
        $jobTitle = CompanyJobTitle::query()->firstOrCreate(
            ['security_company_id' => $company->id, 'name' => 'Administrador'],
            ['is_active' => true, 'sort_order' => 10],
        );
        $collaboratorType = CompanyCollaboratorType::query()->firstOrCreate(
            ['security_company_id' => $company->id, 'name' => 'OPERATIVO'],
            ['is_active' => true, 'sort_order' => 10],
        );

        return array_merge([
            'document_type' => 'CC',
            'document_number' => '12345678',
            'last_name_paternal' => 'Pérez',
            'last_name_maternal' => 'Gómez',
            'first_names' => 'Ana María',
            'sex' => 'mujer',
            'birth_date' => '1990-05-12',
            'job_title_id' => $jobTitle->id,
            'collaborator_type_id' => $collaboratorType->id,
            'email' => 'ana.perez@empresa.test',
            'nationality' => 'COLOMBIANA',
            'blood_group' => 'O+',
            'has_disability' => '0',
            'birth_department' => 'Antioquia',
            'birth_city' => 'Medellín',
            'document_issue_department' => 'Antioquia',
            'document_issue_city' => 'Medellín',
            'document_issued_at' => '2010-03-15',
            'username' => 'ana.perez.4821',
            'password' => 'ClaveSegura12',
        ], $overrides);
    }
}
