<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Access\PorteriaDoorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePorteriaDoor
{
    public function __construct(
        private readonly PorteriaDoorService $doors,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('guardia')) {
            return $next($request);
        }

        if ($request->routeIs(
            'access.turnos.open',
            'access.turnos.store',
            'access.turnos.index',
            'access.turnos.close',
            'access.ops.alerts',
            'access.ops.panic',
        )) {
            $this->doors->bindSingleIfOnlyOne($request);

            return $next($request);
        }

        $door = $this->doors->bindSingleIfOnlyOne($request);
        if ($door !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Selecciona la puerta que vas a operar.',
            ], 422);
        }

        return redirect()
            ->route('access.turnos.open')
            ->with('error', 'Hay más de una puerta. Elige cuál vas a operar.');
    }
}
