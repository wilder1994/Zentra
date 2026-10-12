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
            self::Bronce => 1_000,
            self::Plata => 2_000,
            self::Oro => 3_000,
            self::Platino => 5_000,
        };
    }

    public function postsPerInstallation(): int
    {
        return match ($this) {
            self::Bronce => 2,
            self::Plata => 5,
            self::Oro => 7,
            self::Platino => 10,
        };
    }

    public function employeesPerPost(): int
    {
        return match ($this) {
            self::Bronce => 3,
            self::Plata => 5,
            self::Oro => 7,
            self::Platino => 10,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Bronce => 1,
            self::Plata => 2,
            self::Oro => 3,
            self::Platino => 4,
        };
    }

    public static function highest(?self ...$metals): ?self
    {
        $best = null;
        foreach ($metals as $metal) {
            if ($metal !== null && ($best === null || $metal->rank() > $best->rank())) {
                $best = $metal;
            }
        }

        return $best;
    }

    public function rangeLabel(): string
    {
        return $this->rangeMin().'–'.$this->packSize().' instalaciones';
    }

    /** Indexación: el plan sale de los empleados (Bronce 1–1.000, Plata 1.001–2.000…). */
    public function employeeRangeMin(): int
    {
        return match ($this) {
            self::Bronce => 1,
            self::Plata => self::Bronce->employeeCap() + 1,
            self::Oro => self::Plata->employeeCap() + 1,
            self::Platino => self::Oro->employeeCap() + 1,
        };
    }

    public function rangeMinFor(CommercialProduct $product): int
    {
        return $product->countsEmployees() ? $this->employeeRangeMin() : $this->rangeMin();
    }

    /** Tope del rango; Platino no tiene tope y llega al máximo del producto. */
    public function rangeMaxFor(CommercialProduct $product): int
    {
        if ($this === self::Platino) {
            return $product->maxQuantity();
        }

        return $product->countsEmployees() ? $this->employeeCap() : $this->packSize();
    }

    public function rangeLabelFor(CommercialProduct $product): string
    {
        $min = number_format($this->rangeMinFor($product), 0, ',', '.');
        $range = $this === self::Platino
            ? $min.' o más'
            : $min.'–'.number_format($this->rangeMaxFor($product), 0, ',', '.');

        return $range.' '.$product->unitLabel();
    }

    public static function forSeats(int $seats): ?self
    {
        return $seats > 0 ? self::fromSeats($seats) : null;
    }

    public static function fromEmployees(int $employees): self
    {
        foreach ([self::Bronce, self::Plata, self::Oro] as $metal) {
            if ($employees <= $metal->employeeCap()) {
                return $metal;
            }
        }

        return self::Platino;
    }

    public static function forProduct(CommercialProduct $product, int $quantity): ?self
    {
        if ($quantity < 1) {
            return null;
        }

        return $product->countsEmployees() ? self::fromEmployees($quantity) : self::fromSeats($quantity);
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
