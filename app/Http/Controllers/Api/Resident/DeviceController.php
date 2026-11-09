<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\StructureAppUser;
use App\Models\StructureAppUserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var StructureAppUser $appUser */
        $appUser = $request->user('sanctum');
        abort_unless($appUser instanceof StructureAppUser, 401);

        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'in:android,ios,web'],
        ]);

        $device = StructureAppUserDevice::query()->updateOrCreate(
            [
                'structure_app_user_id' => $appUser->id,
                'push_token' => $validated['push_token'],
            ],
            [
                'platform' => $validated['platform'] ?? 'android',
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['id' => $device->id, 'ok' => true]);
    }
}
