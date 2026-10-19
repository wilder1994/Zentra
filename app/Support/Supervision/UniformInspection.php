<?php

declare(strict_types=1);

namespace App\Support\Supervision;

/**
 * Presentación personal: por prenda Novedad Sí / No o No aplica, y fotos de evidencia de la novedad.
 * Los registros de la primera versión (Bueno / Regular / Malo) se leen como No / Sí / Sí.
 */
final class UniformInspection
{
    /** @var list<string> */
    public const STATUSES = ['yes', 'no', 'na'];

    /** @var array<string, string> */
    private const LEGACY = ['good' => 'no', 'regular' => 'yes', 'bad' => 'yes'];

    /** @var list<string> */
    public const SLOTS = ['uniform_1', 'uniform_2', 'uniform_3'];

    public static function normalize(string $status): string
    {
        return self::LEGACY[$status] ?? $status;
    }

    public static function hasNovelty(string $status): bool
    {
        return self::normalize($status) === 'yes';
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return [
            ['value' => 'yes', 'label' => 'Sí'],
            ['value' => 'no', 'label' => 'No'],
            ['value' => 'na', 'label' => 'No aplica'],
        ];
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            'yes' => 'Con novedad',
            'no' => 'Sin novedad',
            'na' => 'No aplica',
            default => '—',
        };
    }

    /** @return list<array{key: string, label: string}> */
    public static function photoSlots(): array
    {
        return [
            ['key' => 'uniform_1', 'label' => 'Foto 1'],
            ['key' => 'uniform_2', 'label' => 'Foto 2'],
            ['key' => 'uniform_3', 'label' => 'Foto 3'],
        ];
    }
}
