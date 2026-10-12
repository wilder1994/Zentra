<?php

declare(strict_types=1);

namespace App\Enums;

enum CommercialMetal: string
{
    case Bronce = 'bronce';
    case Plata = 'plata';
    case Oro = 'oro';
    case Platino = 'platino';

    public function label(): string
    {
        return match ($this) {
            self::Bronce => 'Bronce',
            self::Plata => 'Plata',
            self::Oro => 'Oro',
            self::Platino => 'Platino',
        };
    }

    public function packSize(): int
    {
        return match ($this) {
            self::Bronce => 5,
            self::Plata => 15,
            self::Oro => 40,
            self::Platino => 100,
        };
    }

    public function rangeMin(): int
    {
        return match ($this) {
            self::Bronce => 1,
            self::Plata => 6,
            self::Oro => 16,
            self::Platino => 41,
        };
    }

    public function employeeCap(): int
    {
        return match ($this) {
            self::Bronce => 3_000,
            self::Plata => 6_000,
            self::Oro => 9_000,
            self::Platino => 12_000,
        };
    }

    public function rangeLabel(): string
    {
        return $this->rangeMin().'–'.$this->packSize().' instalaciones';
    }

    public static function forSeats(int $seats): ?self
    {
        return $seats > 0 ? self::fromSeats($seats) : null;
    }

    public static function fromSeats(int $seats): self
    {
        return match (true) {
            $seats <= 5 => self::Bronce,
            $seats <= 15 => self::Plata,
            $seats <= 40 => self::Oro,
            default => self::Platino,
        };
    }

    /**
     * @return array{metal: ?self, extras: int}
     */
    public static function decompose(int $seats): array
    {
        if ($seats <= 0) {
            return ['metal' => null, 'extras' => 0];
        }

        foreach (array_reverse(self::cases()) as $metal) {
            if ($seats >= $metal->packSize()) {
                return ['metal' => $metal, 'extras' => $seats - $metal->packSize()];
            }
        }

        return ['metal' => null, 'extras' => $seats];
    }
}
