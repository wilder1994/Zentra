<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Models\StructureAppUser;
use App\Models\StructureAppUserEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateStructureAppUserService
{
    public function __construct(
        private readonly StructureAppUserCredentialFactory $credentials,
        private readonly RecordStructureAppUserEventService $events,
    ) {}

    public function execute(
        StructureAppUser $appUser,
        string $deliveryChannel,
        bool $isActive,
        ?int $actorUserId,
    ): StructureAppUser {
        return DB::transaction(function () use ($appUser, $deliveryChannel, $isActive, $actorUserId): StructureAppUser {
            $channel = $this->credentials->assertChannel($deliveryChannel);
            $before = [
                'delivery_channel' => $appUser->delivery_channel,
                'is_active' => $appUser->is_active,
            ];

            $appUser->update([
                'delivery_channel' => $channel,
                'is_active' => $isActive,
            ]);

            if ($appUser->member) {
                $appUser->member->update(['has_app_access' => $isActive]);
            }

            $this->events->execute($appUser, StructureAppUserEvent::UPDATED, $actorUserId, [
                'before' => $before,
                'after' => [
                    'delivery_channel' => $channel,
                    'is_active' => $isActive,
                ],
            ]);

            return $appUser->refresh();
        });
    }

    public function archive(StructureAppUser $appUser, ?int $actorUserId): void
    {
        DB::transaction(function () use ($appUser, $actorUserId): void {
            $appUser->update(['is_active' => false]);
            $appUser->member?->update(['has_app_access' => false]);
            $this->events->execute($appUser, StructureAppUserEvent::ARCHIVED, $actorUserId);
            $appUser->delete();
        });
    }

    public function restore(StructureAppUser $appUser, ?int $actorUserId): StructureAppUser
    {
        if (! $appUser->trashed()) {
            throw ValidationException::withMessages(['app_user' => 'La cuenta no está archivada.']);
        }

        return DB::transaction(function () use ($appUser, $actorUserId): StructureAppUser {
            $appUser->restore();
            $appUser->update(['is_active' => true]);
            $appUser->member?->update(['has_app_access' => true]);
            $this->events->execute($appUser, StructureAppUserEvent::RESTORED, $actorUserId);

            return $appUser->refresh();
        });
    }
}
