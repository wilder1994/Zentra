<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Domain\Pricing\Data\PriceQuote;
use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Enums\PackageModality;
use App\Enums\SupervisionPackageSku;
use App\Support\Catalog\CatalogSettings;

/**
 * Cotiza Accesos y Supervisión con el catálogo de planes (ver CatalogPricer):
 * cada instalación vale unidad × (1 − descuento del plan que corresponde al total).
 */
final class PriceCalculator
{
    public function quote(
        PackageModality $modality,
        int $size,
        BillingCycle $cycle,
        ?CatalogSettings $catalog = null,
    ): PriceQuote {
        $split = $modality === PackageModality::Hardware
            ? new AccessSeatSplit(0, $size)
            : new AccessSeatSplit($size, 0);

        return $this->quoteAccess($split, $cycle, $catalog);
    }

    public function quoteAccess(
        AccessSeatSplit $split,
        BillingCycle $cycle,
        ?CatalogSettings $catalog = null,
    ): PriceQuote {
        $catalog ??= CatalogSettings::current();
        $manualUnit = $catalog->unit('access');
        $hardwareUnit = $catalog->unit('access_hardware');
        $listMonthly = ($split->manual * $manualUnit) + ($split->hardware * $hardwareUnit);
        $blendedUnit = $split->size() > 0 ? $listMonthly / $split->size() : $manualUnit;

        return $this->quoteFromList(
            $listMonthly,
            $split->size(),
            $cycle,
            $split->modality(),
            $this->discountFor(CommercialProduct::Access, $split->size(), $catalog),
            $catalog,
            round($blendedUnit, 2),
        );
    }

    public function quoteSupervision(
        int $size,
        BillingCycle $cycle,
        ?CatalogSettings $catalog = null,
    ): PriceQuote {
        $catalog ??= CatalogSettings::current();
        $unitPrice = $catalog->unit('supervision');

        return $this->quoteFromList(
            $unitPrice * $size,
            $size,
            $cycle,
            PackageModality::Manual,
            $this->discountFor(CommercialProduct::Supervision, $size, $catalog),
            $catalog,
            $unitPrice,
        );
    }

    public function quoteSupervisionSku(
        SupervisionPackageSku $sku,
        BillingCycle $cycle,
        ?CatalogSettings $catalog = null,
    ): PriceQuote {
        return $this->quoteSupervision($sku->size(), $cycle, $catalog);
    }

    public function discountFor(CommercialProduct $product, int $size, ?CatalogSettings $catalog = null): float
    {
        $metal = CommercialMetal::forSeats($size);
        if ($metal === null) {
            return 0.0;
        }

        return ($catalog ?? CatalogSettings::current())->discount($product, $metal);
    }

    public function volumeDiscountFor(int $size): float
    {
        return $this->discountFor(CommercialProduct::Access, $size);
    }

    private function quoteFromList(
        float $listMonthly,
        int $size,
        BillingCycle $cycle,
        PackageModality $modality,
        float $volumeDiscount,
        CatalogSettings $catalog,
        float $unitPrice,
    ): PriceQuote {
        $annualDiscount = (float) config('tenancy.pricing.annual_discount', 0.17);
        $priceMonthly = round($listMonthly * (1 - $volumeDiscount), 2);
        $annualIfPaidMonthly = round($priceMonthly * 12, 2);
        $priceAnnual = round($annualIfPaidMonthly * (1 - $annualDiscount), 2);
        $effectiveUnit = $size > 0 ? round($priceMonthly / $size, 2) : 0.0;

        return new PriceQuote(
            modality: $modality,
            size: $size,
            cycle: $cycle,
            unitPrice: $unitPrice,
            volumeDiscountPct: $volumeDiscount,
            annualDiscountPct: $annualDiscount,
            priceMonthly: $priceMonthly,
            priceAnnual: $priceAnnual,
            effectiveUnitMonthly: $effectiveUnit,
            listMonthlyWithoutVolume: round($listMonthly, 2),
            annualIfPaidMonthly: $annualIfPaidMonthly,
            annualSavings: round($annualIfPaidMonthly - $priceAnnual, 2),
            currency: $catalog->currency(),
        );
    }
}
