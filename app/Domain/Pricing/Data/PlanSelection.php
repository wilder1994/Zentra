<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Data;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Enums\SupervisionPackageSku;
use App\Models\SecurityCompany;
use InvalidArgumentException;

/**
 * Cantidad contratada por producto: instalaciones (Accesos, Supervisión, Observatorio) o empleados (Indexación).
 * Cada producto se cotiza con el plan (metal) de su cantidad.
 */
final readonly class PlanSelection
{
    public function __construct(
        public AccessSeatSplit $access,
        public int $supervision = 0,
        public int $indexing = 0,
        public int $observatory = 0,
    ) {
        $limits = [
            [$this->supervision, SupervisionPackageSku::MAX_SEATS],
            [$this->indexing, CommercialProduct::MAX_EMPLOYEES],
            [$this->observatory, SupervisionPackageSku::MAX_SEATS],
        ];
        foreach ($limits as [$quantity, $max]) {
            if ($quantity < 0) {
                throw new InvalidArgumentException('La cantidad no puede ser negativa.');
            }
            if ($quantity > $max) {
                throw new InvalidArgumentException('La cantidad supera el máximo permitido.');
            }
        }
    }

    public static function of(int $manual = 0, int $hardware = 0, int $supervision = 0, int $indexing = 0, int $observatory = 0): self
    {
        return new self(new AccessSeatSplit($manual, $hardware), $supervision, $indexing, $observatory);
    }

    public static function fromCompany(SecurityCompany $company): self
    {
        return new self(
            $company->accessSeats(),
            (int) ($company->supervision_package_sku?->size() ?? 0),
            (int) ($company->indexing_seats ?? 0),
            (int) ($company->observatory_seats ?? 0),
        );
    }

    public function seats(CommercialProduct $product): int
    {
        return match ($product) {
            CommercialProduct::Access => $this->access->size(),
            CommercialProduct::Supervision => $this->supervision,
            CommercialProduct::Indexing => $this->indexing,
            CommercialProduct::Observatory => $this->observatory,
        };
    }

    public function metal(CommercialProduct $product): ?CommercialMetal
    {
        return CommercialMetal::forProduct($product, $this->seats($product));
    }

    public function isEmpty(): bool
    {
        return $this->access->isEmpty() && $this->supervision < 1 && $this->indexing < 1 && $this->observatory < 1;
    }

    public function supervisionSku(): ?SupervisionPackageSku
    {
        return $this->supervision > 0 ? SupervisionPackageSku::fromSize($this->supervision) : null;
    }

    /** Mayor cantidad entre productos por instalación: tamaño del cupo compartido. Indexación cuenta empleados. */
    public function capacity(): int
    {
        return max($this->access->size(), $this->supervision, $this->observatory);
    }

    public function sameAs(self $other): bool
    {
        return $this->access->manual === $other->access->manual
            && $this->access->hardware === $other->access->hardware
            && $this->supervision === $other->supervision
            && $this->indexing === $other->indexing
            && $this->observatory === $other->observatory;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'manual_seats' => $this->access->manual,
            'hardware_seats' => $this->access->hardware,
            'supervision_seats' => $this->supervision,
            'indexing_seats' => $this->indexing,
            'observatory_seats' => $this->observatory,
        ];
    }

    public function label(): string
    {
        $parts = [];
        foreach (CommercialProduct::cases() as $product) {
            $seats = $this->seats($product);
            if ($seats > 0) {
                $parts[] = $product->label().' '.$this->metal($product)?->label().' '.number_format($seats, 0, ',', '.')
                    .($product->countsEmployees() ? ' '.$product->unitLabel($seats) : '');
            }
        }

        return $parts === [] ? 'Sin productos' : implode(' · ', $parts);
    }
}
