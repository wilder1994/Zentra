<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\ClientLifecycle;
use App\Models\Client;
use App\Models\SecurityCompany;
use Carbon\CarbonImmutable;

final class ProcessDataRetentionPurgeService
{
    public function __construct(
        private readonly PurgeClientTenantDataService $purgeClientTenantDataService,
    ) {}

    /**
     * @return array{clients_purged: int, companies_anonymized: int}
     */
    public function execute(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'clients_purged' => $this->purgeEligibleClients($now),
            'companies_anonymized' => $this->anonymizeEligibleCompanies($now),
        ];
    }

    private function purgeEligibleClients(CarbonImmutable $now): int
    {
        $cutoff = $now->subDays((int) config('retention.census_retention_days', 365));
        $purged = 0;

        Client::query()
            ->whereIn('lifecycle', [
                ClientLifecycle::Released->value,
                ClientLifecycle::ArchivedCompany->value,
            ])
            ->whereNull('tenant_data_purged_at')
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($released) use ($cutoff) {
                    $released->where('lifecycle', ClientLifecycle::Released->value)
                        ->whereNotNull('released_at')
                        ->where('released_at', '<=', $cutoff);
                })->orWhere(function ($archived) use ($cutoff) {
                    $archived->where('lifecycle', ClientLifecycle::ArchivedCompany->value)
                        ->whereNotNull('archived_at')
                        ->where('archived_at', '<=', $cutoff);
                });
            })
            ->orderBy('id')
            ->chunkById(50, function ($clients) use (&$purged) {
                foreach ($clients as $client) {
                    $this->purgeClientTenantDataService->execute($client);
                    $purged++;
                }
            });

        return $purged;
    }

    private function anonymizeEligibleCompanies(CarbonImmutable $now): int
    {
        $cutoff = $now->subYears((int) config('retention.commercial_retention_years', 5));
        $anonymized = 0;

        SecurityCompany::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $cutoff)
            ->whereNull('commercial_anonymized_at')
            ->orderBy('id')
            ->chunkById(50, function ($companies) use ($now, &$anonymized) {
                foreach ($companies as $company) {
                    $company->update([
                        'legal_name' => "Empresa archivada #{$company->id}",
                        'trade_name' => "Empresa archivada #{$company->id}",
                        'email' => "archived-{$company->id}@purged.zentra",
                        'phone' => null,
                        'logo_path' => null,
                        'commercial_anonymized_at' => $now,
                    ]);
                    $anonymized++;
                }
            });

        return $anonymized;
    }
}
