<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MemberType;
use App\Models\User;

final class MemberTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('client.members.manage') || $user->can('client.settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('client.settings.manage');
    }

    public function update(User $user, MemberType $memberType): bool
    {
        return $this->owns($user, $memberType);
    }

    public function delete(User $user, MemberType $memberType): bool
    {
        return $this->owns($user, $memberType);
    }

    private function owns(User $user, MemberType $memberType): bool
    {
        return $user->can('client.settings.manage')
            && $user->canAccessClient((int) $memberType->client_id);
    }
}
