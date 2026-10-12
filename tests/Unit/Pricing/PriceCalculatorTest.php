<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\BillingCycle;
use App\Enums\SupervisionPackageSku;
use App\Services\Pricing\PriceCalculator;
use App\Support\Catalog\CatalogSettings;
use Tests\TestCase;

final class PriceCalculatorTest extends TestCase
{
    private function catalog(): CatalogSettings
    {
        return new CatalogSettings([
            'units' => [
                'access' => 80_000,
                'access_hardware' => 150_000,
                'supervision' => 50_000,
                'indexing' => 40_000,
                'observatory' => 40_000,
            ],
            'discounts' => [
                'access' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
                'supervision' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
            ],
        ]);
    }

    public function test_plan_comes_from_the_total_and_reprices_all_installations(): void
    {
        $calculator = app(PriceCalculator::class);

        $five = $calculator->quoteAccess(new AccessSeatSplit(5, 0), BillingCycle::Monthly, $this->catalog());
        $this->assertSame(0.05, $five->volumeDiscountPct);
        $this->assertEquals(380_000.0, $five->priceMonthly);

        $eight = $calculator->quoteAccess(new AccessSeatSplit(8, 0), BillingCycle::Monthly, $this->catalog());
        $this->assertSame(0.08, $eight->volumeDiscountPct);
        $this->assertEquals(8 * 73_600.0, $eight->priceMonthly);

        $over = $calculator->quoteAccess(new AccessSeatSplit(150, 0), BillingCycle::Monthly, $this->catalog());
        $this->assertSame(0.12, $over->volumeDiscountPct);
    }

    public function test_annual_is_twelve_months_minus_annual_discount(): void
    {
        $calculator = app(PriceCalculator::class);
        $monthly = $calculator->quoteAccess(new AccessSeatSplit(10, 0), BillingCycle::Monthly, $this->catalog());
        $annual = $calculator->quoteAccess(new AccessSeatSplit(10, 0), BillingCycle::Annual, $this->catalog());

        $annualPct = (float) config('tenancy.pricing.annual_discount', 0.17);
        $this->assertEqualsWithDelta($monthly->priceMonthly * 12 * (1 - $annualPct), $annual->priceAnnual, 1.0);
        $this->assertGreaterThan(0, $annual->annualSavings);
    }

    public function test_hardware_seats_use_their_own_unit_with_the_same_discount(): void
    {
        $calculator = app(PriceCalculator::class);
        $mix = $calculator->quoteAccess(new AccessSeatSplit(3, 3), BillingCycle::Monthly, $this->catalog());

        $this->assertSame(0.08, $mix->volumeDiscountPct);
        $this->assertEquals(round((3 * 80_000 + 3 * 150_000) * 0.92, 2), $mix->priceMonthly);
    }

    public function test_supervision_is_independent_and_uses_its_own_plan(): void
    {
        $calculator = app(PriceCalculator::class);

        $one = $calculator->quoteSupervision(1, BillingCycle::Monthly, $this->catalog());
        $this->assertEquals(47_500.0, $one->priceMonthly);

        $sixteen = $calculator->quoteSupervisionSku(SupervisionPackageSku::fromSize(16), BillingCycle::Monthly, $this->catalog());
        $this->assertSame(0.10, $sixteen->volumeDiscountPct);
        $this->assertEquals(16 * 45_000.0, $sixteen->priceMonthly);
    }
}
