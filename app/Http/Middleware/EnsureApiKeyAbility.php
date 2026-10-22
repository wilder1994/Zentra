<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Api\ApiAbilities;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las API keys de integración solo pasan por los endpoints que se les dieron.
 * Los tokens de sesión personal (login, app de campo) siguen como antes.
 */
final class EnsureApiKeyAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->is_integration) {
            return $next($request);
        }

        if ($ability === ApiAbilities::HUMAN || ! $token->can($ability)) {
            return response()->json([
                'message' => $ability === ApiAbilities::HUMAN
                    ? 'Las API keys de integración no usan este endpoint.'
                    : 'Esta API key no tiene permiso para «'.ApiAbilities::label($ability).'».',
                'code' => 'api_key_ability',
            ], 403);
        }

        return $next($request);
    }
}
