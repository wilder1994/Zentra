<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\ArchiveReason;
use App\Enums\SubscriptionStatus;
use App\Models\SecurityCompany;
use App\Services\Pricing\CompanyRepricingService;
use Carbon\CarbonImmutable;

/**
 * Ciclo de acceso (no reemplaza paquetes):
 * active/cancelled → (vencimiento) → grace (N días) → suspended → (M días) → archived.
 * Si hay cambio de plan programado, se aplica al llegar scheduled_change_at.
 */
final class ProcessSubscriptionLifecycleService
{
    public function execute(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $processed = 0;

        $processed += app(ScheduleCompanyPackageChangeService::class)->applyDueChanges($now);
        $processed += app(CompanyRepricingService::class)->applyDue($now);

        SecurityCompany::query()
            ->whereNull('archived_at')
            ->whereNotNull('package_ends_at')
            ->chunkById(50, function ($companies) use ($now, &$processed) {
                foreach ($companies as $company) {
                    if ($this->processCompany($company, $now)) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    private function processCompany(SecurityCompany $company, CarbonImmutable $now): bool
    {
        $endsAt = $company->package_ends_at;

        if ($endsAt === null) {
            return false;
        }

        $graceDays = max(0, (int) config('subscription.grace_days', 5));
        $archiveAfterSuspended = max(1, (int) config('subscription.archive_after_suspended_days', 90));

        // Cancelada al fin de periodo: al vencer, suspende (sin gracia comercial).
        if (
            $company->subscription_status === SubscriptionStatus::Cancelled
            && $company->cancel_at_period_end
            && $endsAt->isPast()
        ) {
            app(SuspendCompanyService::class)->execute($company, $now);

            return true;
        }

        // 1) Activa vencida → gracia
        if ($company->subscription_status === SubscriptionStatus::Active && $endsAt->isPast()) {
            $company->update([
                'subscription_status' => SubscriptionStatus::Grace,
                'grace_ends_at' => $endsAt->addDays($graceDays),
            ]);

            return true;
        }

        // 2) Gracia vencida → suspensión (bloqueo de acceso, sin archivar)
        if ($company->subscription_status === SubscriptionStatus::Grace) {
            $graceEnds = $company->grace_ends_at ?? $endsAt->addDays($graceDays);

            if ($graceEnds->isPast()) {
                app(SuspendCompanyService::class)->execute($company, $now);

                return true;
            }
        }

        // 3) Expired legacy → suspensión (no archivo directo)
        if ($company->subscription_status === SubscriptionStatus::Expired && $endsAt->isPast()) {
            app(SuspendCompanyService::class)->execute($company, $now);

            return true;
        }

        // 4) Suspendida demasiado tiempo → archivo
        if (
            $company->subscription_status === SubscriptionStatus::Suspended
            && $company->suspended_at !== null
            && CarbonImmutable::parse($company->suspended_at)->addDays($archiveAfterSuspended)->isPast()
        ) {
            $reason = $company->cancel_at_period_end
                ? ArchiveReason::Cancelled
                : ArchiveReason::NonPayment;
            app(ArchiveCompanyService::class)->execute($company, $reason);

            return true;
        }

        return false;
    }
}
