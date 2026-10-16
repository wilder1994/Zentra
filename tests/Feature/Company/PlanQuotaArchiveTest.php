<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\CommercialMetal;
use App\Enums\QuotaRequestKind;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\QuotaRequest;
use App\Models\SecurityCompany;
use App\Models\SupervisorPost;
use App\Models\User;
use App\Services\Company\ManageEmployeeService;
use App\Support\Catalog\CompanyEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PlanQuotaArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_limits_follow_highest_metal_plus_extras(): void
    {
        $this->assertSame([1000, 2, 3], [CommercialMetal::Bronce->employeeCap(), CommercialMetal::Bronce->postsPerInstallation(), CommercialMetal::Bronce->employeesPerPost()]);
        $this->assertSame([2000, 5, 5], [CommercialMetal::Plata->employeeCap(), CommercialMetal::Plata->postsPerInstallation(), CommercialMetal::Plata->employeesPerPost()]);
        $this->assertSame([3000, 7, 7], [CommercialMetal::Oro->employeeCap(), CommercialMetal::Oro->postsPerInstallation(), CommercialMetal::Oro->employeesPerPost()]);
        $this->assertSame([5000, 10, 10], [CommercialMetal::Platino->employeeCap(), CommercialMetal::Platino->postsPerInstallation(), CommercialMetal::Platino->employeesPerPost()]);

        $company = new SecurityCompany([
            'max_clients' => 4,
            'indexing_seats' => 1500,
            'observatory_seats' => 12,
            'extra_employees' => 150,
        ]);
        $entitlements = CompanyEntitlements::for($company);

        $this->assertSame(CommercialMetal::Plata, $entitlements->indexingMetal());
        $this->assertSame(CommercialMetal::Plata, $entitlements->metal());
        $this->assertSame(12, $entitlements->installationCapacity());
        $this->assertSame(2150, $entitlements->employeeCap());
        $this->assertSame(5, $entitlements->postsPerInstallation());

        $company->indexing_seats = 8000;
        $this->assertSame(CommercialMetal::Platino, CompanyEntitlements::for($company)->metal());
        $this->assertSame(8150, CompanyEntitlements::for($company)->employeeCap());
        $company->indexing_seats = 1500;

        $company->posts_per_installation = 8;
        $company->employees_per_post = 9;
        $this->assertSame(8, CompanyEntitlements::for($company)->postsPerInstallation());
        $this->assertSame(9, CompanyEntitlements::for($company)->employeesPerPost());
    }

    public function test_posts_per_installation_limit_blocks_with_clear_message(): void
    {
        [$admin, $company, $client, $installation] = $this->pilot();
        $this->capInstallations($company, 5);

        $this->assertSame(2, $installation->supervisorPosts()->where('is_active', true)->count());

        $this->actingAs($admin)
            ->post(route('company.clients.posts.store', $client), [
                'installation_id' => $installation->id,
                'name' => 'Tercer puesto',
                'modality' => 12,
            ])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'permite 2 puestos activos y ya tiene 2'));

        $this->assertNull(SupervisorPost::query()->where('name', 'Tercer puesto')->first());

        $installation->update(['max_posts' => 3, 'max_posts_note' => 'Cobro aparte']);

        $this->actingAs($admin)
            ->post(route('company.clients.posts.store', $client), [
                'installation_id' => $installation->id,
                'name' => 'Tercer puesto',
                'modality' => 12,
            ])
            ->assertSessionHas('success');
    }

    public function test_company_supervision_post_quota_blocks_when_seats_full(): void
    {
        [$admin, $company, $client, $installation] = $this->pilot();
        $used = SupervisorPost::query()
            ->where('is_active', true)
            ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id)->where('has_supervision', true))
            ->count();
        $this->assertGreaterThan(0, $used);

        $company->update([
            'max_supervision_clients' => $used,
            'supervision_package_size' => $used,
            'supervision_package_sku' => 'sup_'.$used,
        ]);
        $installation->update(['max_posts' => $used + 5, 'max_posts_note' => 'Solo tope empresa']);

        $this->actingAs($admin)
            ->post(route('company.clients.posts.store', $client), [
                'installation_id' => $installation->id,
                'name' => 'Puesto sin cupo empresa',
                'modality' => 12,
            ])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'plan de Supervisión cubre '.$used.' puestos'));

        $this->assertNull(SupervisorPost::query()->where('name', 'Puesto sin cupo empresa')->first());
    }

    public function test_employees_per_post_limit_blocks_assignment(): void
    {
        [$admin, $company, $client, $installation] = $this->pilot();
        $this->capInstallations($company, 5);
        $post = $installation->supervisorPosts()->where('is_active', true)->firstOrFail();
        $ids = $this->makeEmployees($company, 4);

        $this->actingAs($admin)
            ->put(route('company.clients.posts.update', [$client, $post]), [
                'installation_id' => $installation->id,
                'name' => $post->name,
                'modality' => 12,
                'is_active' => '1',
                'employee_ids' => $ids,
                'observations' => 'Refuerzo',
            ])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'admite 3 empleados asignados'));

        $this->assertSame(0, $post->employees()->count());
    }

    public function test_employee_cap_blocks_restore_until_extra_granted(): void
    {
        [, $company] = $this->pilot();
        $this->capInstallations($company, 5);
        $cap = CompanyEntitlements::for($company->fresh())->employeeCap();
        $this->assertSame(1000, $cap);

        $active = Employee::query()->where('security_company_id', $company->id)->where('is_active', true)->count();
        $this->makeEmployees($company, $cap - $active);
        $archived = Employee::query()->findOrFail($this->makeEmployees($company, 1)[0]);
        $archived->update(['is_active' => false]);

        try {
            app(ManageEmployeeService::class)->restore($archived->fresh());
            $this->fail('Debió bloquear por cupo de empleados.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tu cupo es de 1000 empleados activos', $e->validator->errors()->first());
        }

        $company->update(['extra_employees' => 1]);
        app(ManageEmployeeService::class)->restore($archived->fresh());
        $this->assertTrue($archived->fresh()->is_active);
    }

    public function test_archive_client_cascades_and_restore_needs_room(): void
    {
        [$admin, $company, $client, $installation] = $this->pilot();
        $used = $company->installationSeatsCount();
        $this->capInstallations($company, $used);

        $this->actingAs($admin)->post(route('company.clients.archive', $client))->assertSessionHas('success');

        $client->refresh();
        $this->assertFalse($client->is_active);
        $this->assertNotNull($client->archived_at);
        $this->assertFalse($installation->fresh()->is_active);
        $this->assertTrue($installation->fresh()->archived_with_parent);
        $this->assertSame(0, SupervisorPost::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->count());
        $this->assertSame($used - 1, $company->fresh()->installationSeatsCount());

        $other = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $this->actingAs($admin)->post(route('company.clients.installations.store', $other), [
            'name' => 'Ocupa el cupo',
            'address' => 'Calle 1 # 2-3',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'latitude' => '3.4300000',
            'longitude' => '-76.5200000',
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('company.clients.restore', $client))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Tu plan es de '.$used.' instalaciones y ya usas '.$used));

        $this->capInstallations($company->fresh(), $used + 1);
        $this->actingAs($admin)->post(route('company.clients.restore', $client))->assertSessionHas('success');

        $this->assertTrue($client->fresh()->is_active);
        $this->assertTrue($installation->fresh()->is_active);
        $this->assertSame(2, SupervisorPost::query()->withoutGlobalScopes()->where('client_id', $client->id)->where('is_active', true)->count());
    }

    public function test_installation_archive_then_remove(): void
    {
        [$admin, , $client, $installation] = $this->pilot();
        $extra = Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'Bodega',
            'is_active' => true,
            'city' => 'Cali',
        ]);

        $this->actingAs($admin)->delete(route('company.clients.installations.destroy', [$client, $extra]))
            ->assertSessionHas('error', 'Archiva la instalación antes de darla de baja.');

        $this->actingAs($admin)->post(route('company.clients.installations.archive', [$client, $extra]))->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('company.clients.installations.destroy', [$client, $extra]))->assertSessionHas('success');
        $this->assertSoftDeleted($extra);

        $this->actingAs($admin)->post(route('company.clients.installations.archive', [$client, $installation]))->assertSessionHas('success');
        $this->assertSame(0, $installation->supervisorPosts()->where('is_active', true)->count());
        $this->actingAs($admin)->post(route('company.clients.installations.restore', [$client, $installation]))->assertSessionHas('success');
        $this->assertSame(2, $installation->supervisorPosts()->where('is_active', true)->count());
    }

    public function test_supervision_counts_posts_not_installations(): void
    {
        [$admin, $company, $client, $installation] = $this->pilot();
        $before = $company->supervisionSeatsCount();
        $this->assertTrue($client->has_supervision);

        Installation::query()->create([
            'client_id' => $client->id,
            'name' => 'Segunda instalación',
            'is_active' => true,
            'city' => 'Cali',
        ]);
        $this->assertSame($before, $company->fresh()->supervisionSeatsCount());

        $installation->update(['max_posts' => 10, 'max_posts_note' => 'Test']);
        $this->capInstallations($company, max(5, $before + 1));

        $this->actingAs($admin)
            ->post(route('company.clients.posts.store', $client), [
                'installation_id' => $installation->id,
                'name' => 'Puesto nuevo cupo',
                'modality' => 12,
            ])
            ->assertSessionHas('success');

        $this->assertSame($before + 1, $company->fresh()->supervisionSeatsCount());
    }

    public function test_quota_request_approved_by_superadmin(): void
    {
        [$admin, $company, , $installation] = $this->pilot();
        $this->capInstallations($company, 5);

        $this->actingAs($admin)->post(route('company.quota-requests.store'), [
            'kind' => QuotaRequestKind::Posts->value,
            'quantity' => 2,
            'installation_id' => $installation->id,
            'message' => 'Nuevo turno nocturno',
        ])->assertSessionHas('success');

        $request = QuotaRequest::query()->firstOrFail();
        $this->assertTrue($request->isPending());

        $super = User::query()->role('super-admin')->firstOrFail();
        $this->actingAs($super)->get(route('admin.companies.show', $company))->assertOk()->assertSee('Nuevo turno nocturno', false);

        $this->actingAs($super)
            ->post(route('admin.companies.quota-requests.approve', [$company, $request]), ['resolution_note' => 'Factura 123'])
            ->assertSessionHas('success');

        $this->assertSame(QuotaRequest::APPROVED, $request->fresh()->status);
        $this->assertSame(4, (int) $installation->fresh()->max_posts);

        $this->actingAs($super)
            ->put(route('admin.companies.quotas.update', $company), [
                'extra_employees' => 50,
                'posts_per_installation' => '',
                'employees_per_post' => 6,
                'quota_note' => 'Acuerdo anual',
            ])->assertSessionHas('success');

        $company->refresh();
        $this->assertSame(50, (int) $company->extra_employees);
        $this->assertNull($company->posts_per_installation);
        $this->assertSame(6, (int) $company->employees_per_post);

        $this->actingAs($admin)->get(route('company.billing.index'))->assertOk()->assertSee('Solicitar ampliación', false);
    }

    /** @return array{0: User, 1: SecurityCompany, 2: Client, 3: Installation} */
    private function pilot(): array
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = $admin->securityCompany;
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $installation = Installation::query()->where('client_id', $client->id)->where('is_client_site', true)->firstOrFail();

        return [$admin, $company, $client, $installation];
    }

    /** @return list<int> */
    private function makeEmployees(SecurityCompany $company, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $template = (array) DB::table('employees')->where('security_company_id', $company->id)->first();
        $this->assertNotEmpty($template, 'La empresa piloto debe tener al menos un empleado.');
        unset($template['id']);

        $ids = [];
        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            $rows = [];
            foreach ($chunk as $n) {
                $suffix = uniqid('', false).$n;
                $rows[] = array_merge($template, [
                    'document_number' => 'T'.$suffix,
                    'email' => 't'.$suffix.'@quota.test',
                    'is_active' => true,
                    'ceased_at' => null,
                ]);
            }
            DB::table('employees')->insert($rows);
        }

        return Employee::query()
            ->where('security_company_id', $company->id)
            ->where('email', 'like', '%@quota.test')
            ->latest('id')
            ->limit($count)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
