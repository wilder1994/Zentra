<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Models\SecurityCompany;
use App\Support\Catalog\CatalogSettings;

/**
 * Guarda en la empresa el valor de cada producto ya con descuento de plan y por combinar.
 */
final class CompanyPlanPricer
{
    public function __construct(private readonly PlanQuoter $quoter) {}

    /** @return array<string, mixed> */
    public function fields(SecurityCompany $company, ?CatalogSettings $catalog = null, ?PlanSelection $selection = null): array
    {
        $selection ??= PlanSelection::fromCompany($company);
        $quote = $this->quoter->quote($selection, $company->billing_cycle ?? BillingCycle::Monthly, $catalog);
        $line = fn (string $key): ?array => $quote['lines'][$key] ?? null;

        $access = $line('access');
        $supervision = $line('supervision');
        $indexing = $line('indexing');
        $observatory = $line('observatory');

        return [
            'unit_price_snapshot' => $access['unit'] ?? null,
            'volume_discount_pct' => $access['plan_discount'] ?? 0,
            'annual_discount_pct' => $quote['annual_discount'],
            'package_price_monthly' => $access['monthly'] ?? null,
            'package_price_annual' => $access['annual'] ?? null,
            'supervision_unit_price_snapshot' => $supervision['unit'] ?? null,
            'supervision_package_price_monthly' => $supervision['monthly'] ?? null,
            'supervision_package_price_annual' => $supervision['annual'] ?? null,
            'indexing_price_monthly' => $indexing['monthly'] ?? null,
            'indexing_price_annual' => $indexing['annual'] ?? null,
            'observatory_price_monthly' => $observatory['monthly'] ?? null,
            'observatory_price_annual' => $observatory['annual'] ?? null,
        ];
    }

    public function sync(SecurityCompany $company): SecurityCompany
    {
        $company->update($this->fields($company));

        return $company->fresh();
    }

    public function amount(SecurityCompany $company, ?CatalogSettings $catalog = null): float
    {
        return $this->quoter->amount(
            PlanSelection::fromCompany($company),
            $company->billing_cycle ?? BillingCycle::Monthly,
            $catalog,
        );
    }
}
