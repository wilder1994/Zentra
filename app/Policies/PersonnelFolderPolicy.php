<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PersonnelFolder;
use App\Models\User;

final class PersonnelFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('company.settings.manage')
            || $user->can('company.settings.view')
            || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('company.settings.manage') || $user->hasRole('super-admin');
    }

    public function update(User $user, PersonnelFolder $folder): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, PersonnelFolder $folder): bool
    {
        return $this->create($user);
    }
}
