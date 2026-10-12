<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Support\Catalog\CatalogPricer;
use App\Support\Catalog\CatalogSettings;
use Tests\TestCase;

final class CatalogPricerTest extends TestCase
{
    private function pricer(array $availability = []): CatalogPricer
    {
        return new CatalogPricer(new CatalogSettings([
            'units' => [
                'access' => 100_000,
                'access_hardware' => 150_000,
                'supervision' => 80_000,
                'indexing' => 1_000,
                'observatory' => 40_000,
            ],
            'discounts' => [
                'access' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
                'indexing' => ['bronce' => 0.05, 'plata' => 0.08, 'oro' => 0.10, 'platino' => 0.12],
            ],
            'availability' => $availability,
        ]));
    }

    public function test_every_installation_takes_the_discount_of_the_total(): void
    {
        $pricer = $this->pricer();

        $this->assertEquals(95_000.0, $pricer->netUnit(CommercialProduct::Access, CommercialMetal::Bronce));
        $this->assertEquals(475_000.0, $pricer->seatsMonthly(CommercialProduct::Access, 5));
        // 6 → Plata: las 6 a 92.000
        $this->assertEquals(552_000.0, $pricer->seatsMonthly(CommercialProduct::Access, 6));
        // >100 sigue a precio Platino
        $this->assertEquals(120 * 88_000.0, $pricer->seatsMonthly(CommercialProduct::Access, 120));
    }

    public function test_indexing_matrix_uses_employee_ranges(): void
    {
        $pricer = $this->pricer();
        $rows = $pricer->matrix(CommercialProduct::Indexing, BillingCycle::Monthly);

        $this->assertSame([1, 1001, 2001, 3001], array_column($rows, 'min'));
        $this->assertSame('1.001–2.000 empleados', $rows[1]['range']);
        $this->assertSame('empleado', $rows[1]['unit_label']);
        $this->assertEquals(920.0, $rows[1]['unit_net']);
        $this->assertEquals(1001 * 920.0, $rows[1]['from_monthly']);
        $this->assertEquals(1500 * 920.0, $pricer->seatsMonthly(CommercialProduct::Indexing, 1500));
    }

    public function test_matrix_hides_unavailable_plans_when_requested(): void
    {
        $pricer = $this->pricer(['oro' => false]);

        $all = $pricer->matrix(CommercialProduct::Access, BillingCycle::Monthly);
        $public = $pricer->matrix(CommercialProduct::Access, BillingCycle::Monthly, true);

        $this->assertCount(4, $all);
        $this->assertSame(['bronce', 'plata', 'platino'], array_column($public, 'metal'));
        $this->assertEquals(92_000.0, $public[1]['unit_net']);
        $this->assertEquals(6 * 92_000.0, $public[1]['from_monthly']);
    }
}
