<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Enums\CompanyPackageSku;
use App\Enums\EvidenceEventType;
use App\Enums\ManualPaymentIntent;
use App\Enums\SupervisionPackageSku;
use App\Models\CommercialPayment;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Pricing\CompanyAgreedAmount;
use App\Services\Pricing\CompanyPriceHistory;
use App\Services\Pricing\PlanQuoter;
use App\Services\Tenant\AssignCompanyPackageService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ScheduleCompanyPackageChangeService
{
    public function __construct(
        private readonly RecordLifecycleEvidenceService $evidenceService,
        private readonly PlanQuoter $planQuoter,
        private readonly RegisterCommercialPaymentService $paymentService,
        private readonly AssignCompanyPackageService $assignCompanyPackageService,
        private readonly AssignCompanySupervisionPackageService $assignCompanySupervisionPackageService,
        private readonly CompanyPriceHistory $priceHistory,
        private readonly CompanyAgreedAmount $agreedAmount,
    ) {}

    public function scheduleWithManualPayment(
        SecurityCompany $company,
        User $actor,
        BillingCycle $cycle,
        string $reference,
        UploadedFile $proof,
        PlanSelection $selection,
    ): CommercialPayment {
        $this->assertCanSchedule($company, $cycle, $selection);

        return DB::transaction(function () use ($company, $actor, $cycle, $reference, $proof, $selection) {
            $amount = $this->amountDue($selection, $cycle);
            $effectiveAt = CarbonImmutable::parse($company->package_ends_at);

            $payment = $this->paymentService->executeManual(
                $company,
                $actor,
                $reference,
                $proof,
                ManualPaymentIntent::PlanChange,
                $amount,
                $cycle,
            );

            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], $this->meta($company, $cycle, $effectiveAt, $selection)),
            ]);

            $this->persistScheduledChange($company, $cycle, $effectiveAt, $payment, $amount, $selection);

            return $payment->fresh();
        });
    }

    public function scheduleWithLocalCheckout(
        SecurityCompany $company,
        User $actor,
        BillingCycle $cycle,
        PlanSelection $selection,
    ): CommercialPayment {
        $this->assertCanSchedule($company, $cycle, $selection);

        $effectiveAt = CarbonImmutable::parse($company->package_ends_at);

        return $this->paymentService->initiateLocalCheckout(
            $company,
            $actor,
            ManualPaymentIntent::PlanChange,
            $this->amountDue($selection, $cycle),
            $cycle,
            $this->meta($company, $cycle, $effectiveAt, $selection),
        );
    }

    public function scheduleSupervisionRemoval(SecurityCompany $company): SecurityCompany
    {
        if (! $company->isUpToDate()) {
            throw new \InvalidArgumentException(
                'Sin periodo vigente no se puede programar el retiro de Supervisión.',
            );
        }

        if (! $company->hasAccessPackage()
            && (int) $company->indexing_seats < 1
            && (int) $company->observatory_seats < 1) {
            throw new \InvalidArgumentException('La empresa solo tiene Supervisión. Para retirarla cancele la membresía.');
        }

        $effectiveAt = CarbonImmutable::parse($company->package_ends_at);
        $company->update([
            'scheduled_supervision_package_sku' => 'none',
            'scheduled_change_at' => $effectiveAt,
        ]);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeScheduled,
            'Retiro de Supervisión programado',
            ['effective_at' => $effectiveAt->toIso8601String()],
            $company->id,
        );

        return $company->fresh();
    }

    public function attachFromCompletedPayment(CommercialPayment $payment): void
    {
        $meta = $payment->metadata ?? [];
        if (($meta['kind'] ?? null) !== 'scheduled_package_change') {
            return;
        }

        $company = $payment->company ?? SecurityCompany::query()->findOrFail($payment->security_company_id);
        $cycle = BillingCycle::from((string) $meta['to_cycle']);
        $effectiveAt = isset($meta['effective_at'])
            ? CarbonImmutable::parse((string) $meta['effective_at'])
            : CarbonImmutable::parse($company->package_ends_at);

        $this->persistScheduledChange(
            $company,
            $cycle,
            $effectiveAt,
            $payment,
            (float) $payment->amount,
            $this->selectionFromMeta($meta, $company),
        );
    }

    public function applyDueChanges(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $applied = 0;

        SecurityCompany::query()
            ->whereNotNull('scheduled_change_at')
            ->where('scheduled_change_at', '<=', $now)
            ->where(function ($q): void {
                $q->whereNotNull('scheduled_package_sku')
                    ->orWhereNotNull('scheduled_supervision_package_sku')
                    ->orWhereNotNull('scheduled_indexing_seats')
                    ->orWhereNotNull('scheduled_observatory_seats');
            })
            ->chunkById(50, function ($companies) use (&$applied): void {
                foreach ($companies as $company) {
                    $this->applyOne($company);
                    $applied++;
                }
            });

        return $applied;
    }

    public function applyOne(SecurityCompany $company): SecurityCompany
    {
        $hasAccessChange = $company->scheduled_package_sku !== null;
        $sku = CompanyPackageSku::tryFrom((string) $company->scheduled_package_sku)
            ?? $company->package_sku
            ?? $company->accessSeats()->sku();
        $cycle = BillingCycle::from((string) ($company->scheduled_billing_cycle ?? $company->billing_cycle?->value ?? 'monthly'));
        $startsAt = $company->scheduled_change_at
            ? CarbonImmutable::parse($company->scheduled_change_at)
            : CarbonImmutable::now();
        $manual = (int) ($company->scheduled_manual_seats ?? $company->package_manual_seats ?? $sku->size());
        $hardware = (int) ($company->scheduled_hardware_seats ?? $company->package_hardware_seats ?? 0);
        $seats = $hasAccessChange ? new AccessSeatSplit($manual, $hardware) : $company->accessSeats();
        $scheduledSup = $company->scheduled_supervision_package_sku;
        $indexing = $company->scheduled_indexing_seats;
        $observatory = $company->scheduled_observatory_seats;
        $agreed = $company->scheduled_amount !== null ? (float) $company->scheduled_amount : null;
        $agreedProduct = CommercialProduct::tryFrom((string) $company->scheduled_amount_product);
        $agreedNote = $company->scheduled_amount_note;
        $before = $this->priceHistory->snapshot($company);

        return DB::transaction(function () use ($company, $sku, $cycle, $startsAt, $seats, $scheduledSup, $hasAccessChange, $indexing, $observatory, $before, $agreed, $agreedProduct, $agreedNote) {
            if ($scheduledSup !== null) {
                $supSku = $scheduledSup === 'none' ? null : SupervisionPackageSku::tryFrom($scheduledSup);
                $company = $this->assignCompanySupervisionPackageService->execute($company, $supSku);
            }

            if ($indexing !== null || $observatory !== null) {
                $company = $this->assignCompanyPackageService->assignProducts(
                    $company,
                    (int) ($indexing ?? $company->indexing_seats),
                    (int) ($observatory ?? $company->observatory_seats),
                );
            }

            if ($hasAccessChange) {
                $company = $this->assignCompanyPackageService->execute($company, $sku, $cycle, $startsAt, $seats);
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
                'scheduled_amount' => null,
                'scheduled_amount_product' => null,
                'scheduled_amount_note' => null,
            ]);

            if ($agreed !== null) {
                $company = $this->agreedAmount->apply($company, $agreed, $agreedProduct, $agreedNote);
            }

            $this->priceHistory->record($company, $before, $agreed !== null ? 'quota_expansion' : 'plan_change', null, $agreedNote, $startsAt);

            $this->evidenceService->record(
                EvidenceEventType::PackageChangeApplied,
                'Cambio de plan aplicado',
                [
                    'sku' => $sku->value,
                    'cycle' => $cycle->value,
                    'started_at' => $startsAt->toIso8601String(),
                ],
                $company->id,
            );

            return $company->fresh();
        });
    }

    private function amountDue(PlanSelection $selection, BillingCycle $cycle): float
    {
        $amount = $this->planQuoter->amount($selection, $cycle);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('No hay un cobro asociado a este cambio.');
        }

        return round($amount, 2);
    }

    /** @return array<string, mixed> */
    private function meta(SecurityCompany $company, BillingCycle $cycle, CarbonImmutable $effectiveAt, PlanSelection $selection): array
    {
        return [
            'kind' => 'scheduled_package_change',
            'from_sku' => $company->package_sku?->value,
            'to_sku' => $selection->access->sku()->value,
            'to_cycle' => $cycle->value,
            'effective_at' => $effectiveAt->toIso8601String(),
            ...$selection->toArray(),
        ];
    }

    /** @param array<string, mixed> $meta */
    private function selectionFromMeta(array $meta, SecurityCompany $company): PlanSelection
    {
        $sku = CompanyPackageSku::from((string) $meta['to_sku']);
        $current = PlanSelection::fromCompany($company);

        $supervision = $current->supervision;
        if (array_key_exists('supervision_seats', $meta)) {
            $supervision = (int) $meta['supervision_seats'];
        } elseif (! empty($meta['include_supervision']) || array_key_exists('to_supervision_sku', $meta)) {
            $supervision = (int) (SupervisionPackageSku::tryFrom((string) ($meta['to_supervision_sku'] ?? ''))?->size() ?? 0);
        }

        return new PlanSelection(
            new AccessSeatSplit(
                (int) ($meta['manual_seats'] ?? $sku->size()),
                (int) ($meta['hardware_seats'] ?? 0),
            ),
            $supervision,
            (int) ($meta['indexing_seats'] ?? $current->indexing),
            (int) ($meta['observatory_seats'] ?? $current->observatory),
        );
    }

    private function persistScheduledChange(
        SecurityCompany $company,
        BillingCycle $cycle,
        CarbonImmutable $effectiveAt,
        CommercialPayment $payment,
        float $amount,
        PlanSelection $selection,
    ): void {
        $payload = [
            'scheduled_change_at' => $effectiveAt,
            'scheduled_change_payment_id' => $payment->id,
        ];

        if (! $company->sameAccessAs($selection->access, $cycle)) {
            $payload['scheduled_package_sku'] = $selection->access->sku()->value;
            $payload['scheduled_manual_seats'] = $selection->access->manual;
            $payload['scheduled_hardware_seats'] = $selection->access->hardware;
            $payload['scheduled_billing_cycle'] = $cycle->value;
        }

        if ($company->supervision_package_sku !== $selection->supervisionSku()) {
            $payload['scheduled_supervision_package_sku'] = $selection->supervisionSku()?->value ?? 'none';
        }

        if ((int) ($company->indexing_seats ?? 0) !== $selection->indexing) {
            $payload['scheduled_indexing_seats'] = $selection->indexing;
        }

        if ((int) ($company->observatory_seats ?? 0) !== $selection->observatory) {
            $payload['scheduled_observatory_seats'] = $selection->observatory;
        }

        $company->update($payload);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeScheduled,
            'Cambio de plan programado',
            [
                'from_sku' => $company->package_sku?->value,
                'to_sku' => $selection->access->sku()->value,
                'to_plan' => $selection->label(),
                'to_cycle' => $cycle->value,
                'effective_at' => $effectiveAt->toIso8601String(),
                'payment_id' => $payment->id,
                'amount' => $amount,
            ],
            $company->id,
        );
    }

    private function assertCanSchedule(SecurityCompany $company, BillingCycle $cycle, PlanSelection $selection): void
    {
        if (! $company->isUpToDate()) {
            throw new \InvalidArgumentException(
                'Sin periodo vigente no se puede programar cambio diferido. Reactive o renueve primero.'
            );
        }

        if ($selection->isEmpty()) {
            throw new \InvalidArgumentException('El plan debe incluir al menos un producto.');
        }

        if ($company->sameAccessAs($selection->access, $cycle)
            && $company->supervision_package_sku === $selection->supervisionSku()
            && $company->sameProductsAs($selection)) {
            throw new \InvalidArgumentException('El plan seleccionado es el mismo que el actual.');
        }
    }
}
