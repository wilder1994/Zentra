<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\ClaimSingleUserSession;
use App\Services\Auth\ResolveUserHomeRoute;
use App\Support\Platform\SupportCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly ResolveUserHomeRoute $resolveUserHomeRoute,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        return $this->redirectAfterLogin($request);
    }

    public function takeover(Request $request, ClaimSingleUserSession $claim): RedirectResponse
    {
        $pending = $claim->pendingTakeover($request);
        $user = $pending !== null ? User::query()->find((int) $pending['user_id']) : null;

        if ($user === null || ! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'email' => 'La confirmación venció. Ingresa de nuevo.',
            ]);
        }

        $claim->signIn($request, $user, $claim->deviceFor($request), (bool) $pending['remember']);

        return $this->redirectAfterLogin($request);
    }

    private function redirectAfterLogin(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        $user = $request->user();

        if ($user->must_change_password) {
            return redirect()->route('password.first');
        }

        $home = $this->resolveUserHomeRoute->forUser($user);

        if ($user->hasRole('super-admin')) {
            $intended = $request->session()->pull('url.intended', $home);
            if (SupportCompanyContext::isCompanyPanelUrl($intended)) {
                return redirect()
                    ->route('admin.dashboard')
                    ->with('warning', SupportCompanyContext::EXPIRED_FLASH);
            }

            return redirect()->to($intended);
        }

        return redirect()->intended($home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            app(ClaimSingleUserSession::class)->release($user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
