<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\PersonnelFolder;
use App\Support\Personnel\DefaultPersonnelCatalog;
use Illuminate\Support\Facades\DB;

final class EnsurePersonnelCatalogService
{
    public function ensure(int $companyId): void
    {
        if (PersonnelFolder::query()->where('security_company_id', $companyId)->exists()) {
            return;
        }

        DB::transaction(function () use ($companyId): void {
            if (PersonnelFolder::query()->where('security_company_id', $companyId)->lockForUpdate()->exists()) {
                return;
            }

            foreach (DefaultPersonnelCatalog::tree() as $row) {
                $folder = PersonnelFolder::query()->create([
                    'security_company_id' => $companyId,
                    'slug' => $row['slug'],
                    'name' => $row['name'],
                    'behavior' => $row['behavior'],
                    'sort_order' => $row['sort'],
                    'is_active' => true,
                ]);

                foreach ($row['types'] as $type) {
                    $folder->types()->create([
                        'security_company_id' => $companyId,
                        'slug' => $type['slug'],
                        'name' => $type['name'],
                        'requirement' => $type['requirement'],
                        'is_repeatable' => $type['repeatable'],
                        'sort_order' => $type['sort'],
                        'is_active' => true,
                    ]);
                }
            }
        });
    }
}
