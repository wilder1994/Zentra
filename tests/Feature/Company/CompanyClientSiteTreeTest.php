<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\Location;
use App\Models\StructureType;
use App\Models\SupervisorPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyClientSiteTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_creates_installation_access_and_post(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($user)->post(route('company.clients.installations.store', $client), [
            'name' => 'Bodega norte',
            'vista' => 'sitio',
            'address' => 'Calle 9 # 10-11',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'latitude' => '3.4516000',
            'longitude' => '-76.5320000',
        ])->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']));

        $installation = Installation::query()
            ->where('client_id', $client->id)
            ->where('name', 'Bodega norte')
            ->firstOrFail();
        $this->assertSame('Cali', $installation->city);
        $this->assertNotNull($installation->latitude);

        $this->actingAs($user)->post(route('company.clients.locations.store', $client), [
            'installation_id' => $installation->id,
            'code' => 'BN-01',
            'name' => 'Vehicular norte',
        ])->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']));

        $this->assertTrue(
            Location::query()
                ->where('client_id', $client->id)
                ->where('code', 'BN-01')
                ->where('installation_id', $installation->id)
                ->exists()
        );

        $this->actingAs($user)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $installation->id,
            'name' => 'Puesto bodega',
            'modality' => 12,
            'vista' => 'sitio',
        ])->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']));

        $post = SupervisorPost::query()
            ->where('client_id', $client->id)
            ->where('name', 'Puesto bodega')
            ->firstOrFail();
        $this->assertSame(12, $post->modality);

        $this->actingAs($user)
            ->get(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->assertOk()
            ->assertSee('Crear')
            ->assertSee('Agregar puesto');

        $this->actingAs($user)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $installation->id,
            'name' => 'Puesto sur',
            'modality' => 24,
            'vista' => 'sitio',
        ])->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']));

        $this->assertSame(2, SupervisorPost::query()->where('installation_id', $installation->id)->count());
    }

    public function test_access_only_client_can_create_shared_post(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $installation = Installation::query()->where('client_id', $client->id)->firstOrFail();
        $employee = Employee::query()
            ->where('security_company_id', $client->security_company_id)
            ->where('document_number', '1144001122')
            ->firstOrFail();

        $this->actingAs($user)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $installation->id,
            'name' => 'Puesto compartido',
            'modality' => 24,
            'employee_ids' => [$employee->id],
            'vista' => 'sitio',
        ])->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']));

        $post = SupervisorPost::query()
            ->where('client_id', $client->id)
            ->where('name', 'Puesto compartido')
            ->firstOrFail();
        $this->assertSame(24, $post->modality);
        $this->assertTrue($post->employees->contains($employee));

        $this->actingAs($user)
            ->get(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->assertOk()
            ->assertSee('Puesto compartido')
            ->assertSee('24 h')
            ->assertSee('Crear')
            ->assertSee('Agregar puesto');

        $this->actingAs($user)
            ->get(route('company.clients.show', [$client, 'vista' => 'puertas']))
            ->assertOk()
            ->assertSee('Instalaciones')
            ->assertSee('Puesto compartido')
            ->assertSee('Crear');
    }

    public function test_employee_cannot_join_a_second_post_from_the_site(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $employee = Employee::query()->where('document_number', '1144001122')->firstOrFail();
        $first = SupervisorPost::query()->where('client_id', $client->id)->where('name', 'Portería principal')->firstOrFail();
        $second = SupervisorPost::query()->where('client_id', $client->id)->where('name', 'Parqueadero')->firstOrFail();

        $first->employees()->sync([$employee->id]);

        $this->actingAs($user)
            ->from(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->put(route('company.clients.posts.update', [$client, $second]), [
                'installation_id' => $second->installation_id,
                'name' => $second->name,
                'modality' => $second->modality,
                'employee_ids' => [$employee->id],
                'is_active' => '1',
                'vista' => 'sitio',
            ])
            ->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->assertSessionHas('error');

        $this->assertFalse($second->fresh()->employees->contains($employee));
        $this->assertTrue($first->fresh()->employees->contains($employee));
    }

    public function test_reassign_moves_employee_between_posts(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $employee = Employee::query()->where('document_number', '1144001122')->firstOrFail();
        $first = SupervisorPost::query()->where('client_id', $client->id)->where('name', 'Portería principal')->firstOrFail();
        $second = SupervisorPost::query()->where('client_id', $client->id)->where('name', 'Parqueadero')->firstOrFail();
        $first->employees()->sync([$employee->id]);

        $this->actingAs($user)
            ->post(route('company.employees.reassign', $employee), [
                'client_id' => $client->id,
                'installation_id' => $second->installation_id,
                'supervisor_post_id' => $second->id,
            ])
            ->assertRedirect(route('company.employees.show', $employee));

        $this->assertFalse($first->fresh()->employees->contains($employee));
        $this->assertTrue($second->fresh()->employees->contains($employee));
        $this->assertSame(1, $employee->fresh()->supervisorPosts()->count());
    }

    public function test_lookup_employees_by_document(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('company.employees.lookup', ['q' => '1144001122']))
            ->assertOk()
            ->assertJsonFragment(['document' => 'CC 1144001122']);
    }

    public function test_same_client_installation_copies_name_and_geo(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->create([
            'security_company_id' => $user->security_company_id,
            'name' => 'SOS Occidente',
            'party_type' => 'legal_entity',
            'legal_name' => 'SOS Occidente S.A.S.',
            'document_type' => 'NIT',
            'tax_id' => '901777888-9',
            'email' => 'sos@test.com',
            'structure_type_id' => StructureType::idByCode((int) $user->security_company_id, 'ph'),
            'slug' => 'sos-occidente',
            'login_suffix' => 'sosoccidente',
            'address' => 'Av 5 # 1-20',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'latitude' => 3.4401,
            'longitude' => -76.5222,
            'is_active' => true,
            'has_access' => true,
            'has_supervision' => true,
        ]);

        $this->actingAs($user)->post(route('company.clients.installations.store', $client), [
            'name' => 'otro nombre',
            'is_client_site' => '1',
            'vista' => 'sitio',
        ])->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']));

        $installation = Installation::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame('SOS Occidente', $installation->name);
        $this->assertTrue($installation->is_client_site);
        $this->assertSame('Av 5 # 1-20', $installation->address);
        $this->assertSame('Cali', $installation->city);
        $this->assertEqualsWithDelta(3.4401, (float) $installation->latitude, 0.0001);
    }

    public function test_installation_without_map_is_rejected(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($user)
            ->from(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->post(route('company.clients.installations.store', $client), [
                'name' => 'Sin pin',
                'vista' => 'sitio',
            ])
            ->assertRedirect(route('company.clients.show', [$client, 'vista' => 'sitio']))
            ->assertSessionHasErrors('latitude');
    }
}
