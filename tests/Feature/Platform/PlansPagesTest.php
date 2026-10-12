<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\SecurityCompany;
use App\Models\User;
use Tests\TestCase;

final class PlansPagesTest extends TestCase
{
    public function test_admin_plan_pages_render(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.pricing.edit'))
            ->assertOk()
            ->assertSee('Planes')
            ->assertSee('¿Cómo se calcula un plan?')
            ->assertSee('No disponible');

        $this->actingAs($admin)->get(route('admin.companies.show', $company))
            ->assertOk()
            ->assertSee('Precio pactado')
            ->assertSee('Historial de precios');

        $this->actingAs($admin)->get(route('admin.companies.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_price_lock_excludes_company_from_increase(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.companies.price-lock.update', $company), [
                'price_locked' => 1,
                'price_lock_note' => 'Acuerdo firmado',
            ])
            ->assertRedirect();
        $this->assertTrue($company->fresh()->isPriceLocked());

        $this->actingAs($admin)
            ->postJson(route('admin.pricing.increase.preview'), [
                'percent' => 10,
                'effective_on' => now()->addDay()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('locked', 1);

        $this->actingAs($admin)
            ->post(route('admin.pricing.increase.apply'), [
                'percent' => 10,
                'effective_on' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect(route('admin.pricing.edit'));

        $this->assertNull($company->fresh()->pending_reprice_at);
    }

    public function test_public_plans_render_only_available(): void
    {
        $this->seed();

        $this->get(route('planes.index'))
            ->assertOk()
            ->assertSee('Selecciona tu plan o ármalo a tu gusto')
            ->assertSee('Cómo armar tu plan')
            ->assertDontSee('Calcula tu plan')
            ->assertSee('Platino');

        $this->get(route('planes.cart'))->assertOk();
        $this->get('/')->assertOk();
    }
}
