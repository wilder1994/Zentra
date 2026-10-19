<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El admin de cliente interno (responsable) entra a portería solo a mirar; operar es de los vigilantes. */
final class EnsurePorteriaReadOnly
{
    public const MESSAGE = 'Portería en solo lectura: los registros los hacen los vigilantes.';

    public static function appliesTo(?User $user): bool
    {
        return $user !== null
            && $user->hasRole('client-admin')
            && $user->admin_origin !== 'external';
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()
            || ! self::appliesTo($request->user())
            || $request->routeIs('access.ops.panic')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 403);
        }

        return redirect()->back()->with('error', self::MESSAGE);
    }
}
