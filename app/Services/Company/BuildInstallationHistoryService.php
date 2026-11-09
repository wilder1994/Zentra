<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\OperationalAlertType;
use App\Models\Installation;
use App\Models\OperationalAlert;
use Illuminate\Support\Collection;

final class BuildInstallationHistoryService
{
    /**
     * @return Collection<int, OperationalAlert>
     */
    public function execute(Installation $installation, int $limit = 80): Collection
    {
        return OperationalAlert::query()
            ->where('installation_id', $installation->id)
            ->where('type', OperationalAlertType::ServiceChange)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
