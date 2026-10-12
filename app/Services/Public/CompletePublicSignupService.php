<?php

declare(strict_types=1);

namespace App\Services\Public;

use App\Enums\EvidenceEventType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlatformDocumentType;
use App\Enums\SignupIntentStatus;
use App\Models\CommercialPayment;
use App\Models\CommercialSignupIntent;
use App\Models\PlatformDocument;
use App\Models\SecurityCompany;
use App\Models\SubscriptionAcceptance;
use App\Models\User;
use App\Services\Platform\IssueDemoInvoiceService;
use App\Services\Platform\RecordLifecycleEvidenceService;
use App\Services\Pricing\CompanyPriceHistory;
use App\Services\Tenant\AssignCompanyPackageService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use App\Services\User\EnsureCompanyAdminCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompletePublicSignupService
{
    public function __construct(
        private readonly AssignCompanyPackageService $assignPackageService,
        private readonly AssignCompanySupervisionPackageService $assignSupervisionPackageService,
        private readonly RecordLifecycleEvidenceService $evidenceService,
        private readonly IssueDemoInvoiceService $issueDemoInvoiceService,
        private readonly CompanyPriceHistory $priceHistory,
    ) {}

    public function execute(CommercialSignupIntent $intent): User
    {
        if (! $intent->isCheckoutReady()) {
            throw new \InvalidArgumentException('El proceso de contratación está incompleto o expiró.');
        }

        if (User::query()->where('email', $intent->email)->exists()) {
            throw new \InvalidArgumentException('Ya existe una cuenta con este email.');
        }

        if (SecurityCompany::query()->where('tax_id', $intent->tax_id)->exists()) {
            throw new \InvalidArgumentException('Ya existe una empresa con este identificador fiscal.');
        }

        return DB::transaction(function () use ($intent) {
            $now = CarbonImmutable::now();

            $company = SecurityCompany::query()->create([
                'legal_name' => $intent->legal_name,
                'trade_name' => $intent->trade_name ?: $intent->legal_name,
                'tax_id' => $intent->tax_id,
                'party_type' => $intent->party_type,
                'email' => $intent->email,
                'phone' => $intent->phone,
                'address' => $intent->address,
                'city' => $intent->city,
                'department' => $intent->department,
                'latitude' => $intent->latitude,
                'longitude' => $intent->longitude,
                'is_active' => true,
            ]);

            $selection = $intent->planSelection();
            $this->assignPackageService->execute(
                $company,
                $selection->access->sku(),
                $intent->billing_cycle,
                $now,
                $selection->access,
            );
            $this->assignPackageService->assignProducts($company->fresh(), $selection->indexing, $selection->observatory);

            if ($intent->supervision_package_sku !== null) {
                $this->assignSupervisionPackageService->execute(
                    $company->fresh(),
                    $intent->supervision_package_sku,
                );
            }

            $this->priceHistory->record($company, ['amount' => 0.0, 'plan' => ''], 'signup');

            $user = User::query()->create([
                'name' => $intent->admin_name ?? $intent->legal_name,
                'email' => $intent->email,
                'password' => $intent->password,
                'email_verified_at' => $now,
                'is_active' => true,
                'security_company_id' => $company->id,
            ]);
            $user->assignRole('company-admin');
            app(EnsureCompanyAdminCatalog::class)->execute($user);

            $acceptance = SubscriptionAcceptance::query()->create([
                'security_company_id' => $company->id,
                'user_id' => $user->id,
                'representative_name' => $intent->representative_name,
                'representative_role' => $intent->representative_role,
                'representative_document_type' => $intent->representative_document_type,
                'representative_document_number' => $intent->representative_document_number,
                'corpus_snapshot' => $intent->corpus_snapshot,
                'content_hash' => $intent->content_hash,
                'ip_address' => $intent->ip_address,
                'user_agent' => $intent->user_agent,
                'accepted_at' => $now,
            ]);

            $contractTitle = collect($intent->corpus_snapshot ?? [])
                ->firstWhere('type', 'contract')['title'] ?? 'Paquete contractual aceptado';

            PlatformDocument::query()->create([
                'security_company_id' => $company->id,
                'type' => PlatformDocumentType::Contract,
                'title' => $contractTitle,
                'reference_number' => 'ACC-'.$acceptance->id,
                'metadata' => [
                    'acceptance_id' => $acceptance->id,
                    'package_sku' => $intent->package_sku?->value ?? $intent->package_sku,
                    'corpus_snapshot' => $intent->corpus_snapshot,
                    'signup_intent_token' => $intent->token,
                    'immutable' => true,
                ],
                'issued_at' => $now,
                'retention_until' => $now->addYears(10)->toDateString(),
                'created_by_user_id' => $user->id,
                'is_demo' => config('billing.mode') === 'demo',
            ]);

            $this->evidenceService->record(
                EvidenceEventType::SubscriptionAccepted,
                'Aceptación contractual registrada',
                [
                    'acceptance_id' => $acceptance->id,
                    'signup_intent_token' => $intent->token,
                    'content_hash' => $intent->content_hash,
                ],
                $company->id,
            );

            $payment = CommercialPayment::query()->create([
                'security_company_id' => $company->id,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'billing_cycle' => $intent->billing_cycle->value,
                'method' => PaymentMethod::Gateway,
                'gateway_driver' => 'local',
                'gateway_transaction_id' => (string) Str::uuid(),
                'gateway_status' => 'approved',
                'status' => PaymentStatus::Completed,
                'reference' => 'LOCAL-SIGNUP-'.Str::upper(Str::substr($intent->token, 0, 12)),
                'paid_at' => $now,
                'recorded_by_user_id' => $user->id,
                'initiated_by_user_id' => $user->id,
                'metadata' => ['source' => 'public_signup', 'signup_intent_token' => $intent->token],
            ]);

            $this->evidenceService->record(
                EvidenceEventType::PaymentRecorded,
                'Pago online registrado (contratación pública)',
                [
                    'payment_id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'signup_intent_token' => $intent->token,
                ],
                $company->id,
            );

            $this->issueDemoInvoiceService->execute($company, $user, $payment);

            $intent->update([
                'status' => SignupIntentStatus::Completed,
                'completed_at' => $now,
                'password' => null,
            ]);

            return $user;
        });
    }
}
