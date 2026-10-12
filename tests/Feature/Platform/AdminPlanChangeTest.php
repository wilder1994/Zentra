<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\CompanyPackageSku;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class AdminPlanChangeTest extends TestCase
{
    public function test_super_admin_applies_access_and_supervision_immediately(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.apply', $company), [
                'billing_cycle' => 'monthly',
                'manual_seats' => 8,
                'hardware_seats' => 0,
                'supervision_seats' => 20,
                'apply_when' => 'now',
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertSame(CompanyPackageSku::from('pack_8_manual'), $company->package_sku);
        $this->assertSame(8, (int) $company->max_clients);
        $this->assertSame(SupervisionPackageSku::fromSize(20), $company->supervision_package_sku);
        $this->assertNull($company->scheduled_change_at);
        $this->assertTrue($company->priceChanges()->exists());
    }

    public function test_super_admin_schedules_plan_for_period_end(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();
        $ends = CarbonImmutable::parse($company->package_ends_at);
        $currentSku = $company->package_sku;

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.apply', $company), [
                'billing_cycle' => 'monthly',
                'manual_seats' => 10,
                'hardware_seats' => 0,
                'supervision_seats' => 5,
                'apply_when' => 'period_end',
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertSame($currentSku, $company->package_sku);
        $this->assertSame('pack_10_manual', $company->scheduled_package_sku);
        $this->assertSame('sup_5', $company->scheduled_supervision_package_sku);
        $this->assertTrue($company->scheduled_change_at->equalTo($ends));
    }

    public function test_super_admin_schedules_plan_on_a_date(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();
        $date = CarbonImmutable::now()->addDays(10)->toDateString();
        $currentSup = $company->supervision_package_sku;

        $this->actingAs($admin)
            ->post(route('admin.companies.plan.apply', $company), [
                'billing_cycle' => $company->billing_cycle->value,
                'manual_seats' => $company->package_manual_seats,
                'hardware_seats' => $company->package_hardware_seats,
                'supervision_seats' => 50,
                'apply_when' => 'on_date',
                'effective_on' => $date,
            ])
            ->assertRedirect(route('admin.companies.show', $company));

        $company->refresh();
        $this->assertSame($currentSup, $company->supervision_package_sku);
        $this->assertSame('sup_50', $company->scheduled_supervision_package_sku);
        $this->assertSame($date, $company->scheduled_change_at->toDateString());
    }

    public function test_empty_plan_is_rejected(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'admin@control-acceso.test')->firstOrFail();
        $company = SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.companies.show', $company))
            ->post(route('admin.companies.plan.apply', $company), [
                'billing_cycle' => 'annual',
                'manual_seats' => 0,
                'hardware_seats' => 0,
                'supervision_seats' => 0,
                'apply_when' => 'now',
            ])
            ->assertRedirect(route('admin.companies.show', $company))
            ->assertSessionHasErrors('manual_seats');
    }
}
