<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Access\TurnoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOpenShift
{
    public function __construct(private readonly TurnoService $turnoService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! config('access.shifts.enforced', false)) {
            return $next($request);
        }

        if ($this->turnoService->isShiftOptionalFor($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'access.turnos.open',
            'access.turnos.store',
            'access.turnos.index',
            'access.turnos.close',
            'access.ops.alerts',
            'access.ops.panic',
            'logout',
        )) {
            return $next($request);
        }

        if (! $this->turnoService->hasOpenShiftFor($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Debes iniciar tu turno antes de operar en la portería.',
                ], 422);
            }

            return redirect()->route('access.turnos.open')
                ->with('error', 'Debes iniciar tu turno antes de operar en la portería.');
        }

        return $next($request);
    }
}
