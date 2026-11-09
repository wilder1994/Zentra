<?php

declare(strict_types=1);

namespace App\Support\Client;

use App\Models\Client;

final class ClientPanelModules
{
    public const VEHICLES = 'vehicles';

    public const PETS = 'pets';

    public const AUTHORIZATIONS = 'authorizations';

    public const BOOKINGS = 'bookings';

    public const DOORS = 'doors';

    public const OBSERVATORY = 'observatory';

    /** @return list<string> */
    public static function optional(): array
    {
        return [
            self::VEHICLES,
            self::PETS,
            self::AUTHORIZATIONS,
            self::BOOKINGS,
            self::DOORS,
            self::OBSERVATORY,
        ];
    }

    /** @return array<string, string> */
    public static function optionalLabels(): array
    {
        return [
            self::VEHICLES => 'Vehículos',
            self::PETS => 'Mascotas',
            self::AUTHORIZATIONS => 'Autorizaciones',
            self::BOOKINGS => 'Reservas',
            self::DOORS => 'Puertas',
            self::OBSERVATORY => 'Observatorio',
        ];
    }

    /**
     * Módulos opcionales del panel cliente según líneas activas (no el plan de empresa a solas).
     *
     * @return array<string, string>
     */
    public static function availableLabelsFor(Client $client): array
    {
        $labels = self::optionalLabels();
        $allowed = [];

        if ($client->has_access) {
            foreach ([self::VEHICLES, self::PETS, self::AUTHORIZATIONS, self::BOOKINGS, self::DOORS] as $key) {
                $allowed[$key] = $labels[$key];
            }
        }

        if ($client->has_observatory) {
            $allowed[self::OBSERVATORY] = $labels[self::OBSERVATORY];
        }

        return $allowed;
    }

    /** @return array<string, string> */
    public static function optionalHints(): array
    {
        return [
            self::VEHICLES => 'Censo de vehículos por nodo.',
            self::PETS => 'Censo de mascotas por nodo.',
            self::AUTHORIZATIONS => 'Autorizaciones del censo.',
            self::BOOKINGS => 'Reservas de zonas comunes con aprobación.',
            self::DOORS => 'Portería. Solo si el cliente ya tiene puertas creadas.',
            self::OBSERVATORY => 'Tablero y reportes escolares. Si no está chuleado, el cliente no lo ve.',
        ];
    }

    /** @return array<string, bool> */
    public static function defaultOptional(): array
    {
        return [
            self::VEHICLES => true,
            self::PETS => true,
            self::AUTHORIZATIONS => true,
            self::BOOKINGS => true,
            self::DOORS => true,
            self::OBSERVATORY => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, bool>
     */
    public static function normalized(?array $stored): array
    {
        $defaults = self::defaultOptional();
        if ($stored === null) {
            return $defaults;
        }

        foreach ($defaults as $key => $default) {
            $defaults[$key] = array_key_exists($key, $stored)
                ? filter_var($stored[$key], FILTER_VALIDATE_BOOLEAN)
                : $default;
        }

        return $defaults;
    }
}
