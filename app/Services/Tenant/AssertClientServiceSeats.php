<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Client;
use App\Models\SecurityCompany;
use App\Support\Catalog\CompanyEntitlements;
use App\Support\Catalog\InstallationQuota;
use Illuminate\Validation\ValidationException;

/** Activar una línea en un cliente consume un cupo de esa línea por cada instalación activa del cliente. */
final class AssertClientServiceSeats
{
    /**
     * @param  array{access?: bool, supervision?: bool, observatory?: bool}  $lines
     */
    public function execute(SecurityCompany $company, array $lines, ?Client $client = null): void
    {
        $entitlements = CompanyEntitlements::for($company);
        $installations = $client !== null ? max(1, $this->activeInstallations($client)) : 1;

        $map = [
            InstallationQuota::ACCESS => [$entitlements->hasAccess(), 'Accesos'],
            InstallationQuota::SUPERVISION => [$entitlements->hasSupervision(), 'Supervisión'],
            InstallationQuota::OBSERVATORY => [$entitlements->hasObservatory(), 'Observatorio'],
        ];

        foreach ($map as $line => [$contracted, $label]) {
            if (! ($lines[$line] ?? false)) {
                continue;
            }

            if (! $contracted) {
                throw ValidationException::withMessages([
                    'has_'.$line => 'Tu plan no incluye '.$label.'. Agrégalo en Facturación → Cambiar plan.',
                ]);
            }

            $already = $client !== null && (bool) $client->{'has_'.$line};
            if ($already) {
                continue;
            }

            $company->installationQuota()->assertRoom(
                [$line],
                $installations,
                'No puedes activar '.$label.' en este cliente ('.$installations.' '.($installations === 1 ? 'instalación' : 'instalaciones').'):',
                exceptClientId: $client?->id,
            );
        }
    }

    private function activeInstallations(Client $client): int
    {
        return $client->installations()->where('is_active', true)->count();
    }
}
