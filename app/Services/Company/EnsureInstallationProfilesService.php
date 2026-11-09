<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\InstallationProfile;
use App\Models\SecurityCompany;

final class EnsureInstallationProfilesService
{
    /** @return list<array{code: string, name: string, requires_dane: bool, observatory_community: bool, is_system: bool, sort_order: int}> */
    public static function defaults(): array
    {
        return [
            [
                'code' => InstallationProfile::CODE_COLEGIO,
                'name' => 'Colegio',
                'requires_dane' => true,
                'observatory_community' => true,
                'is_system' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'conjunto',
                'name' => 'Conjunto / PH',
                'requires_dane' => false,
                'observatory_community' => false,
                'is_system' => false,
                'sort_order' => 20,
            ],
            [
                'code' => 'bodega',
                'name' => 'Bodega',
                'requires_dane' => false,
                'observatory_community' => false,
                'is_system' => false,
                'sort_order' => 30,
            ],
            [
                'code' => 'oficina',
                'name' => 'Oficina',
                'requires_dane' => false,
                'observatory_community' => false,
                'is_system' => false,
                'sort_order' => 40,
            ],
            [
                'code' => 'otro',
                'name' => 'Otro',
                'requires_dane' => false,
                'observatory_community' => false,
                'is_system' => false,
                'sort_order' => 50,
            ],
        ];
    }

    public function execute(int $companyId): void
    {
        foreach (self::defaults() as $row) {
            InstallationProfile::query()->firstOrCreate(
                [
                    'security_company_id' => $companyId,
                    'code' => $row['code'],
                ],
                [
                    'name' => $row['name'],
                    'requires_dane' => $row['requires_dane'],
                    'observatory_community' => $row['observatory_community'],
                    'is_system' => $row['is_system'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }

    public function forAllCompanies(): void
    {
        SecurityCompany::query()
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int|string $id) => $this->execute((int) $id));
    }
}
