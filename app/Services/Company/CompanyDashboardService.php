<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CompanyPackageSku;
use App\Enums\PackageModality;
use App\Models\SecurityCompany;
use App\Repositories\ClientRepository;
use App\Services\Pricing\PriceCalculator;
use App\Support\Catalog\CatalogSettings;

final class CompanyDashboardService
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly CompanyDashboardAnalytics $analytics,
        private readonly PriceCalculator $priceCalculator,
        private readonly BuildFieldSupervisionStripService $fieldSupervisionStrip,
    ) {}

    /** @return array<string, mixed> */
    public function build(SecurityCompany $company): array
    {
        $companyId = (int) $company->id;
        $metrics = $this->clientRepository->metricsForCompany($companyId);
        $ops = $this->analytics->build($company);

        $modality = PackageModality::tryFrom((string) $metrics['package_modality'])
            ?? PackageModality::Manual;
        $currentSize = (int) $metrics['max_clients'];
        $catalog = CatalogSettings::current();
        $upgradeSizes = collect(CommercialMetal::cases())
            ->filter(static fn (CommercialMetal $metal) => $catalog->isMetalAvailable($metal))
            ->map(static fn (CommercialMetal $metal) => $metal->rangeMin())
            ->filter(static fn (int $size) => $size > $currentSize)
            ->values()
            ->all();

        $upgradeQuotes = [];
        foreach ($upgradeSizes as $index => $size) {
            $monthly = $this->priceCalculator->quote($modality, $size, BillingCycle::Monthly);
            $annual = $this->priceCalculator->quote($modality, $size, BillingCycle::Annual);
            $upgradeQuotes[] = [
                'size' => $size,
                'label' => CompanyPackageSku::fromParts($size, $modality)->label(),
                'monthly' => $monthly,
                'annual' => $annual,
                'recommended' => $index === 0,
            ];
        }

        $annualForCurrent = $this->priceCalculator->quote($modality, max(1, $currentSize), BillingCycle::Annual);

        return [
            'company' => $company,
            'metrics' => $metrics,
            'ops' => $ops,
            'upgradeQuotes' => $upgradeQuotes,
            'annualForCurrent' => $annualForCurrent,
            'fieldSupervision' => $this->fieldSupervisionStrip->forToday($company),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function live(SecurityCompany $company): array
    {
        $full = $this->build($company);
        $metrics = $full['metrics'] ?? [];
        $ops = $full['ops'] ?? [];

        return [
            'metrics' => [
                'clients_remaining' => $metrics['clients_remaining'] ?? ($ops['portfolio']['available'] ?? 0),
                'supervision_remaining' => $metrics['supervision_remaining'] ?? 0,
                'supervision_remaining_label' => $metrics['supervision_remaining_label'] ?? ($metrics['supervision_remaining'] ?? 0),
                'package_label' => $metrics['package_label'] ?? '—',
            ],
            'ops' => [
                'kpis' => $ops['kpis'] ?? [],
                'workforce' => $ops['workforce'] ?? [],
                'portfolio' => $ops['portfolio'] ?? [],
                'revista_monthly' => $ops['revista_monthly'] ?? ['labels' => [], 'done' => [], 'expected' => [], 'pending' => []],
                'revista_week' => $ops['revista_week'] ?? ['labels' => [], 'done' => [], 'expected' => [], 'pending' => []],
                'access_by_client' => $ops['access_by_client'] ?? [],
                'open_shifts_table' => $ops['open_shifts_table'] ?? [],
            ],
            'field' => $full['fieldSupervision'],
        ];
    }
}
