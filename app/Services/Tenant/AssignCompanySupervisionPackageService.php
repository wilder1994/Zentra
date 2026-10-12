<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use App\Services\Pricing\CompanyPlanPricer;

final class AssignCompanySupervisionPackageService
{
    public function __construct(
        private readonly CompanyPlanPricer $planPricer,
    ) {}

    public function execute(SecurityCompany $company, ?SupervisionPackageSku $sku): SecurityCompany
    {
        $company->update([
            'supervision_package_sku' => $sku,
            'supervision_package_size' => $sku?->size() ?? 0,
            'supervision_unlimited' => false,
            'max_supervision_clients' => $sku?->seatCap() ?? 0,
        ]);

        return $this->planPricer->sync($company);
    }
}
