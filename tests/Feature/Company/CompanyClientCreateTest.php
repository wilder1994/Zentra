<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\StructureType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyClientCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_open_create_form(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $response = $this->actingAs($user)->get(route('company.clients.create'));

        $response->assertOk();
        $response->assertSee('Nuevo cliente');
        $response->assertSee('Tipo de estructura');
        $response->assertSee('Protección de datos de menores');
        $response->assertSee('Volver al listado');
        $response->assertDontSee('Sufijo login');
        $response->assertDontSee('+ Conjunto');
    }

    public function test_company_admin_can_create_commercial_client(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $companyId = (int) $user->security_company_id;
        $structureTypeId = StructureType::idByCode($companyId, 'ph');

        $response = $this->actingAs($user)->post(route('company.clients.store'), [
            'party_type' => 'legal_entity',
            'name' => 'Residencial Norte',
            'legal_name' => 'Residencial Norte PH',
            'document_type' => 'NIT',
            'tax_id' => '901555666-7',
            'email' => 'contacto@norte.test',
            'phone' => '3001112233',
            'representative_name' => 'María López',
            'representative_email' => 'maria@norte.test',
            'structure_type_id' => $structureTypeId,
            'address' => 'Calle 10 # 20-30',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'service_started_at' => now()->toDateString(),
            'is_active' => '1',
        ]);

        $client = Client::query()->where('tax_id', '901555666-7')->firstOrFail();

        $response->assertRedirect(route('company.clients.show', $client));
        $this->assertSame('Residencial Norte', $client->name);
        $this->assertSame('residencial-norte', $client->slug);
        $this->assertNotEmpty($client->login_suffix);
        $this->assertSame('NIT', $client->document_type);
        $this->assertSame('contacto@norte.test', $client->email);
        $this->assertSame($structureTypeId, (int) $client->structure_type_id);
        $this->assertFalse($client->has_access);
        $this->assertFalse($client->has_supervision);
    }

    public function test_company_admin_can_create_catalog_client_when_access_quota_is_full(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = $user->securityCompany;
        $this->fillAccessLeaveInstallationSeat($company);
        $companyId = (int) $user->security_company_id;
        $structureTypeId = StructureType::idByCode($companyId, 'ph');

        $response = $this->actingAs($user)->post(route('company.clients.store'), [
            'party_type' => 'legal_entity',
            'name' => 'Ficha Extra',
            'legal_name' => 'Ficha Extra PH',
            'document_type' => 'NIT',
            'tax_id' => '901777888-9',
            'email' => 'extra@norte.test',
            'phone' => '3001112233',
            'representative_name' => 'María López',
            'representative_email' => 'maria@norte.test',
            'structure_type_id' => $structureTypeId,
            'address' => 'Calle 10 # 20-30',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'service_started_at' => now()->toDateString(),
            'is_active' => '1',
            'has_access' => '0',
            'has_supervision' => '0',
        ]);

        $client = Client::query()->where('tax_id', '901777888-9')->firstOrFail();
        $response->assertRedirect(route('company.clients.show', $client));
        $this->assertFalse($client->has_access);
    }

    public function test_cannot_enable_access_when_quota_is_full(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = $user->securityCompany;
        $this->fillAccessLeaveInstallationSeat($company);
        $companyId = (int) $user->security_company_id;
        $structureTypeId = StructureType::idByCode($companyId, 'ph');

        $response = $this->actingAs($user)->post(route('company.clients.store'), [
            'party_type' => 'legal_entity',
            'name' => 'Sin cupo Accesos',
            'legal_name' => 'Sin cupo Accesos PH',
            'document_type' => 'NIT',
            'tax_id' => '901999000-1',
            'email' => 'sincupo@norte.test',
            'representative_name' => 'María López',
            'representative_email' => 'maria@norte.test',
            'structure_type_id' => $structureTypeId,
            'has_access' => '1',
        ]);

        $response->assertSessionHasErrors('has_access');
        $this->assertNull(Client::query()->where('tax_id', '901999000-1')->first());
    }

    public function test_cannot_create_client_when_installation_quota_is_full(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = $user->securityCompany;
        $this->capInstallations($company, max(1, $company->installationSeatsCount()));
        $companyId = (int) $user->security_company_id;
        $structureTypeId = StructureType::idByCode($companyId, 'ph');

        $this->actingAs($user)->post(route('company.clients.store'), [
            'party_type' => 'legal_entity',
            'name' => 'Sin cupo instalación',
            'legal_name' => 'Sin cupo instalación PH',
            'document_type' => 'NIT',
            'tax_id' => '901111222-3',
            'email' => 'sincupo.instalacion@norte.test',
            'representative_name' => 'María López',
            'representative_email' => 'maria@norte.test',
            'structure_type_id' => $structureTypeId,
        ])->assertSessionHasErrors('package');

        $this->assertNull(Client::query()->where('tax_id', '901111222-3')->first());
    }

    private function fillAccessLeaveInstallationSeat(SecurityCompany $company): void
    {
        $site = Installation::query()
            ->withoutGlobalScopes()
            ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id))
            ->where('is_active', true)
            ->first();
        $site?->update(['is_active' => false]);
        $company->update(['max_clients' => max(1, $company->accessSeatsCount())]);
    }
}
