<?php

declare(strict_types=1);

namespace App\Support\Personnel;

use App\Enums\DocumentFolder;
use App\Models\PersonnelFolder;

final class DefaultPersonnelCatalog
{
    /**
     * @return list<array{slug: string, name: string, behavior: string, sort: int, types: list<array{slug: string, name: string, requirement: string, repeatable: bool, sort: int}>}>
     */
    public static function tree(): array
    {
        $rows = [];
        $order = 0;

        foreach (DocumentFolder::cases() as $folder) {
            $types = [];
            $typeOrder = 0;
            foreach (IndexedFolder::types($folder) as $type) {
                $types[] = [
                    'slug' => $type->value,
                    'name' => $type->label(),
                    'requirement' => $type->requirement()->value,
                    'repeatable' => method_exists($type, 'isRepeatable') && $type->isRepeatable(),
                    'sort' => $typeOrder,
                ];
                $typeOrder++;
            }

            $rows[] = [
                'slug' => $folder->value,
                'name' => $folder->label(),
                'behavior' => match ($folder) {
                    DocumentFolder::Cursos => PersonnelFolder::COURSE,
                    DocumentFolder::Otros => PersonnelFolder::OTHER,
                    DocumentFolder::Parafiscales => PersonnelFolder::PARAFISCAL,
                    default => PersonnelFolder::STANDARD,
                },
                'sort' => $order,
                'types' => $types,
            ];
            $order++;
        }

        return $rows;
    }
}
