<?php

declare(strict_types=1);

namespace App\Services\Resident;

use App\Models\Client;
use App\Models\StructureAppUser;
use App\Models\StructureAppUserEvent;
use App\Services\Structure\RecordStructureAppUserEventService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthenticateStructureAppUserService
{
    public function __construct(
        private readonly RecordStructureAppUserEventService $events,
    ) {}

    public function attempt(string $login, string $password): StructureAppUser
    {
        $login = mb_strtolower(trim($login));
        [$username, $suffix] = $this->splitLogin($login);

        $client = Client::query()
            ->where('login_suffix', $suffix)
            ->whereNull('archived_at')
            ->first();

        if ($client === null) {
            throw ValidationException::withMessages(['login' => 'Usuario o contraseña incorrectos.']);
        }

        $appUser = StructureAppUser::query()
            ->where('client_id', $client->id)
            ->where('username', $username)
            ->where('is_active', true)
            ->first();

        if ($appUser === null || ! Hash::check($password, (string) $appUser->password)) {
            throw ValidationException::withMessages(['login' => 'Usuario o contraseña incorrectos.']);
        }

        $appUser->forceFill(['last_login_at' => now()])->save();
        $this->events->execute($appUser, StructureAppUserEvent::LOGIN);

        return $appUser;
    }

    /** @return array{0: string, 1: string} */
    private function splitLogin(string $login): array
    {
        if (! str_contains($login, '@')) {
            throw ValidationException::withMessages(['login' => 'Usa el formato usuario@sufijo.']);
        }

        [$username, $suffix] = explode('@', $login, 2);
        $username = trim($username);
        $suffix = trim($suffix);

        if ($username === '' || $suffix === '') {
            throw ValidationException::withMessages(['login' => 'Usa el formato usuario@sufijo.']);
        }

        return [$username, $suffix];
    }
}
