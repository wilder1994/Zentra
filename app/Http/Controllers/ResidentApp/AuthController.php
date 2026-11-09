<?php

declare(strict_types=1);

namespace App\Http\Controllers\ResidentApp;

use App\Http\Controllers\Controller;
use App\Services\Resident\AuthenticateStructureAppUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('resident')->check()) {
            return redirect()->route('resident-app.home');
        }

        return view('resident-app.login');
    }

    public function store(Request $request, AuthenticateStructureAppUserService $auth): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:100'],
        ]);

        $appUser = $auth->attempt($validated['login'], $validated['password']);
        Auth::guard('resident')->login($appUser, false);
        $request->session()->regenerate();

        return redirect()->intended(route('resident-app.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('resident')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('resident-app.login');
    }
}
