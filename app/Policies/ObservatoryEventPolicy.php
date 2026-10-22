<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ObservatoryEvent;
use App\Models\User;
use App\Support\Auth\AssignableRoles;

final class ObservatoryEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('observatory.view');
    }

    public function view(User $user, ObservatoryEvent $event): bool
    {
        if (! $user->can('observatory.view')) {
            return false;
        }

        if ($user->hasRole('company-admin')) {
            return (int) $event->client?->security_company_id === (int) $user->security_company_id
                || (int) $event->client?->security_company_id === (int) app(\App\Support\Platform\ActingCompanyResolver::class)->id($user);
        }

        if (! $user->canAccessClient((int) $event->client_id)) {
            return false;
        }

        return $user->canAccessInstallation((int) $event->installation_id);
    }

    public function update(User $user, ObservatoryEvent $event): bool
    {
        if (! $user->can('observatory.events.update')) {
            return false;
        }

        if ($user->hasRole('company-admin') || $user->hasRole('colaborador') || $user->hasRole('super-admin')) {
            $companyId = (int) (app(\App\Support\Platform\ActingCompanyResolver::class)->id($user) ?? $user->security_company_id);
            if ((int) $event->client?->security_company_id !== $companyId) {
                return false;
            }

            return ! $user->hasRole('colaborador') || $user->canAccessClient((int) $event->client_id);
        }

        return $user->hasRole(AssignableRoles::CLIENT_INSTALLATION_ADMIN)
            && $user->canAccessInstallation((int) $event->installation_id)
            && ! $user->isSiteSupport((int) $event->installation_id);
    }
}
