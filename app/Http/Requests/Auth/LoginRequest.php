<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\ClaimSingleUserSession;
use App\Services\Auth\FindUserByLogin;
use App\Support\Company\CompanyServiceAccess;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => 'usuario o correo',
            'password' => 'contraseña',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('email'));
        $user = app(FindUserByLogin::class)->execute($login);

        if (! $user || ! Hash::check((string) $this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta está desactivada.',
            ]);
        }

        $user->loadMissing('securityCompany');
        if (CompanyServiceAccess::isCut($user->securityCompany) && ! CompanyServiceAccess::mayBrowseWhileCut($user)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => CompanyServiceAccess::DENIED,
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $claim = app(ClaimSingleUserSession::class);
        $device = $claim->deviceFor($this);
        if ($claim->isOccupied($user, $device)) {
            $claim->holdTakeover($this, $user, $this->boolean('remember'));
        }

        $claim->signIn($this, $user, $device, $this->boolean('remember'));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
