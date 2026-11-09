<?php

declare(strict_types=1);

namespace App\Services\Resident;

use App\Models\StructureAppUser;
use App\Models\StructureAppUserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SendResidentPushService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function toUser(StructureAppUser $appUser, string $title, string $body, array $data = []): int
    {
        $tokens = StructureAppUserDevice::query()
            ->where('structure_app_user_id', $appUser->id)
            ->pluck('push_token')
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $key = (string) config('services.fcm.server_key');
        if ($key === '') {
            Log::info('resident.push.queued', [
                'app_user_id' => $appUser->id,
                'tokens' => $tokens->count(),
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'fcm_configured' => false,
            ]);

            return $tokens->count();
        }

        $sent = 0;
        foreach ($tokens as $token) {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$key,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge(['click_action' => 'FLUTTER_NOTIFICATION_CLICK'], $data),
                'priority' => 'high',
            ]);

            if ($response->successful()) {
                $sent++;
            } else {
                Log::warning('resident.push.failed', [
                    'app_user_id' => $appUser->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Notifica a todos los usuarios app activos de una unidad (y opcionalmente un miembro).
     *
     * @param  array<string, mixed>  $data
     */
    public function toStructure(int $clientId, ?int $structureId, ?int $memberId, string $title, string $body, array $data = []): int
    {
        if ($structureId === null && $memberId === null) {
            return 0;
        }

        $users = StructureAppUser::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->where(function ($q) use ($structureId, $memberId): void {
                if ($memberId !== null) {
                    $q->where('member_id', $memberId);
                }
                if ($structureId !== null) {
                    $q->orWhereHas('member', fn ($m) => $m->where('structure_id', $structureId));
                }
            })
            ->get();

        $total = 0;
        foreach ($users as $user) {
            $total += $this->toUser($user, $title, $body, $data);
        }

        return $total;
    }
}
