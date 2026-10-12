<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;

/**
 * Precio por instalación: unidad × (1 − descuento del plan). El plan sale de la cantidad total;
 * al cruzar un rango todas las instalaciones pasan al nuevo plan. Desde 101 se sigue cobrando a precio Platino.
 */
final class CatalogPricer
{
    public function __construct(private readonly CatalogSettings $catalog) {}

    public static function make(): self
    {
        return new self(CatalogSettings::current());
    }

    public function unitKey(CommercialProduct $product, bool $indexingAddon = false): string
    {
        return $product === CommercialProduct::Indexing && $indexingAddon
            ? 'indexing_addon'
            : $product->value;
    }

    public function netUnit(CommercialProduct $product, CommercialMetal $metal, bool $indexingAddon = false): float
    {
        $unit = $this->catalog->unit($this->unitKey($product, $indexingAddon));

        return round($unit * (1 - $this->catalog->discount($product, $metal)), 2);
    }

    /** Precio del plan lleno (tope del rango). */
    public function packMonthly(CommercialProduct $product, CommercialMetal $metal, bool $indexingAddon = false): float
    {
        return round($this->netUnit($product, $metal, $indexingAddon) * $metal->packSize(), 2);
    }

    public function seatsMonthly(CommercialProduct $product, int $seats, bool $indexingAddon = false): float
    {
        if ($seats <= 0) {
            return 0.0;
        }

        return round($this->netUnit($product, CommercialMetal::fromSeats($seats), $indexingAddon) * $seats, 2);
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
    public function matrix(CommercialProduct $product, BillingCycle $cycle, bool $indexingAddon = false, bool $onlyAvailable = false): array
    {
        $unit = $this->catalog->unit($this->unitKey($product, $indexingAddon));
        $rows = [];

        foreach (CommercialMetal::cases() as $metal) {
            $available = $this->catalog->isMetalAvailable($metal);
            if ($onlyAvailable && ! $available) {
                continue;
            }

            $net = $this->netUnit($product, $metal, $indexingAddon);
            $monthly = $this->packMonthly($product, $metal, $indexingAddon);
            $from = round($net * $metal->rangeMin(), 2);
            $list = $unit * $metal->packSize();
            $rows[] = [
                'metal' => $metal->value,
                'label' => $metal->label(),
                'range' => $metal->rangeLabel(),
                'min' => $metal->rangeMin(),
                'pack' => $metal->packSize(),
                'employees' => $metal->employeeCap(),
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

    /**
     * @return list<array<string, mixed>>
     */
    public function observatoryMatrix(BillingCycle $cycle, bool $onlyAvailable = false): array
    {
        $rows = [];
        foreach (CommercialMetal::cases() as $metal) {
            if ($onlyAvailable && ! $this->catalog->isMetalAvailable($metal)) {
                continue;
            }
            $monthly = $this->catalog->observatoryPrice($metal);
            $rows[] = [
                'metal' => $metal->value,
                'label' => $metal->label(),
                'price_monthly' => $monthly,
                'price_annual' => $this->applyCycle($monthly, BillingCycle::Annual),
                'amount' => $this->applyCycle($monthly, $cycle),
            ];
        }

        return $rows;
    }

    public function catalog(): CatalogSettings
    {
        return $this->catalog;
    }
}
