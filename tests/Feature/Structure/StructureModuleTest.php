<?php

declare(strict_types=1);

namespace Tests\Feature\Structure;

use App\Models\Client;
use App\Models\Installation;
use App\Models\MemberType;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\StructureType;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StructureModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_admin_can_create_structure_with_tenant_scope(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->first();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->first();
        $expectedTypeId = StructureType::idByCode((int) $client->security_company_id, 'ph');

        $this->assertNotNull($client);
        $this->assertNotNull($admin);
        $this->assertSame($expectedTypeId, (int) $client->structure_type_id);

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->orderByDesc('is_client_site')
            ->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->post(route('client.structures.store'), [
                'installation_id' => $installation->id,
                'name' => 'Torre Piloto Test',
                'code' => 'IGNORAR-ESTE-CODIGO',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('client.installations.show', $installation));

        $structure = Structure::withoutGlobalScopes()
            ->where('name', 'Torre Piloto Test')
            ->where('installation_id', $installation->id)
            ->first();

        $this->assertNotNull($structure);
        $this->assertSame('torre-piloto-test', $structure->code);
        $this->assertSame($client->id, $structure->client_id);
        $this->assertSame($installation->id, (int) $structure->installation_id);
        $this->assertSame($expectedTypeId, $structure->structure_type_id);
    }

    public function test_duplicate_structure_name_gets_unique_internal_code(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->first();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->first();
        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->orderByDesc('is_client_site')
            ->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->post(route('client.structures.store'), [
                'installation_id' => $installation->id,
                'name' => 'Tesorería',
            ]);

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->post(route('client.structures.store'), [
                'installation_id' => $installation->id,
                'name' => 'Tesorería',
            ]);

        $codes = Structure::withoutGlobalScopes()
            ->where('installation_id', $installation->id)
            ->where('name', 'Tesorería')
            ->orderBy('id')
            ->pluck('code')
            ->all();

        $this->assertSame(['tesoreria', 'tesoreria-2'], $codes);
    }

    public function test_member_and_vehicle_are_isolated_by_client(): void
    {
        $this->seedWithPilot();

        $clientA = Client::query()->where('slug', 'palmas-del-ingenio')->first();
        $clientB = Client::query()->where('slug', 'torres-loma')->first();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->first();

        $installationB = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $clientB->id)
            ->orderByDesc('is_client_site')
            ->first();

        $structureB = Structure::withoutGlobalScopes()->create([
            'client_id' => $clientB->id,
            'installation_id' => $installationB?->id,
            'name' => 'Apto B1',
            'code' => 'B1-TEST',
            'structure_type_id' => StructureType::idByCode((int) $clientB->security_company_id, 'apartment'),
            'is_active' => true,
        ]);

        $type = MemberType::withoutGlobalScopes()->create([
            'client_id' => $clientB->id,
            'name' => 'Propietario',
            'slug' => 'propietario-b',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        StructureMember::withoutGlobalScopes()->create([
            'client_id' => $clientB->id,
            'structure_id' => $structureB->id,
            'first_name' => 'Otro',
            'last_name' => 'Cliente',
            'document_number' => '999999999',
            'document_type' => 'CC',
            'birth_date' => '1980-01-01',
            'member_type_id' => $type->id,
            'access_code' => 'SECRETB999',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setClientId($clientA->id);

        $visibleDocs = StructureMember::query()->pluck('document_number')->all();

        $this->assertNotContains('999999999', $visibleDocs);
    }

    public function test_client_admin_can_access_structures_index(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->first();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->first();

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->orderByDesc('is_client_site')
            ->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.structures.index'))
            ->assertRedirect(route('client.installations.index'));

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.installations.index'))
            ->assertOk()
            ->assertSee('Instalaciones')
            ->assertSee($installation->name);

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.installations.show', $installation))
            ->assertOk()
            ->assertSee('>Instalaciones</h3>', false)
            ->assertSee('Crear dentro de')
            ->assertDontSee('El censo se arma por instalación')
            ->assertDontSee('name="code"', false);
    }

    public function test_structure_tree_hides_disabled_vehicle_and_pet_modules(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->orderByDesc('is_client_site')
            ->firstOrFail();

        $client->update([
            'panel_modules' => [
                'vehicles' => false,
                'pets' => false,
                'authorizations' => true,
                'doors' => true,
            ],
        ]);

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.installations.show', $installation))
            ->assertOk()
            ->assertSee('personas')
            ->assertDontSee('vehículos')
            ->assertDontSee('mascotas');
    }

    public function test_structure_store_requires_installation(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->first();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->first();

        $response = $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->from(route('client.structures.index'))
            ->post(route('client.structures.store'), [
                'name' => 'Sin instalación',
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('installation_id');
    }

    public function test_pilot_seed_creates_tower_and_members(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->first();

        $structures = Structure::withoutGlobalScopes()->where('client_id', $client->id)->count();
        $members = StructureMember::withoutGlobalScopes()->where('client_id', $client->id)->count();

        $this->assertGreaterThanOrEqual(11, $structures);
        $this->assertGreaterThanOrEqual(20, $members);
    }
}
