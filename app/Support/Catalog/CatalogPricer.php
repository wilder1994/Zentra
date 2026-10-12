<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;

/**
 * Precio por unidad: unidad × (1 − descuento del plan). La unidad es la instalación, salvo Indexación (empleado).
 * El plan sale de la cantidad del producto; al cruzar un rango todas las unidades pasan al nuevo plan.
 * Platino no tiene tope.
 */
final class CatalogPricer
{
    public function __construct(private readonly CatalogSettings $catalog) {}

    public static function make(): self
    {
        return new self(CatalogSettings::current());
    }

    public function netUnit(CommercialProduct $product, CommercialMetal $metal): float
    {
        return round($this->catalog->unit($product->value) * (1 - $this->catalog->discount($product, $metal)), 2);
    }

    /** Precio del plan lleno (tope del rango; Platino al tope del pack o de empleados). */
    public function packMonthly(CommercialProduct $product, CommercialMetal $metal): float
    {
        return round($this->netUnit($product, $metal) * $this->packSize($product, $metal), 2);
    }

    public function seatsMonthly(CommercialProduct $product, int $seats): float
    {
        $metal = CommercialMetal::forProduct($product, $seats);
        if ($metal === null) {
            return 0.0;
        }

        return round($this->netUnit($product, $metal) * $seats, 2);
    }

    private function packSize(CommercialProduct $product, CommercialMetal $metal): int
    {
        return $product->countsEmployees() ? $metal->employeeCap() : $metal->packSize();
    }

    public function applyCycle(float $monthly, BillingCycle $cycle): float
    {
        if ($cycle !== BillingCycle::Annual) {
            return $monthly;
        }

        $annualPct = (float) config('tenancy.pricing.annual_discount', 0.17);

        return round($monthly * 12 * (1 - $annualPct), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function matrix(CommercialProduct $product, BillingCycle $cycle, bool $onlyAvailable = false): array
    {
        $unit = $this->catalog->unit($product->value);
        $rows = [];

        foreach (CommercialMetal::cases() as $metal) {
            $available = $this->catalog->isMetalAvailable($metal);
            if ($onlyAvailable && ! $available) {
                continue;
            }

            $net = $this->netUnit($product, $metal);
            $monthly = $this->packMonthly($product, $metal);
            $from = round($net * $metal->rangeMinFor($product), 2);
            $list = $unit * $this->packSize($product, $metal);
            $rows[] = [
                'metal' => $metal->value,
                'label' => $metal->label(),
                'range' => $metal->rangeLabelFor($product),
                'unit_label' => $product->unitLabel(1),
                'min' => $metal->rangeMinFor($product),
                'pack' => $this->packSize($product, $metal),
                'employees' => $metal->employeeCap(),
                'posts' => $metal->postsPerInstallation(),
                'per_post' => $metal->employeesPerPost(),
                'discount' => $this->catalog->discount($product, $metal),
                'unit' => $unit,
                'unit_net' => $net,
                'unit_amount' => $this->applyCycle($net, $cycle),
                'from_monthly' => $from,
                'from_amount' => $this->applyCycle($from, $cycle),
                'list_monthly' => $list,
                'price_monthly' => $monthly,
                'price_annual' => $this->applyCycle($monthly, BillingCycle::Annual),
                'amount' => $this->applyCycle($monthly, $cycle),
                'savings' => max(0, $list - $monthly),
                'available' => $available,
                'modules' => $this->catalog->modules($product),
            ];
        }

        return $rows;
    }

    public function catalog(): CatalogSettings
    {
        return $this->catalog;
    }
}
