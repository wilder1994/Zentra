<?php

declare(strict_types=1);

namespace App\Support\Company;

use App\Enums\AccessGrantLevel;
use App\Enums\AccessGrantScope;
use App\Support\Auth\GrantableModules;
use Spatie\Permission\Models\Role;

/**
 * Mientras se opera un cliente desde el expediente, los permisos de portería y panel cliente
 * son los del rol que se opera (guarda, admin de cliente, admin de instalación), aunque quien
 * opere sea súper admin o admin de empresa.
 */
final class OperateAsRole
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    public function role(): ?string
    {
        return match (CompanyOperateContext::mode()) {
            CompanyOperateContext::MODE_PORTERIA => 'guardia',
            CompanyOperateContext::MODE_CLIENTE => CompanyOperateContext::installationId() !== null
                ? 'client-installation-admin'
                : 'client-admin',
            default => null,
        };
    }

    public function applies(): bool
    {
        return request()->route() !== null
            && $this->role() !== null
            && request()->routeIs('access.*', 'client.*');
    }

    /** Null = no aplica (sigue la regla normal). */
    public function allows(string $ability): ?bool
    {
        if (! in_array($ability, config('access.permissions', []), true) || ! $this->applies()) {
            return null;
        }

        return in_array($ability, $this->permissions((string) $this->role()), true);
    }

    /** @return list<string> */
    public function permissions(string $role): array
    {
        return $this->cache[$role] ??= $this->resolve($role);
    }

    /** @return list<string> */
    private function resolve(string $role): array
    {
        $model = Role::query()->where('name', $role)->where('guard_name', 'web')->first();
        $permissions = $model?->permissions()->pluck('name')->all() ?? [];

        $scope = match ($role) {
            'client-admin' => AccessGrantScope::Client,
            'client-installation-admin' => AccessGrantScope::Installation,
            default => null,
        };

        if ($scope !== null) {
            foreach (GrantableModules::defaultScopedManageGrants($scope, 1) as $grant) {
                $permissions = array_merge($permissions, GrantableModules::permissionsFor($grant->scope, $grant->module, AccessGrantLevel::Manage));
            }
        }

        return array_values(array_unique($permissions));
    }
}
