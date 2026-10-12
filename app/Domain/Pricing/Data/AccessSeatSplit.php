<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Data;

use App\Enums\CommercialMetal;
use App\Enums\CompanyPackageSku;
use App\Enums\PackageModality;
use InvalidArgumentException;

final readonly class AccessSeatSplit
{
    public function __construct(
        public int $manual,
        public int $hardware,
    ) {
        if ($this->manual < 0 || $this->hardware < 0) {
            throw new InvalidArgumentException('Las instalaciones no pueden ser negativas.');
        }

        if ($this->size() > CompanyPackageSku::MAX_SEATS) {
            throw new InvalidArgumentException('El cupo de Accesos supera el máximo permitido.');
        }
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    public function size(): int
    {
        return $this->manual + $this->hardware;
    }

    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    public function metal(): ?CommercialMetal
    {
        return CommercialMetal::forSeats($this->size());
    }

    public function modality(): PackageModality
    {
        if ($this->hardware === 0) {
            return PackageModality::Manual;
        }

        if ($this->manual === 0) {
            return PackageModality::Hardware;
        }

        return PackageModality::Mixed;
    }

    public function sku(): CompanyPackageSku
    {
        return CompanyPackageSku::fromParts($this->size(), $this->modality());
    }

    public function label(): string
    {
        $size = $this->size();
        if ($size === 0) {
            return 'Sin Accesos';
        }

        $clients = $size === 1 ? '1 instalación' : "{$size} instalaciones";
        $metal = $this->metal()?->label();

        if ($this->modality() !== PackageModality::Mixed) {
            return "{$metal} · {$clients} · {$this->modality()->label()}";
        }

        return "{$metal} · {$clients} · {$this->manual} sin hardware + {$this->hardware} con hardware";
    }

    public static function fromSku(CompanyPackageSku $sku): self
    {
        return $sku->modality() === PackageModality::Hardware
            ? new self(0, $sku->size())
            : new self($sku->size(), 0);
    }

    /** Con asientos explícitos manda la cantidad; el SKU solo aporta el valor por defecto. */
    public static function resolve(CompanyPackageSku $sku, ?int $manual, ?int $hardware): self
    {
        if ($manual === null && $hardware === null) {
            return self::fromSku($sku);
        }

        return new self($manual ?? 0, $hardware ?? 0);
    }
}
