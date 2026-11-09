<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InstallationProfile;
use App\Models\User;

final class InstallationProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('company.settings.manage')
            || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, InstallationProfile $profile): bool
    {
        return $this->owns($user, $profile) && ! $profile->is_system;
    }

    public function delete(User $user, InstallationProfile $profile): bool
    {
        return $this->owns($user, $profile) && ! $profile->is_system;
    }

    private function owns(User $user, InstallationProfile $profile): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->can('company.settings.manage')
            && (int) $user->security_company_id === (int) $profile->security_company_id;
    }
}
