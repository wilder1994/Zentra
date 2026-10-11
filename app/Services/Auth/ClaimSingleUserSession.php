<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ClaimSingleUserSession
{
    public const MESSAGE = 'Tienes una sesión abierta. ¿Cerrar la otra y seguir aquí?';

    public const CODE = 'session_active';

    public const DEVICE_COOKIE = 'zentra_device';

    public const PENDING_KEY = 'login.pending_takeover';

    public const PENDING_SECONDS = 120;

    public function isOccupied(User $user, ?string $device = null): bool
    {
        if (! filled($user->single_session_token)) {
            return false;
        }

        return $device === null || $user->single_session_device !== $device;
    }

    public function claim(User $user, ?string $device = null): string
    {
        $token = Str::random(40);
        $user->forceFill([
            'single_session_token' => $token,
            'single_session_device' => $device,
        ])->save();

        return $token;
    }

    public function signIn(Request $request, User $user, string $device, bool $remember): void
    {
        $token = $this->claim($user, $device);
        Auth::login($user, $remember);
        $request->session()->put('single_session_token', $token);
        $request->session()->forget(self::PENDING_KEY);
    }

    public function holdTakeover(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::PENDING_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires' => now()->addSeconds(self::PENDING_SECONDS)->getTimestamp(),
        ]);

        throw ValidationException::withMessages([
            'session_takeover' => self::MESSAGE,
        ]);
    }

    public function pendingTakeover(Request $request): ?array
    {
        $pending = $request->session()->pull(self::PENDING_KEY);
        if (! is_array($pending) || (int) ($pending['expires'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $pending;
    }

    public function release(User $user): void
    {
        $user->forceFill(['single_session_token' => null])->save();
    }

    public function deviceFor(Request $request): string
    {
        $device = (string) $request->cookie(self::DEVICE_COOKIE, '');
        if (strlen($device) !== 40) {
            $device = Str::random(40);
        }
        Cookie::queue(self::DEVICE_COOKIE, $device, 60 * 24 * 365 * 5);

        return $device;
    }
}
