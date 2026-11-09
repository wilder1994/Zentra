<?php

declare(strict_types=1);

namespace App\Support\Resident;

use App\Models\StructureAppUser;
use App\Support\Tenancy\TenantContext;

final class ResidentTenant
{
    public static function bind(StructureAppUser $appUser, TenantContext $tenant): void
    {
        $appUser->loadMissing(['member.structure', 'client']);
        $tenant->setClientId((int) $appUser->client_id);
        $installationId = (int) ($appUser->member?->structure?->installation_id ?? 0);
        $tenant->setInstallationIds($installationId > 0 ? [$installationId] : []);
    }
}
