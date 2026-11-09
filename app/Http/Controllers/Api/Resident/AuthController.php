<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Services\Resident\AuthenticateStructureAppUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function login(Request $request, AuthenticateStructureAppUserService $auth): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ]);

        $appUser = $auth->attempt($validated['login'], $validated['password']);
        $token = $appUser->createToken($validated['device_name'] ?? 'resident-app')->plainTextToken;
        $appUser->loadMissing(['member.structure.installation', 'client']);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $appUser->id,
                'login' => $appUser->login_email,
                'name' => $appUser->member?->full_name ?? $appUser->username,
                'unit' => $appUser->member?->structure?->full_path,
                'site' => $appUser->member?->structure?->installation?->name,
                'client' => $appUser->client?->name,
                'channel' => $appUser->delivery_channel,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $appUser = $request->user('sanctum');
        abort_unless($appUser instanceof \App\Models\StructureAppUser, 401);
        $appUser->loadMissing(['member.structure.installation', 'client']);

        return response()->json([
            'user' => [
                'id' => $appUser->id,
                'login' => $appUser->login_email,
                'name' => $appUser->member?->full_name ?? $appUser->username,
                'unit' => $appUser->member?->structure?->full_path,
                'site' => $appUser->member?->structure?->installation?->name,
                'client' => $appUser->client?->name,
                'channel' => $appUser->delivery_channel,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
