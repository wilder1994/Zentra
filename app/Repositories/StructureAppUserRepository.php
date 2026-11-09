<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\StructureAppUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class StructureAppUserRepository
{
    public function paginateForClient(int $clientId, int $perPage = 20, bool $includeArchived = false): LengthAwarePaginator
    {
        $query = StructureAppUser::query()
            ->with(['member.structure.installation', 'client'])
            ->where('client_id', $clientId)
            ->orderBy('username');

        if ($includeArchived) {
            $query->onlyTrashed();
        }

        return $query->paginate($perPage);
    }

    public function usernameExists(int $clientId, string $username, ?int $exceptId = null): bool
    {
        $query = StructureAppUser::query()
            ->where('client_id', $clientId)
            ->where('username', $username);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}
