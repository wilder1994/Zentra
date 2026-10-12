<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Enums\BillingCycle;
use App\Enums\CompanyPackageSku;
use App\Enums\EvidenceEventType;
use App\Enums\SupervisionPackageSku;
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
        CompanyPackageSku $sku,
        BillingCycle $cycle,
        AccessSeatSplit $seats,
        ?SupervisionPackageSku $supervisionSku,
        string $when,
        ?CarbonImmutable $onDate = null,
    ): array {
        $sku = $seats->sku();
        $this->assertPlan($company, $cycle, $seats, $supervisionSku);
        $effectiveAt = $this->effectiveAt($company, $when, $onDate);

        if ($when === self::WHEN_NOW || $effectiveAt->startOfDay()->lte(CarbonImmutable::now()->startOfDay())) {
            $this->applyNow($company, $sku, $cycle, $seats, $supervisionSku, $effectiveAt);

            return ['applied' => true, 'effective_at' => $effectiveAt];
        }

        $this->schedule($company, $sku, $cycle, $seats, $supervisionSku, $effectiveAt);

        return ['applied' => false, 'effective_at' => $effectiveAt];
    }

    private function applyNow(
        SecurityCompany $company,
        CompanyPackageSku $sku,
        BillingCycle $cycle,
        AccessSeatSplit $seats,
        ?SupervisionPackageSku $supervisionSku,
        CarbonImmutable $startsAt,
    ): void {
        $before = $this->priceHistory->snapshot($company);

        $company = $this->assignSupervision->execute($company, $supervisionSku);

        if (! $company->sameAccessAs($seats, $cycle)) {
            $company = $this->assignAccess->execute($company, $sku, $cycle, $startsAt, $seats);
        }

        $company->update([
            'scheduled_package_sku' => null,
            'scheduled_manual_seats' => null,
            'scheduled_hardware_seats' => null,
            'scheduled_supervision_package_sku' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
            'scheduled_change_payment_id' => null,
        ]);

        $this->priceHistory->record($company, $before, 'plan_change', null, null, $startsAt);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeApplied,
            'Cambio de plan aplicado por plataforma',
            [
                'sku' => $sku->value,
                'cycle' => $cycle->value,
                'supervision' => $supervisionSku?->value,
                'started_at' => $startsAt->toIso8601String(),
            ],
            $company->id,
        );
    }

    private function schedule(
        SecurityCompany $company,
        CompanyPackageSku $sku,
        BillingCycle $cycle,
        AccessSeatSplit $seats,
        ?SupervisionPackageSku $supervisionSku,
        CarbonImmutable $effectiveAt,
    ): void {
        $payload = [
            'scheduled_change_at' => $effectiveAt,
            'scheduled_supervision_package_sku' => $supervisionSku?->value ?? 'none',
        ];

        if (! $company->sameAccessAs($seats, $cycle)) {
            $payload['scheduled_package_sku'] = $sku->value;
            $payload['scheduled_manual_seats'] = $seats->manual;
            $payload['scheduled_hardware_seats'] = $seats->hardware;
            $payload['scheduled_billing_cycle'] = $cycle->value;
        }

        $company->update($payload);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeScheduled,
            'Cambio de plan programado por plataforma',
            [
                'to_sku' => $sku->value,
                'to_cycle' => $cycle->value,
                'supervision' => $supervisionSku?->value ?? 'none',
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

    private function assertPlan(
        SecurityCompany $company,
        BillingCycle $cycle,
        AccessSeatSplit $seats,
        ?SupervisionPackageSku $supervisionSku,
    ): void {
        if ($seats->isEmpty() && $supervisionSku === null) {
            throw new InvalidArgumentException('El plan debe incluir Accesos o Supervisión.');
        }

        $sameAccess = $company->sameAccessAs($seats, $cycle);
        $sameSup = $company->supervision_package_sku === $supervisionSku;
        if ($sameAccess && $sameSup) {
            throw new InvalidArgumentException('El plan seleccionado es el mismo que el actual.');
        }
    }
}
