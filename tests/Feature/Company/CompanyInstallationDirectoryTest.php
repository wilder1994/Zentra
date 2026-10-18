<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyInstallationDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_lists_and_searches_installations(): void
    {
        [$admin, $client, $site] = $this->palmas();

        $this->actingAs($admin)
            ->get(route('company.installations.index'))
            ->assertOk()
            ->assertSee('Instalaciones y servicios', false)
            ->assertSee('Todos los clientes', false)
            ->assertSee($site->name, false)
            ->assertSee($client->name, false)
            ->assertDontSee('Buscar por nombre, código, DANE', false);

        $this->actingAs($admin)
            ->get(route('company.installations.index', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Filtrado por cliente', false)
            ->assertSee($site->name, false);

        $other = Client::query()
            ->where('security_company_id', $admin->security_company_id)
            ->whereKeyNot($client->id)
            ->first();
        if ($other !== null) {
            $this->actingAs($admin)
                ->getJson(route('company.sig.live', ['client_id' => $other->id]))
                ->assertOk()
                ->assertJsonMissing(['name' => $site->name]);
        }

        $this->actingAs($admin)
            ->getJson(route('company.sig.live', ['client_id' => $client->id]))
            ->assertOk()
            ->assertJsonStructure([
                'installations' => [['id', 'name', 'client', 'posts_count', 'guards_count', 'posts', 'has_location']],
                'reviews_expected_today',
                'reviews_done_today',
                'installations_without_location',
                'last_novelty_at',
            ])
            ->assertJsonFragment(['name' => $site->name]);
    }

    public function test_client_filter_rejects_unknown_client(): void
    {
        [$admin, , $site] = $this->palmas();

        $this->actingAs($admin)
            ->getJson(route('company.sig.live', ['client_id' => 999999]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('company.installations.index', ['client_id' => 999999]))
            ->assertOk()
            ->assertDontSee('Filtrado por cliente', false)
            ->assertSee($site->name, false);
    }

    public function test_company_creates_installation_with_context_and_rector(): void
    {
        [$admin, $client] = $this->palmas();
        $rector = User::query()->where('email', 'laura.instalacion@palmas.test')->first();
        if ($rector === null) {
            $rector = $this->makeRector($admin, $client);
        }

        $this->actingAs($admin)
            ->get(route('company.installations.create'))
            ->assertOk()
            ->assertSee('Código', false)
            ->assertSee('Comuna', false)
            ->assertSee('Tipo de instalación', false)
            ->assertSee('Contacto en directorio', false);

        $this->actingAs($admin)
            ->post(route('company.installations.store'), [
                'client_id' => $client->id,
                'name' => 'INEM Jorge Isaacs',
                'code' => 'INE-01',
                'commune' => 'Comuna 17',
                'rector_user_id' => $rector->id,
                'address' => 'Calle 5 # 50-00',
                'city' => 'Cali',
                'department' => 'Valle del Cauca',
                'latitude' => '3.4372200',
                'longitude' => '-76.5225000',
            ])
            ->assertRedirect();

        $installation = Installation::query()->where('name', 'INEM Jorge Isaacs')->firstOrFail();
        $this->assertSame('INE-01', $installation->code);
        $this->assertSame('Comuna 09', $installation->commune);
        $this->assertSame('comuna', $installation->area_kind);
        $this->assertSame((int) $rector->id, (int) $installation->rector_user_id);
        $this->assertTrue($rector->assignedInstallations()->where('installations.id', $installation->id)->exists());

        $this->actingAs($admin)
            ->get(route('company.installations.edit', $installation))
            ->assertOk()
            ->assertSee('idescLocked: true', false);

        $this->actingAs($admin)
            ->get(route('company.installations.show', $installation))
            ->assertOk()
            ->assertSee('INEM Jorge Isaacs', false)
            ->assertSee('Calle 5 # 50-00', false)
            ->assertSee('INE-01', false)
            ->assertSee('Comuna 09', false)
            ->assertSee('IDESC Cali', false)
            ->assertSee($rector->name, false)
            ->assertSee('Administrador', false)
            ->assertSee('Apoyo', false)
            ->assertSee('Puestos', false);
    }

    public function test_company_drops_barrio_when_area_does_not_apply(): void
    {
        [$admin, $client] = $this->palmas();

        $this->actingAs($admin)
            ->post(route('company.installations.store'), [
                'client_id' => $client->id,
                'name' => 'Instalación Jamundí',
                'commune' => 'Centro',
                'address' => 'Calle 10 # 1-20',
                'city' => 'Jamundí',
                'department' => 'Valle del Cauca',
                'latitude' => '3.2600000',
                'longitude' => '-76.5400000',
            ])
            ->assertRedirect();

        $installation = Installation::query()->where('name', 'Instalación Jamundí')->firstOrFail();
        $this->assertNull($installation->commune);
        $this->assertSame('none', $installation->area_kind);
    }

    public function test_same_name_is_allowed_for_two_sites(): void
    {
        [$admin, $client] = $this->palmas();
        $payload = [
            'client_id' => $client->id,
            'name' => 'Santa Librada',
            'kind' => 'conjunto',
            'address' => 'Calle 5 # 50-00',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'latitude' => '3.4372200',
            'longitude' => '-76.5225000',
        ];

        $this->actingAs($admin)->post(route('company.installations.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('company.installations.store'), array_merge($payload, [
            'address' => 'Calle 6 # 10-20',
        ]))->assertRedirect();

        $this->assertSame(2, Installation::query()->where('client_id', $client->id)->where('name', 'Santa Librada')->count());
    }

    public function test_colegio_requires_unique_dane(): void
    {
        [$admin, $client] = $this->palmas();
        $base = [
            'client_id' => $client->id,
            'name' => 'INEM Norte',
            'kind' => 'colegio',
            'address' => 'Calle 5 # 50-00',
            'city' => 'Cali',
            'department' => 'Valle del Cauca',
            'latitude' => '3.4372200',
            'longitude' => '-76.5225000',
        ];

        $this->actingAs($admin)
            ->from(route('company.installations.create'))
            ->post(route('company.installations.store'), $base)
            ->assertRedirect(route('company.installations.create'))
            ->assertSessionHasErrors('dane_code');

        $this->actingAs($admin)
            ->post(route('company.installations.store'), array_merge($base, ['dane_code' => '176001000001']))
            ->assertRedirect();

        $this->actingAs($admin)
            ->from(route('company.installations.create'))
            ->post(route('company.installations.store'), array_merge($base, [
                'name' => 'INEM Sur',
                'dane_code' => '176001000001',
            ]))
            ->assertRedirect(route('company.installations.create'))
            ->assertSessionHasErrors('dane_code');
    }

    public function test_guard_cannot_open_directory(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)
            ->get(route('company.installations.index'))
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Client, 2: Installation} */
    private function palmas(): array
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $site = Installation::query()->where('client_id', $client->id)->firstOrFail();

        return [$admin, $client, $site];
    }

    private function makeRector(User $admin, Client $client): User
    {
        $site = Installation::query()->where('client_id', $client->id)->firstOrFail();

        $this->actingAs($admin)->post(route('company.users.store'), [
            'role' => 'client-installation-admin',
            'origin' => 'external',
            'name' => 'Rector Palmas',
            'document_number' => '1098000777',
            'job_title' => 'Rector',
            'email' => 'rector.palmas@palmas.test',
            'username' => 'rector.palmas.7777',
            'password' => 'Cliente123!',
            'password_confirmation' => 'Cliente123!',
            'client_ids' => [$client->id],
            'installation_ids' => [$site->id],
            'is_active' => '1',
        ])->assertRedirect();

        return User::query()->where('email', 'rector.palmas@palmas.test')->firstOrFail();
    }
}
