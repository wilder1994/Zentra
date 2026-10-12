<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\EvidenceEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\SecurityCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReactivateCompanyService
{
    public function __construct(
        private readonly RecordLifecycleEvidenceService $evidenceService,
    ) {}

    public function execute(SecurityCompany $company, ?CarbonImmutable $at = null): SecurityCompany
    {
        $at ??= CarbonImmutable::now();

        return DB::transaction(function () use ($company, $at) {
            $company->update([
                'is_active' => true,
                'subscription_status' => SubscriptionStatus::Active,
                'suspended_at' => null,
                'archived_at' => null,
                'archive_reason' => null,
            ]);

            Client::restoreFromCompanyArchive((int) $company->id);

            $this->evidenceService->record(
                EvidenceEventType::MembershipReactivated,
                'Reactivación de servicio',
                [
                    'reactivated_at' => $at->toIso8601String(),
                    'subscription_status' => SubscriptionStatus::Active->value,
                ],
                $company->id,
                null,
                $at,
            );

            return $company->fresh();
        });
    }
}
