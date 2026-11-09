<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Mail\StructureAppUserCredentialsMail;
use App\Models\Client;
use App\Models\StructureAppUser;
use App\Models\StructureAppUserEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class ResendStructureAppUserCredentialsService
{
    public function __construct(
        private readonly StructureAppUserCredentialFactory $credentials,
        private readonly RecordStructureAppUserEventService $events,
    ) {}

    public function execute(StructureAppUser $appUser, ?int $actorUserId): void
    {
        DB::transaction(function () use ($appUser, $actorUserId): void {
            $appUser->loadMissing(['member', 'client']);
            $email = trim((string) ($appUser->email ?: $appUser->member?->email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email' => 'No hay correo válido para reenviar las credenciales.',
                ]);
            }

            $plainPassword = $this->credentials->plainPassword();
            $appUser->update([
                'password' => $plainPassword,
                'email' => $email,
                'credentials_sent_at' => now(),
            ]);

            $this->events->execute($appUser, StructureAppUserEvent::CREDENTIALS_SENT, $actorUserId, [
                'to' => $email,
                'resent' => true,
            ]);

            $client = $appUser->client ?? Client::query()->findOrFail((int) $appUser->client_id);
            Mail::to($email)->send(new StructureAppUserCredentialsMail(
                appUser: $appUser->fresh() ?? $appUser,
                plainPassword: $plainPassword,
                memberName: (string) ($appUser->member?->full_name ?? $appUser->username),
                clientName: (string) $client->name,
            ));
        });
    }
}
