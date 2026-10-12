<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\ArchiveReason;
use App\Enums\ClientLifecycle;
use App\Enums\EvidenceEventType;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Support\Company\CompanyServiceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ArchiveCompanyService
{
    public function __construct(
        private readonly RecordLifecycleEvidenceService $evidenceService,
    ) {}

    public function execute(SecurityCompany $company, ArchiveReason $reason): SecurityCompany
    {
        return DB::transaction(function () use ($company, $reason) {
            $now = CarbonImmutable::now();

            $company->update([
                'is_active' => false,
                'subscription_status' => SubscriptionStatus::Suspended,
                'suspended_at' => $now,
                'archived_at' => $now,
                'archive_reason' => $reason,
            ]);

            Client::query()
                ->where('security_company_id', $company->id)
                ->where('lifecycle', ClientLifecycle::Active->value)
                ->where('is_active', true)
                ->update([
                    'archived_at' => $now,
                    'archived_with_parent' => true,
                    'is_active' => false,
                ]);

            Client::query()
                ->where('security_company_id', $company->id)
                ->where('lifecycle', ClientLifecycle::Active->value)
                ->update(['lifecycle' => ClientLifecycle::ArchivedCompany]);

            CompanyServiceAccess::revokeOperationalTokens((int) $company->id);

            $this->evidenceService->record(
                EvidenceEventType::CompanyArchived,
                'Acta de archivo comercial',
                [
                    'archive_reason' => $reason->value,
                    'archived_at' => $now->toIso8601String(),
                ],
                $company->id,
                null,
                $now,
            );

            return $company->fresh();
        });
    }
}
