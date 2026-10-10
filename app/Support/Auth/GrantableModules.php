<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Domain\User\AccessGrantData;
use App\Enums\AccessGrantLevel;
use App\Enums\AccessGrantScope;
use App\Models\User;

final class GrantableModules
{
    /**
     * @return array<string, array{label: string, view: list<string>, manage: list<string>, route?: string}>
     */
    public static function forScope(AccessGrantScope $scope): array
    {
        if ($scope === AccessGrantScope::Installation) {
            $modules = self::forScope(AccessGrantScope::Client);
            unset($modules['users']);
            $modules['members']['manage'] = ['client.members.manage'];

            return $modules;
        }

        return match ($scope) {
            AccessGrantScope::Company => [
                'dashboard' => [
                    'label' => 'Mi empresa',
                    'view' => ['company.dashboard'],
                    'manage' => ['company.dashboard'],
                    'route' => 'company.dashboard',
                ],
                'billing' => [
                    'label' => 'Facturación',
                    'view' => ['company.billing.manage'],
                    'manage' => ['company.billing.manage'],
                    'route' => 'company.billing.index',
                ],
                'clients' => [
                    'label' => 'Clientes',
                    'view' => ['company.clients.view'],
                    'manage' => ['company.clients.view', 'company.clients.manage'],
                    'route' => 'company.clients.index',
                ],
                'installations' => [
                    'label' => 'Instalaciones',
                    'view' => ['company.installations.view'],
                    'manage' => ['company.installations.view', 'company.installations.manage'],
                    'route' => 'company.installations.index',
                ],
                'observatory' => [
                    'label' => 'Observatorio',
                    'view' => ['observatory.view'],
                    'manage' => ['observatory.view', 'observatory.events.update'],
                    'route' => 'company.observatory.events.index',
                ],
                'supervision' => [
                    'label' => 'Supervisión',
                    'view' => ['company.supervision.view'],
                    'manage' => ['company.supervision.view'],
                    'route' => 'company.supervision.index',
                ],
                'panics' => [
                    'label' => 'Atención de pánicos',
                    'view' => ['ops.panic.attend'],
                    'manage' => ['ops.panic.attend'],
                    'route' => 'company.panics.index',
                ],
                'downloads' => [
                    'label' => 'Descargas',
                    'view' => ['company.downloads.view'],
                    'manage' => ['company.downloads.view'],
                    'route' => 'company.downloads.index',
                ],
                'employees' => [
                    'label' => 'Empleados',
                    'view' => ['company.employees.view'],
                    'manage' => ['company.employees.view', 'company.employees.manage'],
                    'route' => 'company.employees.index',
                ],
                'documents' => [
                    'label' => 'Documentos',
                    'view' => ['company.documents.view'],
                    'manage' => ['company.documents.view', 'company.documents.manage'],
                    'route' => 'company.personnel-documents.index',
                ],
                'users' => [
                    'label' => 'Usuarios',
                    'view' => ['company.users.view'],
                    'manage' => ['company.users.view', 'company.users.assign'],
                    'route' => 'company.users.index',
                ],
                'profile' => [
                    'label' => 'Mis datos',
                    'view' => ['company.profile.manage'],
                    'manage' => ['company.profile.manage'],
                    'route' => 'company.settings.edit',
                ],
                'settings' => [
                    'label' => 'Ajustes',
                    'view' => ['company.settings.view'],
                    'manage' => ['company.settings.view', 'company.settings.manage'],
                    'route' => 'company.job-titles.index',
                ],
            ],
            AccessGrantScope::Client => [
                'sig' => [
                    'label' => 'Resumen (tablero SIG)',
                    'view' => ['ops.sig.view'],
                    'manage' => ['ops.sig.view'],
                    'route' => 'client.dashboard',
                ],
                'observatory' => [
                    'label' => 'Observatorio',
                    'view' => ['observatory.view'],
                    'manage' => ['observatory.view', 'observatory.events.update'],
                ],
                'structures' => [
                    'label' => 'Instalaciones / nodos',
                    'view' => ['client.structures.manage'],
                    'manage' => ['client.structures.manage'],
                ],
                'members' => [
                    'label' => 'Personas',
                    'view' => ['client.members.manage'],
                    'manage' => ['client.members.manage', 'client.settings.manage'],
                ],
                'vehicles' => [
                    'label' => 'Vehículos',
                    'view' => ['client.vehicles.manage'],
                    'manage' => ['client.vehicles.manage'],
                ],
                'pets' => [
                    'label' => 'Mascotas',
                    'view' => ['client.pets.manage'],
                    'manage' => ['client.pets.manage'],
                ],
                'authorizations' => [
                    'label' => 'Autorizaciones',
                    'view' => ['client.authorizations.manage'],
                    'manage' => ['client.authorizations.manage'],
                ],
                'users' => [
                    'label' => 'Usuarios',
                    'view' => ['client.users.manage'],
                    'manage' => ['client.users.manage'],
                ],
                'app_users' => [
                    'label' => 'Accesos (app)',
                    'view' => ['client.app_users.manage'],
                    'manage' => ['client.app_users.manage'],
                ],
                'employees' => [
                    'label' => 'Empleados',
                    'view' => ['company.employees.view'],
                    'manage' => ['company.employees.view', 'company.employees.manage'],
                ],
                'documents' => [
                    'label' => 'Documentos de personal',
                    'view' => ['company.documents.view'],
                    'manage' => ['company.documents.view', 'company.documents.manage'],
                ],
            ],
        };
    }

    /** @return list<string> */
    public static function keys(AccessGrantScope $scope): array
    {
        return array_keys(self::forScope($scope));
    }

    public static function usesMatrix(string $role): bool
    {
        return in_array($role, ['colaborador', 'company-admin', 'client-admin', 'client-installation-admin'], true);
    }

    public static function usesCompanyMatrix(string $role): bool
    {
        return in_array($role, ['colaborador', 'company-admin'], true);
    }

    public static function usesClientMatrix(string $role): bool
    {
        return in_array($role, ['colaborador', 'client-admin'], true);
    }

    public static function usesInstallationMatrix(string $role): bool
    {
        return in_array($role, ['colaborador', 'client-installation-admin'], true);
    }

    /**
     * @return list<AccessGrantData>
     */
    public static function defaultCompanyManageGrants(int $companyId): array
    {
        return self::defaultManageGrants(AccessGrantScope::Company, $companyId);
    }

    /**
     * @return list<AccessGrantData>
     */
    public static function defaultScopedManageGrants(AccessGrantScope $scope, int $scopeId): array
    {
        return self::defaultManageGrants($scope, $scopeId);
    }

    /**
     * @return list<AccessGrantData>
     */
    private static function defaultManageGrants(AccessGrantScope $scope, int $scopeId): array
    {
        $grants = [];
        foreach (self::keys($scope) as $module) {
            if ($scope !== AccessGrantScope::Company && in_array($module, self::optInModules(), true)) {
                continue;
            }
            $grants[] = new AccessGrantData($scope, $scopeId, $module, AccessGrantLevel::Manage);
        }

        return $grants;
    }

    /** @return list<string> */
    public static function optInModules(): array
    {
        return ['employees', 'documents'];
    }

    /** @return list<string> */
    public static function censusAliasModules(): array
    {
        return ['structures', 'members', 'vehicles', 'pets', 'authorizations'];
    }

    /** @return list<string> */
    public static function permissionsFor(AccessGrantScope $scope, string $module, AccessGrantLevel $level): array
    {
        $catalog = self::forScope($scope)[$module] ?? null;
        if ($catalog === null) {
            return [];
        }

        return $level === AccessGrantLevel::Manage ? $catalog['manage'] : $catalog['view'];
    }

    public static function firstCompanyHome(User $user): string
    {
        foreach (self::forScope(AccessGrantScope::Company) as $row) {
            $route = $row['route'] ?? null;
            $permission = $row['view'][0] ?? null;
            if ($route === null || $permission === null) {
                continue;
            }
            if ($user->can($permission)) {
                return route($route);
            }
        }

        return route('profile.edit');
    }
}
