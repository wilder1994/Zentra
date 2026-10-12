<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\BillingCycle;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Services\Pricing\PriceCalculator;

final class AssignCompanySupervisionPackageService
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
    ) {}

    public function execute(SecurityCompany $company, ?SupervisionPackageSku $sku): SecurityCompany
    {
        if ($sku === null) {
            $company->update([
                'supervision_package_sku' => null,
                'supervision_package_size' => 0,
                'supervision_unlimited' => false,
                'max_supervision_clients' => 0,
                'supervision_unit_price_snapshot' => null,
                'supervision_package_price_monthly' => null,
                'supervision_package_price_annual' => null,
            ]);

            return $company->fresh();
        }

        $cycle = $company->billing_cycle ?? BillingCycle::Monthly;
        $quote = $this->priceCalculator->quoteSupervisionSku($sku, $cycle);

        $company->update([
            'supervision_package_sku' => $sku,
            'supervision_package_size' => $sku->size(),
            'supervision_unlimited' => false,
            'max_supervision_clients' => $sku->seatCap(),
            'supervision_unit_price_snapshot' => $quote->unitPrice,
            'supervision_package_price_monthly' => $quote->priceMonthly,
            'supervision_package_price_annual' => $quote->priceAnnual,
        ]);

        return $company->fresh();
    }
}
