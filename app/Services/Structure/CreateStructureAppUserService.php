<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Domain\Structure\Data\CreateStructureAppUserData;
use App\Mail\StructureAppUserCredentialsMail;
use App\Models\Client;
use App\Models\StructureAppUser;
use App\Models\StructureAppUserEvent;
use App\Models\StructureMember;
use App\Support\Resident\ResidentAppUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class CreateStructureAppUserService
{
    public function __construct(
        private readonly StructureAppUserCredentialFactory $credentials,
        private readonly RecordStructureAppUserEventService $events,
    ) {}

    /** @return array{appUser: StructureAppUser, plainPassword: string} */
    public function execute(CreateStructureAppUserData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $member = StructureMember::query()
                ->with(['structure', 'client'])
                ->whereKey($data->memberId)
                ->where('client_id', $data->clientId)
                ->first();

            if ($member === null) {
                throw ValidationException::withMessages(['member_id' => 'Selecciona una persona del censo.']);
            }
            if ($member->isMinor()) {
                throw ValidationException::withMessages(['member_id' => 'No se crea usuario app para menores de edad.']);
            }
            if (! $member->is_active) {
                throw ValidationException::withMessages(['member_id' => 'La persona está inactiva en el censo.']);
            }
            if (StructureAppUser::withTrashed()->where('member_id', $member->id)->exists()) {
                throw ValidationException::withMessages(['member_id' => 'Esta persona ya tiene (o tuvo) un usuario app. Reactívalo desde Usuarios app.']);
            }

            $email = trim((string) $member->email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'member_id' => 'La persona no tiene correo válido. Edítala en Personas y vuelve a intentar.',
                ]);
            }

            $structureId = (int) $member->structure_id;
            $activeCount = StructureAppUser::query()
                ->where('client_id', $data->clientId)
                ->where('is_active', true)
                ->whereHas('member', fn ($q) => $q->where('structure_id', $structureId))
                ->count();

            if ($activeCount >= ResidentAppUrl::maxUsersPerStructure()) {
                throw ValidationException::withMessages([
                    'member_id' => 'Esta unidad ya alcanzó el máximo de '.ResidentAppUrl::maxUsersPerStructure().' usuarios app.',
                ]);
            }

            $channel = $this->credentials->assertChannel($data->deliveryChannel);
            $username = $this->credentials->usernameFor($member, $data->clientId);
            $plainPassword = $this->credentials->plainPassword();

            $appUser = StructureAppUser::query()->create([
                'client_id' => $data->clientId,
                'member_id' => $member->id,
                'username' => $username,
                'email' => $email,
                'password' => $plainPassword,
                'is_active' => true,
                'delivery_channel' => $channel,
                'credentials_sent_at' => now(),
            ]);

            $member->update(['has_app_access' => true]);

            $this->events->execute($appUser, StructureAppUserEvent::CREATED, $data->actorUserId, [
                'channel' => $channel,
            ]);
            $this->events->execute($appUser, StructureAppUserEvent::CREDENTIALS_SENT, $data->actorUserId, [
                'to' => $email,
            ]);

            $client = Client::query()->findOrFail($data->clientId);
            Mail::to($email)->send(new StructureAppUserCredentialsMail(
                appUser: $appUser,
                plainPassword: $plainPassword,
                memberName: $member->full_name,
                clientName: (string) $client->name,
            ));

            return ['appUser' => $appUser, 'plainPassword' => $plainPassword];
        });
    }
}
