<?php

declare(strict_types=1);

namespace App\Support\Geo;

use Illuminate\Database\Eloquent\Model;

/** «Dirección, ciudad» para compartir ubicaciones desde las tarjetas de los mapas. */
final class PlaceLabel
{
    public static function of(?Model $place): string
    {
        if ($place === null) {
            return '';
        }

        return collect([$place->getAttribute('address'), $place->getAttribute('city')])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->implode(', ');
    }
}
