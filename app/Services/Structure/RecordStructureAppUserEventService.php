<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Models\StructureAppUser;
use App\Models\StructureAppUserEvent;

final class RecordStructureAppUserEventService
{
    /** @param  array<string, mixed>|null  $meta */
    public function execute(
        StructureAppUser $appUser,
        string $event,
        ?int $actorUserId = null,
        ?array $meta = null,
    ): StructureAppUserEvent {
        return StructureAppUserEvent::query()->create([
            'client_id' => (int) $appUser->client_id,
            'structure_app_user_id' => (int) $appUser->id,
            'actor_user_id' => $actorUserId,
            'event' => $event,
            'meta' => $meta,
        ]);
    }
}
