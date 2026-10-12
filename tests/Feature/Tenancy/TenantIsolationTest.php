<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Installation;
use App\Models\Location;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_only_sees_locations_for_active_client(): void
    {
        $this->seedWithPilot();

        $company = SecurityCompany::query()->first();
        $this->assertNotNull($company);

        $clientA = Client::query()->where('slug', 'palmas-del-ingenio')->first();
        $clientB = Client::query()->where('slug', 'torres-loma')->first();

        $site = Installation::query()
            ->where('client_id', $clientB->id)
            ->orderBy('id')
            ->first();

        if ($site === null) {
            $site = Installation::query()->create([
                'client_id' => $clientB->id,
                'name' => 'Instalación Torres',
                'is_client_site' => true,
                'is_active' => true,
            ]);
        }

        Location::query()->create([
            'client_id' => $clientB->id,
            'installation_id' => $site->id,
            'code' => 'PORT-B',
            'name' => 'Portería Torres',
            'type' => 'porteria',
            'is_active' => true,
        ]);

        $guard = User::query()->where('email', 'guardia@control-acceso.test')->first();
        $this->assertNotNull($guard);

        $context = app(TenantContext::class);
        $context->setClientId($clientA->id);

        $visibleCodes = Location::query()->pluck('code')->all();

        $this->assertContains('PA-01', $visibleCodes);
        $this->assertNotContains('PORT-B', $visibleCodes);
    }

    public function test_company_admin_can_access_company_panel(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('company.dashboard'));

        $response->assertOk();
        $response->assertSee('Mi empresa');
        $response->assertSee('Cartera de clientes');
    }
}
