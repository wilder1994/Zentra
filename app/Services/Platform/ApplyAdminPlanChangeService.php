<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\EvidenceEventType;
use App\Models\SecurityCompany;
use App\Services\Pricing\CompanyPriceHistory;
use App\Services\Tenant\AssignCompanyPackageService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ApplyAdminPlanChangeService
{
    public const WHEN_NOW = 'now';

    public const WHEN_ON_DATE = 'on_date';

    public const WHEN_PERIOD_END = 'period_end';

    public function __construct(
        private readonly AssignCompanyPackageService $assignAccess,
        private readonly AssignCompanySupervisionPackageService $assignSupervision,
        private readonly RecordLifecycleEvidenceService $evidenceService,
        private readonly CompanyPriceHistory $priceHistory,
    ) {}

    /**
     * @return array{applied: bool, effective_at: CarbonImmutable}
     */
    public function execute(
        SecurityCompany $company,
        BillingCycle $cycle,
        PlanSelection $selection,
        string $when,
        ?CarbonImmutable $onDate = null,
    ): array {
        $this->assertPlan($company, $cycle, $selection);
        $effectiveAt = $this->effectiveAt($company, $when, $onDate);

        if ($when === self::WHEN_NOW || $effectiveAt->startOfDay()->lte(CarbonImmutable::now()->startOfDay())) {
            $this->applyNow($company, $cycle, $selection, $effectiveAt);

            return ['applied' => true, 'effective_at' => $effectiveAt];
        }

        $this->schedule($company, $cycle, $selection, $effectiveAt);

        return ['applied' => false, 'effective_at' => $effectiveAt];
    }

    private function applyNow(
        SecurityCompany $company,
        BillingCycle $cycle,
        PlanSelection $selection,
        CarbonImmutable $startsAt,
    ): void {
        $before = $this->priceHistory->snapshot($company);

        $company = $this->assignSupervision->execute($company, $selection->supervisionSku());
        $company = $this->assignAccess->assignProducts($company, $selection->indexing, $selection->observatory);

        if (! $company->sameAccessAs($selection->access, $cycle)) {
            $company = $this->assignAccess->execute($company, $selection->access->sku(), $cycle, $startsAt, $selection->access);
        }

        $company->update([
            'scheduled_package_sku' => null,
            'scheduled_manual_seats' => null,
            'scheduled_hardware_seats' => null,
            'scheduled_supervision_package_sku' => null,
            'scheduled_indexing_seats' => null,
            'scheduled_observatory_seats' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
            'scheduled_change_payment_id' => null,
        ]);

        $this->priceHistory->record($company, $before, 'plan_change', null, null, $startsAt);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeApplied,
            'Cambio de plan aplicado por plataforma',
            [
                'sku' => $selection->access->sku()->value,
                'cycle' => $cycle->value,
                'plan' => $selection->label(),
                'supervision' => $selection->supervisionSku()?->value,
                'started_at' => $startsAt->toIso8601String(),
            ],
            $company->id,
        );
    }

    private function schedule(
        SecurityCompany $company,
        BillingCycle $cycle,
        PlanSelection $selection,
        CarbonImmutable $effectiveAt,
    ): void {
        $payload = [
            'scheduled_change_at' => $effectiveAt,
            'scheduled_supervision_package_sku' => $selection->supervisionSku()?->value ?? 'none',
            'scheduled_indexing_seats' => $selection->indexing,
            'scheduled_observatory_seats' => $selection->observatory,
        ];

        if (! $company->sameAccessAs($selection->access, $cycle)) {
            $payload['scheduled_package_sku'] = $selection->access->sku()->value;
            $payload['scheduled_manual_seats'] = $selection->access->manual;
            $payload['scheduled_hardware_seats'] = $selection->access->hardware;
            $payload['scheduled_billing_cycle'] = $cycle->value;
        }

        $company->update($payload);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeScheduled,
            'Cambio de plan programado por plataforma',
            [
                'to_sku' => $selection->access->sku()->value,
                'to_cycle' => $cycle->value,
                'to_plan' => $selection->label(),
                'supervision' => $selection->supervisionSku()?->value ?? 'none',
                'effective_at' => $effectiveAt->toIso8601String(),
            ],
            $company->id,
        );
    }

    private function effectiveAt(
        SecurityCompany $company,
        string $when,
        ?CarbonImmutable $onDate,
    ): CarbonImmutable {
        return match ($when) {
            self::WHEN_NOW => CarbonImmutable::now(),
            self::WHEN_ON_DATE => $onDate?->startOfDay()
                ?? throw new InvalidArgumentException('Indica la fecha en que aplica el plan.'),
            self::WHEN_PERIOD_END => $company->package_ends_at
                ? CarbonImmutable::parse($company->package_ends_at)
                : throw new InvalidArgumentException('Esta empresa no tiene un periodo vigente para programar al corte.'),
            default => throw new InvalidArgumentException('Elige cuándo aplica el cambio.'),
        };
    }

    private function assertPlan(SecurityCompany $company, BillingCycle $cycle, PlanSelection $selection): void
    {
        if ($selection->isEmpty()) {
            throw new InvalidArgumentException('El plan debe incluir al menos un producto.');
        }

        if ($company->sameAccessAs($selection->access, $cycle)
            && $company->supervision_package_sku === $selection->supervisionSku()
            && $company->sameProductsAs($selection)) {
            throw new InvalidArgumentException('El plan seleccionado es el mismo que el actual.');
        }
    }
}
