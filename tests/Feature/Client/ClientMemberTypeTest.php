<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Domain\Tenant\Data\CreateClientData;
use App\Models\Client;
use App\Models\MemberType;
use App\Models\User;
use App\Services\Client\ManageClientMemberTypeService;
use App\Services\Tenant\CreateClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientMemberTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_admin_can_manage_person_types(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.members.index'))
            ->assertOk()
            ->assertSee('Tipos de persona')
            ->assertSee('Propietario');

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->post(route('client.settings.member-types.store'), [
                'name' => 'Contratista',
                'is_active' => true,
            ])
            ->assertRedirect(route('client.members.index'));

        $type = MemberType::query()
            ->where('client_id', $client->id)
            ->where('name', 'Contratista')
            ->firstOrFail();

        $this->assertSame('contratista', $type->slug);

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->put(route('client.settings.member-types.update', $type), [
                'name' => 'Contratista externo',
                'is_active' => false,
            ])
            ->assertRedirect(route('client.members.index'));

        $this->assertDatabaseHas('member_types', [
            'id' => $type->id,
            'name' => 'Contratista externo',
            'is_active' => 0,
        ]);
    }

    public function test_type_can_be_created_inline_as_json(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.members.create'))
            ->assertOk()
            ->assertSee('+ Nuevo tipo');

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->postJson(route('client.settings.member-types.store'), ['name' => 'Funcionario'])
            ->assertCreated()
            ->assertJsonPath('name', 'Funcionario');

        $this->assertDatabaseHas('member_types', ['client_id' => $client->id, 'name' => 'Funcionario']);
    }

    public function test_new_client_gets_default_person_types(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $newClient = app(CreateClientService::class)->execute(new CreateClientData(
            securityCompanyId: (int) $client->security_company_id,
            name: 'Conjunto Tipos Default',
            partyType: $client->party_type,
            legalName: null,
            documentType: null,
            taxId: null,
            email: null,
            phone: null,
            representativeName: null,
            representativeEmail: null,
            structureTypeId: (int) $client->structure_type_id,
            isActive: false,
        ));

        $names = MemberType::query()->withoutGlobalScopes()
            ->where('client_id', $newClient->id)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();

        $this->assertSame(ManageClientMemberTypeService::DEFAULT_NAMES, $names);
    }

    public function test_cannot_delete_type_in_use(): void
    {
        $this->seedWithPilot();

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $type = MemberType::query()
            ->where('client_id', $client->id)
            ->where('name', 'Propietario')
            ->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->from(route('client.members.index'))
            ->delete(route('client.settings.member-types.destroy', $type))
            ->assertRedirect(route('client.members.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('member_types', ['id' => $type->id]);
    }
}
