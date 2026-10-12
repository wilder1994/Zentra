<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\BillingCycle;
use App\Enums\EvidenceEventType;
use App\Enums\ManualPaymentIntent;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\CommercialPayment;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Pricing\CompanyRepricingService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterCommercialPaymentService
{
    public function __construct(
        private readonly RecordLifecycleEvidenceService $evidenceService,
        private readonly IssueDemoInvoiceService $issueDemoInvoiceService,
    ) {}

    public function executeManual(
        SecurityCompany $company,
        User $recordedBy,
        string $reference,
        UploadedFile $proof,
        ManualPaymentIntent $intent = ManualPaymentIntent::Renew,
        ?float $amountOverride = null,
        ?BillingCycle $cycleOverride = null,
    ): CommercialPayment {
        $this->assertAcceptanceCompleted($company);

        if ($company->isUpToDate() && $intent === ManualPaymentIntent::Renew) {
            throw new \InvalidArgumentException(
                'La cuenta está al día. Si desea anticipar el próximo periodo, elija esa opción.'
            );
        }

        if (! $company->isUpToDate() && $intent === ManualPaymentIntent::Anticipate) {
            throw new \InvalidArgumentException(
                'La cuenta no está al día. Use renovar o reactivar según corresponda.'
            );
        }

        if ($intent === ManualPaymentIntent::PlanChange && ! $company->isUpToDate()) {
            throw new \InvalidArgumentException(
                'Sin periodo vigente no se puede programar un cambio de plan diferido.'
            );
        }

        if ($intent === ManualPaymentIntent::SupervisionChange && ! $company->isUpToDate()) {
            throw new \InvalidArgumentException(
                'Sin periodo Accesos vigente no se puede contratar Supervisión.'
            );
        }

        if ($amountOverride === null) {
            $company = $this->applyPendingReprice($company, $intent);
        }

        return DB::transaction(function () use ($company, $recordedBy, $reference, $proof, $intent, $amountOverride, $cycleOverride) {
            $now = CarbonImmutable::now();
            $period = $this->resolveCoveredPeriod($company, $intent, $now);
            $proofPath = $proof->store('payment-proofs/'.$company->id, 'local');
            $cycle = $cycleOverride ?? $company->billing_cycle;
            $amount = $amountOverride ?? $company->contractedAmount();

            $payment = CommercialPayment::query()->create([
                'security_company_id' => $company->id,
                'amount' => $amount,
                'currency' => config('billing.currency', 'COP'),
                'billing_cycle' => $cycle?->value,
                'method' => PaymentMethod::Manual,
                'status' => PaymentStatus::Completed,
                'reference' => $reference,
                'proof_path' => $proofPath,
                'covers_period_start' => $period['start'],
                'covers_period_end' => $period['end'],
                'payment_intent' => $intent->value,
                'paid_at' => $now,
                'recorded_by_user_id' => $recordedBy->id,
                'initiated_by_user_id' => $recordedBy->id,
                'metadata' => [
                    'source' => 'admin_manual',
                    'intent' => $intent->value,
                    'proof_original_name' => $proof->getClientOriginalName(),
                ],
            ]);

            $this->finalizeCompletedPayment($payment, $recordedBy, $intent);

            return $payment;
        });
    }

    public function initiateLocalCheckout(
        SecurityCompany $company,
        User $initiatedBy,
        ?ManualPaymentIntent $intent = null,
        ?float $amountOverride = null,
        ?BillingCycle $cycleOverride = null,
        array $metadata = [],
    ): CommercialPayment {
        $this->assertAcceptanceCompleted($company);
        $this->assertLocalGatewayDriver();

        $intent ??= $company->isUpToDate()
            ? ManualPaymentIntent::Anticipate
            : ($company->hasPendingCancellation() || $company->subscription_status === SubscriptionStatus::Suspended
                ? ManualPaymentIntent::Reactivate
                : ManualPaymentIntent::Renew);

        if ($company->isUpToDate() && $intent === ManualPaymentIntent::Renew) {
            throw new \InvalidArgumentException(
                'La cuenta está al día. Use anticipar el próximo periodo o programar un cambio de plan.'
            );
        }

        if (! $company->isUpToDate() && $intent === ManualPaymentIntent::Anticipate) {
            throw new \InvalidArgumentException(
                'La cuenta no está al día. Use renovar o reactivar según corresponda.'
            );
        }

        if ($intent === ManualPaymentIntent::PlanChange && ! $company->isUpToDate()) {
            throw new \InvalidArgumentException(
                'Sin periodo vigente no se puede programar un cambio de plan diferido.'
            );
        }

        if ($intent === ManualPaymentIntent::SupervisionChange && ! $company->isUpToDate()) {
            throw new \InvalidArgumentException(
                'Sin periodo Accesos vigente no se puede contratar Supervisión.'
            );
        }

        if ($amountOverride === null) {
            $company = $this->applyPendingReprice($company, $intent);
        }

        $cycle = $cycleOverride ?? $company->billing_cycle;
        $amount = $amountOverride ?? $company->contractedAmount();

        return CommercialPayment::query()->create([
            'security_company_id' => $company->id,
            'amount' => $amount,
            'currency' => config('billing.currency', 'COP'),
            'billing_cycle' => $cycle?->value,
            'method' => PaymentMethod::Gateway,
            'gateway_driver' => 'local',
            'gateway_transaction_id' => (string) Str::uuid(),
            'gateway_status' => 'checkout_created',
            'status' => PaymentStatus::Pending,
            'payment_intent' => $intent->value,
            'initiated_by_user_id' => $initiatedBy->id,
            'metadata' => array_merge(['source' => 'local_simulator'], $metadata),
        ]);
    }

    public function completeLocalCheckout(CommercialPayment $payment, User $actor): CommercialPayment
    {
        $this->assertLocalPendingPayment($payment);

        return DB::transaction(function () use ($payment, $actor) {
            $now = CarbonImmutable::now();
            $company = $payment->company ?? SecurityCompany::query()->findOrFail($payment->security_company_id);
            $intent = ManualPaymentIntent::tryFrom((string) ($payment->payment_intent ?? ''))
                ?? ($company->isUpToDate() ? ManualPaymentIntent::Anticipate : ManualPaymentIntent::Renew);
            $cycleOverride = BillingCycle::tryFrom((string) ($payment->billing_cycle ?? ''));
            $period = $this->resolveCoveredPeriod($company, $intent, $now, $cycleOverride);

            $payment->update([
                'status' => PaymentStatus::Completed,
                'gateway_status' => 'approved',
                'paid_at' => $now,
                'recorded_by_user_id' => $actor->id,
                'reference' => 'LOCAL-'.Str::upper(Str::substr($payment->gateway_transaction_id ?? '', 0, 12)),
                'covers_period_start' => $period['start'],
                'covers_period_end' => $period['end'],
                'payment_intent' => $intent->value,
            ]);

            $fresh = $payment->fresh();
            $this->finalizeCompletedPayment($fresh, $actor, $intent);

            if ($intent === ManualPaymentIntent::PlanChange) {
                app(ScheduleCompanyPackageChangeService::class)->attachFromCompletedPayment($fresh);
            }

            if ($intent === ManualPaymentIntent::SupervisionChange) {
                $this->applySupervisionPackageFromPayment($fresh);
            }

            return $payment->fresh();
        });
    }

    public function failLocalCheckout(CommercialPayment $payment, User $actor): CommercialPayment
    {
        return $this->rejectLocalCheckout($payment, $actor);
    }

    public function rejectLocalCheckout(CommercialPayment $payment, User $actor): CommercialPayment
    {
        $this->assertLocalPendingPayment($payment);

        $payment->update([
            'status' => PaymentStatus::Failed,
            'gateway_status' => 'rejected',
            'metadata' => array_merge($payment->metadata ?? [], [
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => CarbonImmutable::now()->toIso8601String(),
            ]),
        ]);

        return $payment->fresh();
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function resolveCoveredPeriod(
        SecurityCompany $company,
        ManualPaymentIntent $intent,
        CarbonImmutable $now,
        ?BillingCycle $cycleOverride = null,
    ): array {
        $cycle = $cycleOverride ?? $company->billing_cycle ?? BillingCycle::Monthly;
        $currentEnd = $company->package_ends_at
            ? CarbonImmutable::parse($company->package_ends_at)
            : null;

        if ($intent === ManualPaymentIntent::Anticipate && $currentEnd !== null && $currentEnd->greaterThan($now)) {
            $start = $currentEnd;
            $end = $cycle === BillingCycle::Annual ? $start->addYear() : $start->addMonth();

            return ['start' => $start, 'end' => $end];
        }

        if ($intent === ManualPaymentIntent::PlanChange && $currentEnd !== null) {
            $start = $currentEnd;
            $end = $cycle === BillingCycle::Annual ? $start->addYear() : $start->addMonth();

            return ['start' => $start, 'end' => $end];
        }

        if ($intent === ManualPaymentIntent::SupervisionChange) {
            $start = $now;
            $end = $currentEnd ?? ($cycle === BillingCycle::Annual ? $start->addYear() : $start->addMonth());

            return ['start' => $start, 'end' => $end];
        }

        $start = $now;
        $end = $cycle === BillingCycle::Annual ? $start->addYear() : $start->addMonth();

        return ['start' => $start, 'end' => $end];
    }

    private function finalizeCompletedPayment(
        CommercialPayment $payment,
        User $recordedBy,
        ManualPaymentIntent $intent,
    ): void {
        $company = $payment->company ?? SecurityCompany::query()->findOrFail($payment->security_company_id);
        $periodEnd = $payment->covers_period_end
            ? CarbonImmutable::parse($payment->covers_period_end)
            : null;
        $periodStart = $payment->covers_period_start
            ? CarbonImmutable::parse($payment->covers_period_start)
            : CarbonImmutable::now();

        // Plan change diferido y alta Pro: no mueve package_ends_at de Accesos.
        if (! in_array($intent, [ManualPaymentIntent::PlanChange, ManualPaymentIntent::SupervisionChange], true)) {
            $company->update([
                'package_starts_at' => $company->package_starts_at ?? $periodStart,
                'package_ends_at' => $periodEnd ?? $company->package_ends_at,
                'grace_ends_at' => null,
                'suspended_at' => null,
                'archived_at' => null,
                'archive_reason' => null,
                'subscription_status' => SubscriptionStatus::Active,
                'is_active' => true,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ]);
        }

        if ($intent === ManualPaymentIntent::Reactivate) {
            Client::restoreFromCompanyArchive((int) $company->id);

            $this->evidenceService->record(
                EvidenceEventType::MembershipReactivated,
                'Membresía reactivada',
                [
                    'payment_id' => $payment->id,
                    'package_ends_at' => $periodEnd?->toIso8601String(),
                ],
                $company->id,
            );
        }

        $this->evidenceService->record(
            EvidenceEventType::PaymentRecorded,
            $payment->method === PaymentMethod::Manual
                ? 'Pago manual registrado'
                : 'Pago online registrado (simulador local)',
            [
                'payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'method' => $payment->method->value,
                'intent' => $intent->value,
                'covers_period_end' => $periodEnd?->toIso8601String(),
                'gateway_driver' => $payment->gateway_driver,
            ],
            $company->id,
        );

        $this->issueDemoInvoiceService->execute($company->fresh(), $recordedBy, $payment);
    }

    /** Si hay nuevo valor programado para el periodo que se paga, se aplica antes de cobrar. */
    private function applyPendingReprice(SecurityCompany $company, ManualPaymentIntent $intent): SecurityCompany
    {
        $at = $intent === ManualPaymentIntent::Anticipate && $company->package_ends_at !== null
            ? CarbonImmutable::parse($company->package_ends_at)
            : CarbonImmutable::now();

        return app(CompanyRepricingService::class)->applyIfDue($company, $at);
    }

    private function assertAcceptanceCompleted(SecurityCompany $company): void
    {
        if (! $company->hasCompletedAcceptance()) {
            throw new \InvalidArgumentException('La empresa debe completar la aceptación contractual antes del pago.');
        }
    }

    private function assertLocalGatewayDriver(): void
    {
        if (config('billing.gateway.driver') !== 'local') {
            throw new \RuntimeException(
                'Solo el driver local está implementado. Use BILLING_GATEWAY_DRIVER=local para pruebas sin proveedor.'
            );
        }
    }

    private function assertLocalPendingPayment(CommercialPayment $payment): void
    {
        if ($payment->gateway_driver !== 'local') {
            throw new \InvalidArgumentException('Este pago no pertenece al simulador local.');
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw new \InvalidArgumentException('El pago ya no está pendiente de confirmación.');
        }
    }

    private function applySupervisionPackageFromPayment(CommercialPayment $payment): void
    {
        $meta = $payment->metadata ?? [];
        if (($meta['kind'] ?? null) !== 'supervision_package_change') {
            return;
        }

        $sku = SupervisionPackageSku::from((string) $meta['to_sku']);
        $company = $payment->company ?? SecurityCompany::query()->findOrFail($payment->security_company_id);

        app(AssignCompanySupervisionPackageService::class)->execute($company, $sku);
    }
}
