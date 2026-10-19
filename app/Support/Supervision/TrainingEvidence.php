<?php

declare(strict_types=1);

namespace App\Support\Supervision;

/** Fotos de evidencia de una capacitación: mínimo una, hasta tres. */
final class TrainingEvidence
{
    /** @var list<string> */
    public const SLOTS = ['training_1', 'training_2', 'training_3'];

    /** @return list<array{key: string, label: string}> */
    public static function photoSlots(): array
    {
        return [
            ['key' => 'training_1', 'label' => 'Foto 1'],
            ['key' => 'training_2', 'label' => 'Foto 2'],
            ['key' => 'training_3', 'label' => 'Foto 3'],
        ];
    }
}
