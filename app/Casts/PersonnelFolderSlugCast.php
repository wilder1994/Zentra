<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\DocumentFolder;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
final class PersonnelFolderSlugCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof DocumentFolder) {
            return $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
