<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Platform\ProcessDataRetentionPurgeService;
use Illuminate\Console\Command;

final class PurgeDataRetention extends Command
{
    protected $signature = 'data:purge-retention';

    protected $description = 'Purga datos operativos post-retención y anonimiza metadatos comerciales vencidos';

    public function handle(ProcessDataRetentionPurgeService $service): int
    {
        $result = $service->execute();

        $this->info("Conjuntos purgados: {$result['clients_purged']}");
        $this->info("Empresas anonimizadas: {$result['companies_anonymized']}");
        $lines = $result['service_lines'] ?? [];
        $this->info('Líneas archivadas purgadas: Accesos '.($lines['access'] ?? 0)
            .', Supervisión '.($lines['supervision'] ?? 0)
            .', Observatorio '.($lines['observatory'] ?? 0));

        return self::SUCCESS;
    }
}
