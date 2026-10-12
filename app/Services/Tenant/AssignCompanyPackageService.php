<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\BillingCycle;
use App\Enums\CompanyPackageSku;
use App\Enums\SubscriptionStatus;
use App\Models\SecurityCompany;
use App\Services\Pricing\CompanyPlanPricer;
use Carbon\CarbonImmutable;

final class AssignCompanyPackageService
{
    public function __construct(
        private readonly CompanyPlanPricer $planPricer,
    ) {}

    /** Asigna Accesos, abre un periodo nuevo y recalcula el valor de todos los productos. */
    public function execute(
        SecurityCompany $company,
        CompanyPackageSku $sku,
        BillingCycle $cycle = BillingCycle::Monthly,
        ?CarbonImmutable $startsAt = null,
        ?AccessSeatSplit $seats = null,
    ): SecurityCompany {
        $seats ??= AccessSeatSplit::fromSku($sku);
        $startsAt ??= CarbonImmutable::now();
        $endsAt = $cycle === BillingCycle::Annual
            ? $startsAt->addYear()
            : $startsAt->addMonth();

        $billingDayMax = max(1, (int) config('subscription.billing_day_max', 28));
        $billingDay = min($billingDayMax, max(1, (int) $startsAt->day));

        $company->update([
            'package_sku' => $seats->sku(),
            'package_size' => $seats->size(),
            'package_manual_seats' => $seats->manual,
            'package_hardware_seats' => $seats->hardware,
            'package_modality' => $seats->modality(),
            'max_clients' => $seats->size(),
            'billing_cycle' => $cycle,
            'billing_day' => $billingDay,
            'package_starts_at' => $startsAt,
            'package_ends_at' => $endsAt,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'archived_at' => null,
            'archive_reason' => null,
            'subscription_status' => SubscriptionStatus::Active,
            'is_active' => true,
            'pending_reprice_at' => null,
            'pending_reprice_reason' => null,
            'pending_reprice_note' => null,
        ]);

        return $this->planPricer->sync($company);
    }

    /** Instalaciones de Indexación y Observatorio; no cambia el periodo. */
    public function assignProducts(SecurityCompany $company, int $indexing, int $observatory): SecurityCompany
    {
        $company->update([
            'indexing_seats' => max(0, $indexing),
            'observatory_seats' => max(0, $observatory),
        ]);

        return $this->planPricer->sync($company);
    }
}
