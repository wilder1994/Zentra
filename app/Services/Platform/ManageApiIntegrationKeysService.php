<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Domain\User\AccessGrantData;
use App\Enums\AccessGrantLevel;
use App\Enums\AccessGrantScope;
use App\Models\ApiIntegrationKey;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\User\SyncCollaboratorAccessService;
use App\Support\Api\ApiAbilities;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Alta, revocación y regeneración de API keys de integración (solo súper admin). */
final class ManageApiIntegrationKeysService
{
    public function __construct(
        private readonly SyncCollaboratorAccessService $access,
    ) {}

    /**
     * @param  array{name: string, user_mode: string, user_id?: ?int, new_user_name?: ?string, new_user_email?: ?string, client_ids?: list<int>, abilities: list<string>, expires_at?: ?CarbonInterface}  $data
     * @return array{0: ApiIntegrationKey, 1: string}
     */
    public function create(SecurityCompany $company, User $actor, array $data): array
    {
        $abilities = array_values(array_intersect($data['abilities'], ApiAbilities::all()));
        if ($abilities === []) {
            throw ValidationException::withMessages(['abilities' => 'Elige al menos un permiso para la key.']);
        }

        return DB::transaction(function () use ($company, $actor, $data, $abilities): array {
            $user = $data['user_mode'] === 'new'
                ? $this->createIntegrationUser($company, $actor, $data, $abilities)
                : $this->existingUser($company, (int) ($data['user_id'] ?? 0));

            $key = new ApiIntegrationKey([
                'security_company_id' => $company->id,
                'user_id' => $user->id,
                'name' => trim($data['name']),
                'abilities' => $abilities,
                'expires_at' => $data['expires_at'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);
            $plain = $this->issueToken($key, $user);

            return [$key, $plain];
        });
    }

    public function revoke(ApiIntegrationKey $key, User $actor): void
    {
        if ($key->isRevoked()) {
            return;
        }

        DB::transaction(function () use ($key, $actor): void {
            $key->token()->delete();
            $key->forceFill([
                'personal_access_token_id' => null,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
            ])->save();
        });
    }

    public function regenerate(ApiIntegrationKey $key): string
    {
        if ($key->isRevoked()) {
            throw ValidationException::withMessages(['key' => 'Una key revocada no se regenera. Crea una nueva.']);
        }
        if ($key->expires_at !== null && $key->expires_at->isPast()) {
            throw ValidationException::withMessages(['key' => 'La key ya venció. Crea una nueva con otra fecha (o sin vencimiento).']);
        }

        $user = $key->user;
        if (! $user instanceof User || ! $user->is_active) {
            throw ValidationException::withMessages(['key' => 'El usuario de la key está inactivo. Actívalo antes de regenerar.']);
        }

        return DB::transaction(function () use ($key, $user): string {
            $key->token()->delete();
            $key->regenerated_at = now();

            return $this->issueToken($key, $user);
        });
    }

    private function issueToken(ApiIntegrationKey $key, User $user): string
    {
        $new = $user->createToken('integration: '.$key->name, $key->abilities, $key->expires_at);
        $new->accessToken->forceFill(['is_integration' => true])->save();

        $key->personal_access_token_id = $new->accessToken->getKey();
        $key->token_hint = Str::substr($new->plainTextToken, -4);
        $key->save();

        return $new->plainTextToken;
    }

    private function existingUser(SecurityCompany $company, int $userId): User
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('security_company_id', $company->id)
            ->where('is_active', true)
            ->first();

        if (! $user instanceof User || $user->hasRole('super-admin')) {
            throw ValidationException::withMessages(['user_id' => 'Elige un usuario activo de esta empresa.']);
        }

        return $user;
    }

    /**
     * Usuario externo solo para la integración: colaborador con el Observatorio en Ver,
     * en toda la empresa o en los clientes elegidos. Clave aleatoria: no entra al panel.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $abilities
     */
    private function createIntegrationUser(SecurityCompany $company, User $actor, array $data, array $abilities): User
    {
        $usesObservatory = collect($abilities)->contains(fn (string $a): bool => str_starts_with($a, 'observatory.'));
        if (! $usesObservatory) {
            throw ValidationException::withMessages([
                'abilities' => 'Un usuario de integración nuevo trabaja con el Observatorio. Para Portería usa un usuario existente (residente o anfitrión).',
            ]);
        }

        $name = trim((string) ($data['new_user_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['new_user_name' => 'Escribe el nombre del usuario de integración.']);
        }

        $email = trim((string) ($data['new_user_email'] ?? ''));
        if ($email === '') {
            $email = 'api.'.Str::slug($company->displayName()).'.'.Str::lower(Str::random(6)).'@integraciones.zentra';
        }
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['new_user_email' => 'Ese correo ya tiene un usuario.']);
        }

        $clientIds = array_values(array_unique(array_map('intval', $data['client_ids'] ?? [])));
        $validClients = Client::query()
            ->where('security_company_id', $company->id)
            ->whereKey($clientIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        if (count($validClients) !== count($clientIds)) {
            throw ValidationException::withMessages(['client_ids' => 'Hay clientes que no son de esta empresa.']);
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'job_title' => 'Integración API',
            'password' => Str::password(40),
            'is_active' => true,
            'must_change_password' => false,
            'security_company_id' => $company->id,
            'admin_origin' => 'external',
        ]);
        $user->syncRoles(['colaborador']);

        $grants = $validClients === []
            ? [new AccessGrantData(AccessGrantScope::Company, (int) $company->id, 'observatory', AccessGrantLevel::View)]
            : array_map(
                fn (int $clientId): AccessGrantData => new AccessGrantData(AccessGrantScope::Client, $clientId, 'observatory', AccessGrantLevel::View),
                $validClients,
            );
        $this->access->sync($user, $actor, $grants, (int) $company->id, true);

        return $user;
    }
}
