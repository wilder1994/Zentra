<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\SupervisionPackageSku;
use App\Models\CommercialPayment;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Platform\ScheduleCompanyPackageChangeService;

final class PurchaseCompanySupervisionPackageService
{
    public function __construct(
        private readonly ScheduleCompanyPackageChangeService $scheduleCompanyPackageChangeService,
    ) {}

    /**
     * Programar Supervisión al corte. Quitar no cobra. Alta/cambio abre checkout.
     */
    public function execute(
        SecurityCompany $company,
        User $actor,
        ?SupervisionPackageSku $sku,
    ): CommercialPayment|SecurityCompany {
        if ($sku === $company->supervision_package_sku) {
            throw new \InvalidArgumentException(
                $sku === null
                    ? 'La empresa ya está sin Supervisión.'
                    : 'El cupo de Supervisión seleccionado es el mismo que el actual.',
            );
        }

        if ($sku === null) {
            return $this->scheduleCompanyPackageChangeService->scheduleSupervisionRemoval($company);
        }

        $current = PlanSelection::fromCompany($company);

        return $this->scheduleCompanyPackageChangeService->scheduleWithLocalCheckout(
            $company,
            $actor,
            $company->billing_cycle ?? BillingCycle::Monthly,
            new PlanSelection($current->access, $sku->size(), $current->indexing, $current->observatory),
        );
    }
}
