<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Client;
use App\Models\Location;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryEventStatusLog;
use App\Models\ObservatoryReport;
use App\Models\SupervisorPost;
use App\Support\Catalog\ServiceLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Purga operativa de líneas archivadas en el cliente (retención service_line). */
final class PurgeArchivedServiceLineDataService
{
    /** @return array{access: int, supervision: int, observatory: int} */
    public function execute(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $cutoff = $now->subDays((int) config('retention.service_line_retention_days', 365));

        return [
            'access' => $this->purgeAccess($cutoff),
            'supervision' => $this->purgeSupervision($cutoff),
            'observatory' => $this->purgeObservatory($cutoff),
        ];
    }

    private function purgeAccess(CarbonImmutable $cutoff): int
    {
        $count = 0;
        Client::query()
            ->where('has_access', false)
            ->whereNotNull('access_line_archived_at')
            ->where('access_line_archived_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($clients) use (&$count): void {
                foreach ($clients as $client) {
                    $n = Location::query()->withoutGlobalScopes()
                        ->where('client_id', $client->id)
                        ->where('archived_by_service_line', true)
                        ->count();
                    Location::query()->withoutGlobalScopes()
                        ->where('client_id', $client->id)
                        ->where('archived_by_service_line', true)
                        ->delete();
                    $count += $n;
                }
            });

        return $count;
    }

    private function purgeSupervision(CarbonImmutable $cutoff): int
    {
        $count = 0;
        Client::query()
            ->where('has_supervision', false)
            ->whereNotNull('supervision_line_archived_at')
            ->where('supervision_line_archived_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($clients) use (&$count): void {
                foreach ($clients as $client) {
                    $n = SupervisorPost::query()->withoutGlobalScopes()
                        ->where('client_id', $client->id)
                        ->where('archived_by_service_line', true)
                        ->count();
                    SupervisorPost::query()->withoutGlobalScopes()
                        ->where('client_id', $client->id)
                        ->where('archived_by_service_line', true)
                        ->delete();
                    $count += $n;
                }
            });

        return $count;
    }

    private function purgeObservatory(CarbonImmutable $cutoff): int
    {
        $count = 0;
        Client::query()
            ->where('has_observatory', false)
            ->whereNotNull(ServiceLine::archivedAtColumn(ServiceLine::OBSERVATORY))
            ->where('observatory_line_archived_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($clients) use (&$count): void {
                foreach ($clients as $client) {
                    $ids = ObservatoryEvent::query()
                        ->where('client_id', $client->id)
                        ->where('archived_by_service_line', true)
                        ->pluck('id');
                    if ($ids->isEmpty()) {
                        continue;
                    }
                    DB::transaction(function () use ($ids, &$count): void {
                        ObservatoryEventStatusLog::query()->whereIn('event_id', $ids)->delete();
                        ObservatoryReport::query()->whereIn('event_id', $ids)->delete();
                        $count += ObservatoryEvent::query()->whereIn('id', $ids)->delete();
                    });
                }
            });

        return $count;
    }
}
