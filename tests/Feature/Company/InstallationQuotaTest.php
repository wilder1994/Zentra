<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InstallationQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_installation_when_quota_is_full(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = $admin->securityCompany;
        $this->capInstallations($company, max(1, $company->installationSeatsCount()));
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('company.installations.store'), [
                'client_id' => $client->id,
                'name' => 'Instalación extra',
                'address' => 'Calle 1 # 2-3',
                'city' => 'Cali',
                'department' => 'Valle del Cauca',
                'latitude' => '3.4300000',
                'longitude' => '-76.5200000',
            ])
            ->assertSessionHasErrors('package');

        $this->assertNull(Installation::query()->where('name', 'Instalación extra')->first());
    }

    public function test_archive_frees_seat_and_reactivate_needs_one(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $company = $admin->securityCompany;
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $site = Installation::query()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->firstOrFail();

        $this->capInstallations($company, max(1, $company->installationSeatsCount()));

        $this->actingAs($admin)
            ->put(route('company.installations.update', $site), [
                'client_id' => $client->id,
                'name' => $site->name,
                'address' => $site->address,
                'city' => $site->city ?: 'Cali',
                'department' => $site->department ?: 'Valle del Cauca',
                'latitude' => (string) $site->latitude,
                'longitude' => (string) $site->longitude,
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($site->fresh()->is_active);
        $this->assertSame(1, $company->fresh()->installationSeatsRemaining());

        $this->actingAs($admin)
            ->post(route('company.installations.store'), [
                'client_id' => $client->id,
                'name' => 'Instalación nueva',
                'address' => 'Calle 8 # 9-10',
                'city' => 'Cali',
                'department' => 'Valle del Cauca',
                'latitude' => '3.4310000',
                'longitude' => '-76.5210000',
            ])
            ->assertRedirect();

        $this->assertNotNull(Installation::query()->where('name', 'Instalación nueva')->first());

        $this->actingAs($admin)
            ->put(route('company.installations.update', $site), [
                'client_id' => $client->id,
                'name' => $site->name,
                'address' => $site->address,
                'city' => $site->city ?: 'Cali',
                'department' => $site->department ?: 'Valle del Cauca',
                'latitude' => (string) $site->latitude,
                'longitude' => (string) $site->longitude,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('package');
    }
}
