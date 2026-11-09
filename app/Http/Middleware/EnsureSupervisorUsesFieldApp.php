<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSupervisorUsesFieldApp
{
    /** @var list<string> */
    private array $except = [
        'password.first',
        'password.update',
        'logout',
        'supervisor.app-only',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! method_exists($user, 'hasRole') || ! $user->hasRole('supervisor')) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if (in_array($route, $this->except, true)) {
            return $next($request);
        }

        if ($user->must_change_password) {
            return redirect()->route('password.first');
        }

        return redirect()->route('supervisor.app-only');
    }
}
