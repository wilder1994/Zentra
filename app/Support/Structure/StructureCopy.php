<?php

declare(strict_types=1);

namespace App\Support\Structure;

use App\Models\Client;

/** Textos de UI para la estructura del cliente (sin jerga «nodo»). */
final class StructureCopy
{
    public static function unitSingular(?Client $client): string
    {
        $name = trim((string) $client?->structureType?->name);

        return $name !== '' ? $name : 'Unidad';
    }

    public static function unitPlural(?Client $client): string
    {
        $singular = self::unitSingular($client);
        if (strcasecmp($singular, 'Unidad') === 0) {
            return 'Unidades';
        }

        return self::pluralizeSpanish($singular);
    }

    public static function addButton(?Client $client): string
    {
        return 'Agregar '.mb_strtolower(self::unitSingular($client));
    }

    public static function emptyTree(?Client $client, string $siteName): string
    {
        $plural = mb_strtolower(self::unitPlural($client));

        return "Aún no hay {$plural} en {$siteName}. Agrega la primera.";
    }

    public static function createParentRoot(string $siteName): string
    {
        return $siteName.' (esta sede)';
    }

    public static function helpIndex(): string
    {
        return 'Aquí armás la estructura interna de cada sede (torres, salones, apartamentos…). Las sedes las crea tu empresa de seguridad.';
    }

    public static function helpShow(): string
    {
        return 'Agrega y organiza las unidades de esta sede. Usa el buscador para encontrar una unidad ya creada.';
    }

    private static function pluralizeSpanish(string $word): string
    {
        $lower = mb_strtolower($word);
        if (str_ends_with($lower, 'z')) {
            return mb_substr($word, 0, -1).'ces';
        }
        if (preg_match('/[aeiouáéíóú]$/iu', $word) === 1) {
            return $word.'s';
        }

        return $word.'es';
    }
}
