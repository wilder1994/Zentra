<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\BloodGroup;
use App\Models\Client;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\Employee;
use App\Models\SecurityCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyUserFromEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_employee_and_credentials_fields(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();

        $this->actingAs($admin)
            ->get(route('company.users.create'))
            ->assertOk()
            ->assertSee('Nombre', false)
            ->assertSee('Cédula', false)
            ->assertSee('Usuario de acceso', false)
            ->assertSee('Cargo / función', false)
            ->assertSee('Correo corporativo', false)
            ->assertSee('Generar usuario y contraseña', false)
            ->assertSee('Protección de datos de menores', false);
    }

    public function test_edit_form_uses_the_same_fields(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $user = $this->companySupervisor();

        $this->actingAs($admin)
            ->get(route('company.users.edit', $user))
            ->assertOk()
            ->assertSee('Nombre', false)
            ->assertSee('Cédula', false)
            ->assertSee('Usuario de acceso', false)
            ->assertSee('Cargo / función', false)
            ->assertSee('Correo corporativo', false);
    }

    public function test_can_create_supervisor_from_users_module(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->createEmployee($this->createJobTitle('Supervisor de zona'));

        $preview = $this->actingAs($admin)->postJson(route('company.users.credentials-preview'), [
            'employee_id' => $employee->id,
        ]);
        $preview->assertOk();
        $username = $preview->json('username');
        $password = $preview->json('password');

        $this->actingAs($admin)->get(route('company.users.employee-search', ['q' => '1098']))
            ->assertOk()
            ->assertJsonPath('employees.0.id', $employee->id);

        $response = $this->actingAs($admin)->post(route('company.users.store'), [
            'role' => 'supervisor',
            'employee_id' => $employee->id,
            'job_title' => 'Supervisor de zona',
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('company.users.edit', User::query()->where('employee_id', $employee->id)->first()));
        $response->assertSessionHas('issued_login', $username);
        $response->assertSessionHas('issued_password', $password);
        $user = User::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertTrue($user->hasRole('supervisor'));
        $this->assertSame($username, $user->username);
        $this->assertNull($user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->supervisor_code);
    }

    public function test_internal_client_admin_saves_corporate_email(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->createEmployee($this->createJobTitle('Director de cuenta'));
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $preview = $this->actingAs($admin)->postJson(route('company.users.credentials-preview'), [
            'employee_id' => $employee->id,
        ])->assertOk();

        $this->actingAs($admin)->post(route('company.users.store'), [
            'role' => 'client-admin',
            'origin' => 'internal',
            'employee_id' => $employee->id,
            'job_title' => 'Director de cuenta',
            'corporate_email' => 'director@sj-seguridad.test',
            'client_ids' => [$client->id],
            'username' => $preview->json('username'),
            'password' => $preview->json('password'),
            'password_confirmation' => $preview->json('password'),
        ])->assertRedirect();

        $user = User::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('director@sj-seguridad.test', $user->corporate_email);
        $this->assertNull($user->email);
    }

    public function test_guard_from_users_requires_employee_and_client(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->createEmployee($this->createJobTitle('Portería'));
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($admin)->from(route('company.users.create'))->post(route('company.users.store'), [
            'role' => 'guardia',
            'employee_id' => $employee->id,
            'job_title' => 'Portería',
            'username' => 'ana.perez.2001',
            'password' => 'Clave123!',
            'password_confirmation' => 'Clave123!',
        ])->assertSessionHasErrors('client_ids');

        $this->actingAs($admin)->from(route('company.users.create'))->post(route('company.users.store'), [
            'role' => 'guardia',
            'employee_id' => $employee->id,
            'job_title' => 'Portería',
            'username' => 'ana.perez.2001',
            'password' => 'Clave123!',
            'password_confirmation' => 'Clave123!',
            'client_ids' => [$client->id],
        ])->assertSessionHasErrors('client_ids');

        $this->assignEmployeeToClientPost($employee, $client);

        $this->actingAs($admin)->post(route('company.users.store'), [
            'role' => 'guardia',
            'employee_id' => $employee->id,
            'job_title' => 'Portería',
            'username' => 'ana.perez.2001',
            'password' => 'Clave123!',
            'password_confirmation' => 'Clave123!',
            'client_ids' => [$client->id],
        ])->assertRedirect();

        $user = User::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertTrue($user->hasRole('guardia'));
        $this->assertSame('ana.perez.2001', $user->username);
        $this->assertNull($user->email);
    }

    public function test_cannot_create_user_twice_for_same_employee(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->createEmployee($this->createJobTitle('Supervisor de zona'));

        $this->actingAs($admin)->post(route('company.users.store'), [
            'role' => 'supervisor',
            'employee_id' => $employee->id,
            'job_title' => 'Supervisor de zona',
            'username' => 'ana.perez.1001',
            'password' => 'Clave123!',
            'password_confirmation' => 'Clave123!',
        ])->assertRedirect();

        $this->actingAs($admin)->from(route('company.users.create'))->post(route('company.users.store'), [
            'role' => 'supervisor',
            'employee_id' => $employee->id,
            'job_title' => 'Supervisor de zona',
            'username' => 'ana.perez.1002',
            'password' => 'Clave123!',
            'password_confirmation' => 'Clave123!',
        ])->assertSessionHasErrors('employee_id');
    }

    public function test_can_deactivate_and_reactivate_user(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->createEmployee($this->createJobTitle('Supervisor de zona'));

        $this->actingAs($admin)->post(route('company.users.store'), [
            'role' => 'supervisor',
            'employee_id' => $employee->id,
            'job_title' => 'Supervisor de zona',
            'username' => 'ana.perez.3001',
            'password' => 'Clave123!',
            'password_confirmation' => 'Clave123!',
        ])->assertRedirect();

        $user = User::query()->where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('company.users.deactivate', $user))
            ->assertRedirect(route('company.users.index', ['status' => 'inactive']));

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);

        $this->actingAs($admin)
            ->get(route('company.users.index', ['status' => 'active']))
            ->assertDontSee('ana.perez.3001', false);

        $this->actingAs($admin)
            ->get(route('company.users.index', ['status' => 'inactive']))
            ->assertSee('ana.perez.3001', false);

        $this->actingAs($admin)
            ->post(route('company.users.reactivate', $user))
            ->assertRedirect(route('company.users.index', ['status' => 'active']));

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_cannot_deactivate_own_user(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();

        $this->actingAs($admin)
            ->from(route('company.users.index'))
            ->post(route('company.users.deactivate', $admin))
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_active);
    }

    private function companyAdmin(): User
    {
        return User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
    }

    private function company(): SecurityCompany
    {
        return SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();
    }

    private function createJobTitle(string $name): CompanyJobTitle
    {
        return CompanyJobTitle::query()->firstOrCreate(
            [
                'security_company_id' => $this->company()->id,
                'name' => $name,
            ],
            ['is_active' => true, 'sort_order' => 10],
        );
    }

    private function createEmployee(CompanyJobTitle $title): Employee
    {
        $type = CompanyCollaboratorType::query()->firstOrCreate(
            [
                'security_company_id' => $this->company()->id,
                'name' => 'OPERATIVO',
            ],
            ['is_active' => true, 'sort_order' => 10],
        );

        return Employee::query()->create([
            'security_company_id' => $this->company()->id,
            'job_title_id' => $title->id,
            'collaborator_type_id' => $type->id,
            'document_type' => 'CC',
            'document_number' => '1098765432',
            'last_name_paternal' => 'Pérez',
            'last_name_maternal' => 'Gómez',
            'first_names' => 'Ana',
            'sex' => 'mujer',
            'birth_date' => '1990-05-12',
            'email' => 'ana.perez@sj-seguridad.test',
            'nationality' => 'COLOMBIANA',
            'blood_group' => BloodGroup::OPositive,
            'is_active' => true,
        ]);
    }
}
