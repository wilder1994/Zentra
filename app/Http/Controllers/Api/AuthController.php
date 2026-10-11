<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ClaimSingleUserSession;
use App\Support\Company\CompanyServiceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:100',
            'replace_session' => 'sometimes|boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        $user->loadMissing('securityCompany');
        if (CompanyServiceAccess::isCut($user->securityCompany)) {
            throw ValidationException::withMessages([
                'email' => [CompanyServiceAccess::DENIED],
            ]);
        }

        $claim = app(ClaimSingleUserSession::class);
        $occupied = $claim->isOccupied($user) || $user->tokens()->exists();
        if ($occupied && ! $request->boolean('replace_session')) {
            return response()->json([
                'message' => ClaimSingleUserSession::MESSAGE,
                'code' => ClaimSingleUserSession::CODE,
            ], 409);
        }
        $claim->claim($user);
        $user->tokens()->delete();

        $token = $user->createToken($request->device_name ?? 'api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('primaryClient');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'client' => $user->primaryClient?->only(['id', 'name', 'slug']),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        app(ClaimSingleUserSession::class)->release($request->user());
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
