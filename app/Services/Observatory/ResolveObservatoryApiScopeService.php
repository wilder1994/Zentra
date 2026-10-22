<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\User;
use App\Support\Auth\AssignableRoles;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ResolveObservatoryApiScopeService
{
    /**
     * @return array{company_id: ?int, client_id: ?int, installation_ids: ?list<int>}
     */
    public function execute(User $user, ?int $clientIdFilter = null): array
    {
        if (! $user->can('observatory.view')) {
            throw new HttpException(403, 'Sin permiso para el Observatorio.');
        }

        if ($user->hasRole('company-admin')) {
            $companyId = (int) $user->security_company_id;
            if ($companyId <= 0) {
                throw new HttpException(403, 'La empresa no está asignada.');
            }
            if ($clientIdFilter !== null) {
                if (! $user->canAccessClient($clientIdFilter)) {
                    throw new HttpException(403, 'Ese cliente no es de tu empresa.');
                }

                return [
                    'company_id' => $companyId,
                    'client_id' => $clientIdFilter,
                    'installation_ids' => null,
                ];
            }

            return [
                'company_id' => $companyId,
                'client_id' => null,
                'installation_ids' => null,
            ];
        }

        if ($user->hasRole('colaborador')) {
            $companyId = (int) $user->security_company_id;
            if ($companyId <= 0) {
                throw new HttpException(403, 'La empresa no está asignada.');
            }
            if ($clientIdFilter !== null && ! $user->canAccessClient($clientIdFilter)) {
                throw new HttpException(403, 'Ese cliente no está en tu alcance.');
            }

            return [
                'company_id' => $companyId,
                'client_id' => $clientIdFilter,
                'installation_ids' => $user->assignedInstallationIds() ?? [],
            ];
        }

        if ($user->hasRole(AssignableRoles::CLIENT_ADMIN) || $user->hasRole(AssignableRoles::CLIENT_INSTALLATION_ADMIN)) {
            $clientId = $clientIdFilter ?? (int) ($user->primary_client_id ?? 0);
            if ($clientId <= 0) {
                $clientId = (int) ($user->assignedClientIds()[0] ?? 0);
            }
            if ($clientId <= 0 || ! $user->canAccessClient($clientId)) {
                throw new HttpException(403, 'No hay un cliente en el alcance.');
            }

            return [
                'company_id' => null,
                'client_id' => $clientId,
                'installation_ids' => $user->assignedInstallationIds(),
            ];
        }

        throw new HttpException(403, 'Este token no cubre el Observatorio.');
    }
}
