<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Domain\User\AccessGrantData;
use App\Domain\User\CreateUserData;
use App\Domain\User\UpdateUserData;
use App\Enums\AccessGrantScope;
use App\Enums\ClientAdminOrigin;
use App\Models\Client;
use App\Models\ClientUserAssignment;
use App\Models\ClientUserInstallationAssignment;
use App\Models\Installation;
use App\Models\User;
use App\Services\Auth\UserScopeResolver;
use App\Support\Auth\AssignableRoles;
use App\Support\Auth\GrantableModules;
use App\Support\Auth\UserManagementContext;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageScopedUserService
{
    public function __construct(
        private readonly UserScopeResolver $scopeResolver,
        private readonly AssertVigilantePorteriaService $assertVigilantePorteria,
        private readonly SyncCollaboratorAccessService $syncCollaboratorAccess,
    ) {}

    public function create(CreateUserData $data, User $actor, UserManagementContext $context): User
    {
        $this->assertRoleAllowed($data->role, $context, $actor);

        if (User::query()->where('username', $data->username)->exists()) {
            throw ValidationException::withMessages([
                'username' => 'Ya existe una cuenta con este usuario.',
            ]);
        }

        if (filled($data->email) && User::query()->where('email', $data->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con este email.',
            ]);
        }

        $companyId = $this->resolveCompanyId($data, $context, $actor);
        $origin = $this->resolveOrigin($data->role, $data->adminOrigin, $data->employeeId);
        $this->assertOriginRules($data->role, $origin, $data->employeeId, $context);
        $clientIds = $this->normalizeClientIds($data->clientIds, $data->role, $origin, $companyId, $context, $actor);
        $installationIds = $this->normalizeInstallationIds($data->installationIds, $data->role, $clientIds);
        if ($data->role === 'colaborador') {
            [$clientIds, $installationIds] = $this->idsFromGrants($data->grants, $companyId, $data->clientIds, $data->installationIds);
        }

        if ($data->role === 'guardia') {
            foreach ($clientIds as $clientId) {
                $this->assertVigilantePorteria->assert($data->employeeId, $clientId);
            }
        }

        return DB::transaction(function () use ($data, $actor, $companyId, $clientIds, $origin, $installationIds): User {
            $attributes = [
                'name' => $data->name,
                'username' => $data->username,
                'email' => filled($data->email) ? $data->email : null,
                'password' => $data->password,
                'is_active' => $data->isActive,
                'must_change_password' => $data->mustChangePassword,
                'security_company_id' => $companyId,
                'employee_id' => $data->employeeId,
                'job_title' => $data->jobTitle,
                'avatar_path' => $data->avatarPath,
                'admin_origin' => $origin?->value,
                'document_number' => $data->documentNumber,
                'email_verified_at' => now(),
            ];

            if ($data->role === 'supervisor') {
                $attributes['supervisor_code'] = $this->generateSupervisorCode($companyId);
            }

            $user = User::query()->create($attributes);
            $user->syncRoles([$data->role]);
            $this->syncClientAssignments($user, $clientIds, $data->role);
            $this->syncInstallationAssignments($user, $installationIds, $data->role, $data->installationPermission);
            $this->syncGrants($user, $actor, $data->role, $data->grants, $companyId);

            return $user->fresh(['roles', 'clients', 'assignedInstallations', 'moduleGrants']);
        });
    }

    public function update(User $target, UpdateUserData $data, User $actor, UserManagementContext $context): User
    {
        if ($data->role !== null) {
            $this->assertRoleAllowed($data->role, $context, $actor);
        }

        if (filled($data->email) && $data->email !== $target->email && User::query()->where('email', $data->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con este email.',
            ]);
        }

        $companyId = (int) ($target->security_company_id ?? $this->scopeCompanyId($actor) ?? 0) ?: null;
        $role = $data->role ?? $target->getRoleNames()->first() ?? '';
        $origin = $this->originFromUser($target, $role);
        $this->assertOriginRules($role, $origin, $target->employee_id !== null ? (int) $target->employee_id : null, $context);
        $clientIds = $data->clientIds ?? $target->clients()->pluck('clients.id')->map(fn ($id) => (int) $id)->all();
        $clientIds = $this->normalizeClientIds($clientIds, $role, $origin, $companyId, $context, $actor);
        $installationIds = $this->normalizeInstallationIds(
            $data->installationIds ?? $target->assignedInstallations()->pluck('installations.id')->map(fn ($id) => (int) $id)->all(),
            $role,
            $clientIds,
        );

        if ($role === 'colaborador') {
            [$clientIds, $installationIds] = $this->idsFromGrants(
                $data->grants ?? [],
                (int) $companyId,
                $data->clientIds ?? [],
                $data->installationIds ?? [],
            );
        }

        if ($role === 'guardia') {
            foreach ($clientIds as $clientId) {
                $this->assertVigilantePorteria->assert(
                    $target->employee_id !== null ? (int) $target->employee_id : null,
                    $clientId,
                    $target,
                );
            }
        }

        return DB::transaction(function () use ($target, $data, $actor, $role, $clientIds, $companyId, $installationIds): User {
            $attributes = [
                'name' => $data->name,
                'email' => filled($data->email) ? $data->email : null,
                'is_active' => $data->isActive,
                'job_title' => $data->jobTitle,
            ];

            if ($data->documentNumber !== null) {
                $attributes['document_number'] = $data->documentNumber;
            }

            if ($data->avatarPath !== null) {
                $attributes['avatar_path'] = $data->avatarPath;
            }

            if ($data->password !== null && $data->password !== '') {
                $attributes['password'] = $data->password;
            }

            $becomingSupervisor = $role === 'supervisor' && ! $target->hasRole('supervisor');
            $leavingSupervisor = $role !== 'supervisor' && $target->hasRole('supervisor');

            if ($becomingSupervisor || ($role === 'supervisor' && ($target->supervisor_code === null || $data->regenerateSupervisorCode))) {
                $attributes['supervisor_code'] = $this->generateSupervisorCode($companyId, $target->id);
            }

            if ($leavingSupervisor) {
                $attributes['supervisor_code'] = null;
            }

            $target->update($attributes);

            if ($data->role !== null) {
                $target->syncRoles([$data->role]);
            }

            $this->syncClientAssignments($target, $clientIds, $role);
            $this->syncInstallationAssignments(
                $target,
                $installationIds,
                $role,
                $data->installationPermission ?? $target->installationAssignments()->value('installation_permission') ?? 'admin',
            );
            $this->syncGrants($target, $actor, $role, $data->grants ?? [], $companyId);

            return $target->fresh(['roles', 'clients', 'assignedInstallations', 'moduleGrants']);
        });
    }

    public function setActive(User $target, User $actor, bool $active): void
    {
        if ($actor->is($target) && ! $active) {
            throw ValidationException::withMessages([
                'user' => 'No puede desactivar su propia cuenta.',
            ]);
        }

        if ($target->hasRole('super-admin') && ! $actor->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'user' => 'No puede desactivar un súper administrador.',
            ]);
        }

        if (! $active) {
            $target->tokens()->delete();
        }

        $target->update(['is_active' => $active]);
    }

    private function generateSupervisorCode(?int $companyId, ?int $exceptUserId = null): string
    {
        if ($companyId === null || $companyId <= 0) {
            throw ValidationException::withMessages([
                'role' => 'El supervisor de vigilancia debe pertenecer a una empresa.',
            ]);
        }

        for ($attempt = 0; $attempt < 40; $attempt++) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $exists = User::query()
                ->where('security_company_id', $companyId)
                ->where('supervisor_code', $code)
                ->when($exceptUserId, fn ($q) => $q->whereKeyNot($exceptUserId))
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'role' => 'No fue posible generar un código de supervisor único. Intenta de nuevo.',
        ]);
    }

    private function assertRoleAllowed(string $role, UserManagementContext $context, User $actor): void
    {
        $allowed = match ($context) {
            UserManagementContext::Platform => AssignableRoles::forPlatform(),
            UserManagementContext::Company => AssignableRoles::forCompanyActor($actor),
            UserManagementContext::Client => AssignableRoles::forClient(),
        };

        if (! in_array($role, $allowed, true)) {
            throw ValidationException::withMessages([
                'role' => 'No puedes asignar este rol desde este panel.',
            ]);
        }

        if ($role === 'super-admin' && ! $actor->hasRole('super-admin')) {
            throw ValidationException::withMessages([
                'role' => 'No puedes asignar el rol de súper administrador.',
            ]);
        }
    }

    private function resolveCompanyId(CreateUserData $data, UserManagementContext $context, User $actor): ?int
    {
        if ($context === UserManagementContext::Platform && $data->securityCompanyId) {
            return $data->securityCompanyId;
        }

        if ($context === UserManagementContext::Company) {
            return $this->scopeCompanyId($actor);
        }

        if ($context === UserManagementContext::Client) {
            $clientId = $this->scopeResolver->resolveClientTenantId($actor);

            if ($clientId === null) {
                throw ValidationException::withMessages([
                    'client_ids' => 'No hay cliente activo para asignar usuarios.',
                ]);
            }

            return (int) Client::query()->whereKey($clientId)->value('security_company_id');
        }

        return $data->securityCompanyId;
    }

    private function scopeCompanyId(User $actor): ?int
    {
        return app(ActingCompanyResolver::class)->id($actor);
    }

    /**
     * @param  list<int>  $clientIds
     * @return list<int>
     */
    private function normalizeClientIds(
        array $clientIds,
        string $role,
        ?ClientAdminOrigin $origin,
        ?int $companyId,
        UserManagementContext $context,
        User $actor,
    ): array {
        if (! in_array($role, AssignableRoles::requiringClientAssignment(), true)) {
            return [];
        }

        if ($context === UserManagementContext::Client) {
            $tenantId = $this->scopeResolver->resolveClientTenantId($actor);

            if ($tenantId === null) {
                throw ValidationException::withMessages([
                    'client_ids' => 'Cliente no disponible.',
                ]);
            }

            return [(int) $tenantId];
        }

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));

        if ($clientIds === []) {
            throw ValidationException::withMessages([
                'client_ids' => 'Selecciona al menos un cliente para este rol.',
            ]);
        }

        if (AssignableRoles::forcesSingleClient($role, $origin?->value) && count($clientIds) !== 1) {
            $message = $role === 'guardia'
                ? 'El vigilante debe quedar asignado a un solo cliente.'
                : 'El administrador externo queda amarrado a un solo cliente.';

            throw ValidationException::withMessages([
                'client_ids' => $message,
            ]);
        }

        if ($companyId !== null) {
            $validCount = Client::query()
                ->where('security_company_id', $companyId)
                ->whereIn('id', $clientIds)
                ->count();

            if ($validCount !== count($clientIds)) {
                throw ValidationException::withMessages([
                    'client_ids' => 'Uno o más clientes no pertenecen a la empresa.',
                ]);
            }
        }

        return $clientIds;
    }

    /**
     * @param  list<int>  $installationIds
     * @param  list<int>  $clientIds
     * @return list<int>
     */
    private function normalizeInstallationIds(array $installationIds, string $role, array $clientIds): array
    {
        if (! AssignableRoles::isInstallationAdmin($role)) {
            return [];
        }

        $installationIds = array_values(array_unique(array_map('intval', $installationIds)));

        if ($installationIds === []) {
            throw ValidationException::withMessages([
                'installation_ids' => 'Selecciona al menos una instalación.',
            ]);
        }

        $clientId = $clientIds[0] ?? null;
        if ($clientId === null) {
            throw ValidationException::withMessages([
                'client_ids' => 'El admin de instalaciones requiere un cliente.',
            ]);
        }

        $validCount = Installation::query()
            ->where('client_id', $clientId)
            ->whereIn('id', $installationIds)
            ->count();

        if ($validCount !== count($installationIds)) {
            throw ValidationException::withMessages([
                'installation_ids' => 'Una o más instalaciones no pertenecen a ese cliente.',
            ]);
        }

        return $installationIds;
    }

    private function resolveOrigin(string $role, ?ClientAdminOrigin $origin, ?int $employeeId): ?ClientAdminOrigin
    {
        if (! AssignableRoles::isClientFacingAdmin($role)) {
            return null;
        }

        if (AssignableRoles::isInstallationAdmin($role)) {
            return ClientAdminOrigin::External;
        }

        if ($origin !== null) {
            return $origin;
        }

        return $employeeId !== null ? ClientAdminOrigin::Internal : ClientAdminOrigin::External;
    }

    private function originFromUser(User $user, string $role): ?ClientAdminOrigin
    {
        if (! AssignableRoles::isClientFacingAdmin($role)) {
            return null;
        }

        if (AssignableRoles::isInstallationAdmin($role)) {
            return ClientAdminOrigin::External;
        }

        $raw = $user->admin_origin;
        if (is_string($raw) && $raw !== '') {
            return ClientAdminOrigin::from($raw);
        }

        return $user->employee_id !== null ? ClientAdminOrigin::Internal : ClientAdminOrigin::External;
    }

    private function assertOriginRules(
        string $role,
        ?ClientAdminOrigin $origin,
        ?int $employeeId,
        UserManagementContext $context,
    ): void {
        if (! AssignableRoles::isClientFacingAdmin($role)) {
            return;
        }

        if (AssignableRoles::isInstallationAdmin($role) && $origin !== ClientAdminOrigin::External) {
            throw ValidationException::withMessages([
                'origin' => 'El admin de instalaciones es siempre externo.',
            ]);
        }

        if ($origin === ClientAdminOrigin::Internal && $employeeId === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'El administrador interno debe ser un empleado de la empresa.',
            ]);
        }

        if ($origin === ClientAdminOrigin::External && $employeeId !== null) {
            throw ValidationException::withMessages([
                'employee_id' => 'El administrador externo no se crea sobre un empleado.',
            ]);
        }

        if ($context === UserManagementContext::Client && $origin === ClientAdminOrigin::Internal) {
            throw ValidationException::withMessages([
                'origin' => 'Desde el panel del cliente solo se dan de alta administradores externos.',
            ]);
        }
    }

    /**
     * @param  list<AccessGrantData>  $grants
     */
    private function syncGrants(User $user, User $actor, string $role, array $grants, ?int $companyId): void
    {
        if (! GrantableModules::usesMatrix($role)) {
            $this->syncCollaboratorAccess->clear($user);

            return;
        }

        $grants = $this->filterGrantsForRole($role, $grants);
        if ($grants === []) {
            $grants = $this->defaultGrantsForRole($user, $role, $companyId);
        }

        $actor->loadMissing('moduleGrants');
        $this->syncCollaboratorAccess->sync($user, $actor, $grants, (int) $companyId);
    }

    /**
     * @param  list<AccessGrantData>  $grants
     * @return list<AccessGrantData>
     */
    private function filterGrantsForRole(string $role, array $grants): array
    {
        return match ($role) {
            'company-admin' => array_values(array_filter(
                $grants,
                fn (AccessGrantData $grant): bool => $grant->scope === AccessGrantScope::Company,
            )),
            'client-admin' => array_values(array_filter(
                $grants,
                fn (AccessGrantData $grant): bool => $grant->scope === AccessGrantScope::Client,
            )),
            'client-installation-admin' => array_values(array_filter(
                $grants,
                fn (AccessGrantData $grant): bool => $grant->scope === AccessGrantScope::Installation,
            )),
            default => $grants,
        };
    }

    /**
     * @return list<AccessGrantData>
     */
    private function defaultGrantsForRole(User $user, string $role, ?int $companyId): array
    {
        if ($role === 'company-admin' && $companyId) {
            return GrantableModules::defaultCompanyManageGrants((int) $companyId);
        }

        if ($role === 'client-admin') {
            $user->loadMissing('clients');
            $grants = [];
            foreach ($user->clients as $client) {
                $grants = array_merge(
                    $grants,
                    GrantableModules::defaultScopedManageGrants(AccessGrantScope::Client, (int) $client->id),
                );
            }

            return $grants;
        }

        if ($role === 'client-installation-admin') {
            $user->loadMissing('assignedInstallations');
            $grants = [];
            foreach ($user->assignedInstallations as $installation) {
                $grants = array_merge(
                    $grants,
                    GrantableModules::defaultScopedManageGrants(AccessGrantScope::Installation, (int) $installation->id),
                );
            }

            return $grants;
        }

        return [];
    }

    /**
     * @param  list<AccessGrantData>  $grants
     * @param  list<int>  $clientIds
     * @param  list<int>  $installationIds
     * @return array{0: list<int>, 1: list<int>}
     */
    private function idsFromGrants(array $grants, int $companyId, array $clientIds, array $installationIds): array
    {
        $clients = array_map('intval', $clientIds);
        $sites = array_map('intval', $installationIds);

        foreach ($grants as $grant) {
            if ($grant->scope === AccessGrantScope::Client) {
                $clients[] = $grant->scopeId;
            }
            if ($grant->scope === AccessGrantScope::Installation) {
                $sites[] = $grant->scopeId;
                $clientId = (int) Installation::query()->whereKey($grant->scopeId)->value('client_id');
                if ($clientId > 0) {
                    $clients[] = $clientId;
                }
            }
        }

        return [
            array_values(array_unique(array_filter($clients))),
            array_values(array_unique(array_filter($sites))),
        ];
    }

    /** @param list<int> $installationIds */
    private function syncInstallationAssignments(User $user, array $installationIds, string $role, string $installationPermission = 'admin'): void
    {
        ClientUserInstallationAssignment::query()->where('user_id', $user->id)->delete();

        if (! AssignableRoles::isInstallationAdmin($role) && $role !== 'colaborador') {
            return;
        }

        $permission = $installationPermission === 'support' ? 'support' : 'admin';

        foreach ($installationIds as $installationId) {
            ClientUserInstallationAssignment::query()->create([
                'user_id' => $user->id,
                'installation_id' => $installationId,
                'installation_permission' => $permission,
            ]);
        }
    }

    /** @param list<int> $clientIds */
    private function syncClientAssignments(User $user, array $clientIds, string $role): void
    {
        if (! in_array($role, AssignableRoles::requiringClientAssignment(), true) && $role !== 'colaborador') {
            ClientUserAssignment::query()->where('user_id', $user->id)->delete();
            $user->update(['primary_client_id' => null]);

            return;
        }

        if ($role === 'colaborador' && $clientIds === []) {
            ClientUserAssignment::query()->where('user_id', $user->id)->delete();
            $user->update(['primary_client_id' => null]);

            return;
        }

        ClientUserAssignment::query()->where('user_id', $user->id)->delete();

        foreach ($clientIds as $index => $clientId) {
            ClientUserAssignment::query()->create([
                'user_id' => $user->id,
                'client_id' => $clientId,
                'is_primary' => $index === 0,
                'assigned_at' => now(),
            ]);
        }

        $user->update(['primary_client_id' => $clientIds[0]]);
    }
}
