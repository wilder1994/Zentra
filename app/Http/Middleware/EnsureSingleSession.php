<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = $request->user();
        $expected = $user?->single_session_token;
        if (! is_string($expected) || $expected === '') {
            return $next($request);
        }

        if ($request->session()->get('single_session_token') === $expected || $user->hasRole('super-admin')) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Tu sesión se cerró porque entraste en otro dispositivo.',
                'code' => 'session_replaced',
            ], 401);
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Tu sesión se cerró porque entraste en otro dispositivo.',
        ]);
    }
}
