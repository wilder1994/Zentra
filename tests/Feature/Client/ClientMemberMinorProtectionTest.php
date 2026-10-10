<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\MemberType;
use App\Models\Resident;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Models\User;
use App\Support\Privacy\MinorPersonalData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientMemberMinorProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_form_asks_document_type_and_birth_date(): void
    {
        [$client, $admin] = $this->palmasAdmin();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.members.create'))
            ->assertOk()
            ->assertSee('Tipo de documento')
            ->assertSee('Fecha de nacimiento')
            ->assertSee('Tarjeta de identidad')
            ->assertSee('Registro civil');
    }

    public function test_minor_requires_legal_acceptance_and_is_excluded_from_export(): void
    {
        [$client, $admin] = $this->palmasAdmin();
        $payload = $this->memberPayload($client, [
            'first_name' => 'Ana',
            'last_name' => 'Menor',
            'document_type' => 'TI',
            'document_number' => '1099111222',
            'birth_date' => now()->subYears(12)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->from(route('client.members.create'))
            ->post(route('client.members.store'), $payload)
            ->assertSessionHasErrors('minor_treatment_accepted');

        $payload['minor_treatment_accepted'] = '1';
        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->post(route('client.members.store'), $payload)
            ->assertRedirect();

        $member = StructureMember::query()->where('document_number', '1099111222')->firstOrFail();
        $this->assertTrue($member->isMinor());
        $this->assertNotNull($member->minor_treatment_accepted_at);
        $this->assertFalse($member->has_app_access);

        $this->assertFalse(
            StructureMember::query()->shareable()->where('document_number', '1099111222')->exists()
        );

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.members.export'))
            ->assertOk();
    }

    public function test_client_admin_sees_minor_document_but_vigilante_does_not_at_door(): void
    {
        [$client, $admin] = $this->palmasAdmin();
        $minor = StructureMember::query()
            ->where('client_id', $client->id)
            ->where('document_number', '1099000001')
            ->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.members.show', $minor))
            ->assertOk()
            ->assertSee('1099000001')
            ->assertSee(MinorPersonalData::notice(), false);

        $vigilante = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();
        Resident::query()->create([
            'client_id' => $client->id,
            'document_type' => 'TI',
            'document_number' => '1099000001',
            'first_name' => 'Menor',
            'last_name' => 'Piloto',
            'birth_date' => now()->subYears(12)->toDateString(),
            'is_active' => true,
        ]);

        $json = $this->actingAs($vigilante)
            ->withSession($this->porteriaSession($client))
            ->getJson(route('access.residents.search.json', ['q' => 'Menor']))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($json);
        $this->assertSame('Menor', $json[0]['first_name']);
        $this->assertSame(MinorPersonalData::RESERVED, $json[0]['document_number']);
        $this->assertSame(MinorPersonalData::RESERVED, $json[0]['document_type']);
    }

    /** @return array{0: Client, 1: User} */
    private function palmasAdmin(): array
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        return [$client, $admin];
    }

    /** @param array<string, mixed> $overrides */
    private function memberPayload(Client $client, array $overrides = []): array
    {
        $structure = Structure::query()
            ->where('client_id', $client->id)
            ->where('code', 'TORRE-A-001')
            ->firstOrFail();
        $type = MemberType::query()->where('client_id', $client->id)->firstOrFail();

        return array_merge([
            'structure_id' => $structure->id,
            'member_type_id' => $type->id,
            'first_name' => 'Luis',
            'last_name' => 'Adulto',
            'document_type' => 'CC',
            'document_number' => '1099333444',
            'birth_date' => '1990-05-12',
        ], $overrides);
    }
}
