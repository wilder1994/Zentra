<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Domain\User\AccessGrantData;
use App\Enums\AccessGrantLevel;
use App\Enums\AccessGrantScope;
use App\Models\SecurityCompany;
use App\Support\Auth\GrantableModules;

final class ParseAccessGrants
{
    /**
     * @param  array<string, mixed>  $raw
     * @return list<AccessGrantData>
     */
    public function fromInput(array $raw, int $companyId): array
    {
        $company = SecurityCompany::query()->find($companyId);
        $grants = [];

        foreach (['company', 'client', 'installation'] as $scopeKey) {
            $scope = AccessGrantScope::from($scopeKey);
            $bucket = $raw[$scopeKey] ?? [];
            if (! is_array($bucket)) {
                continue;
            }

            $allowedKeys = array_keys(GrantableModules::forEntitledCompany($company, $scope));

            if ($scope === AccessGrantScope::Company) {
                foreach ($allowedKeys as $module) {
                    $level = $this->levelOf($bucket[$module] ?? null);
                    if ($level === null) {
                        continue;
                    }
                    $grants[] = new AccessGrantData($scope, $companyId, $module, $level);
                }

                continue;
            }

            foreach ($bucket as $scopeId => $modules) {
                if (! is_array($modules)) {
                    continue;
                }
                $id = (int) $scopeId;
                if ($id <= 0) {
                    continue;
                }
                if (isset($modules['census']) && is_string($modules['census'])) {
                    foreach (GrantableModules::censusAliasModules() as $alias) {
                        $modules[$alias] = $modules[$alias] ?? $modules['census'];
                    }
                }
                foreach ($allowedKeys as $module) {
                    $level = $this->levelOf($modules[$module] ?? null);
                    if ($level === null) {
                        continue;
                    }
                    $grants[] = new AccessGrantData($scope, $id, $module, $level);
                }
            }
        }

        return $grants;
    }

    private function levelOf(mixed $value): ?AccessGrantLevel
    {
        if (! is_string($value) || $value === '' || $value === 'none') {
            return null;
        }

        return AccessGrantLevel::tryFrom($value);
    }
}
