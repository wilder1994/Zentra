<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Services\Pricing\PlanQuoter;
use App\Support\Catalog\CatalogSettings;
use Tests\TestCase;

final class PlanQuoterTest extends TestCase
{
    private function catalog(float $combo = 0.30): CatalogSettings
    {
        $ladder = ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12];

        return new CatalogSettings([
            'units' => [
                'access' => 80_000,
                'access_hardware' => 150_000,
                'supervision' => 80_000,
                'indexing' => 40_000,
                'observatory' => 40_000,
            ],
            'discounts' => [
                'access' => $ladder,
                'supervision' => $ladder,
                'indexing' => $ladder,
                'observatory' => $ladder,
            ],
            'combo_discount' => $combo,
        ]);
    }

    public function test_each_product_uses_its_own_plan_and_extras_get_combo_discount(): void
    {
        // Accesos 5 (Bronce −5%) = 380.000; Supervisión 10 (Plata −8%) = 736.000 → la de mayor valor va completa.
        $quote = app(PlanQuoter::class)->quote(PlanSelection::of(5, 0, 10), BillingCycle::Monthly, $this->catalog());

        $this->assertSame('bronce', $quote['lines']['access']['metal']);
        $this->assertSame('plata', $quote['lines']['supervision']['metal']);
        $this->assertEquals(736_000.0, $quote['lines']['supervision']['monthly']);
        $this->assertFalse($quote['lines']['supervision']['combo']);
        $this->assertEquals(266_000.0, $quote['lines']['access']['monthly']);
        $this->assertTrue($quote['lines']['access']['combo']);

        $this->assertEquals(1_200_000.0, $quote['list_monthly']);
        $this->assertEquals(84_000.0, $quote['plan_savings']);
        $this->assertEquals(114_000.0, $quote['combo_savings']);
        $this->assertEquals(1_002_000.0, $quote['amount']);
        $this->assertEquals(198_000.0, $quote['savings']);
    }

    public function test_annual_discount_is_chained_after_plan_and_combo(): void
    {
        $catalog = $this->catalog();
        $monthly = app(PlanQuoter::class)->quote(PlanSelection::of(5, 0, 10), BillingCycle::Monthly, $catalog);
        $annual = app(PlanQuoter::class)->quote(PlanSelection::of(5, 0, 10), BillingCycle::Annual, $catalog);

        $this->assertEqualsWithDelta($monthly['amount'] * 12 * 0.83, $annual['amount'], 0.05);
        $this->assertEquals(1_200_000.0 * 12, $annual['list_amount']);
        $this->assertEqualsWithDelta($annual['list_amount'] - $annual['amount'], $annual['savings'], 0.05);
    }

    public function test_single_product_has_no_combo_and_observatory_can_go_alone(): void
    {
        $quote = app(PlanQuoter::class)->quote(PlanSelection::of(observatory: 3), BillingCycle::Monthly, $this->catalog());

        $this->assertSame(['observatory'], array_keys($quote['lines']));
        $this->assertEquals(114_000.0, $quote['amount']);
        $this->assertEquals(0.0, $quote['combo_savings']);
    }

    public function test_combo_discount_is_configurable(): void
    {
        $quote = app(PlanQuoter::class)->quote(PlanSelection::of(5, 0, 0, 5), BillingCycle::Monthly, $this->catalog(0.0));

        $this->assertEquals(380_000.0 + 190_000.0, $quote['amount']);
    }
}
