<?php

declare(strict_types=1);

namespace App\Enums;

enum CommercialProduct: string
{
    case Access = 'access';
    case Supervision = 'supervision';
    case Indexing = 'indexing';
    case Observatory = 'observatory';

    /** Tope de empleados que se pueden contratar en Indexación. */
    public const MAX_EMPLOYEES = 100_000;

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Accesos',
            self::Supervision => 'Supervisión',
            self::Indexing => 'Indexación',
            self::Observatory => 'Observatorio',
        };
    }

    /** Indexación se cobra por empleado. */
    public function countsEmployees(): bool
    {
        return $this === self::Indexing;
    }

    /** Supervisión se cobra por puesto de servicio. */
    public function countsPosts(): bool
    {
        return $this === self::Supervision;
    }

    public function unitLabel(int $quantity = 2): string
    {
        if ($this->countsEmployees()) {
            return $quantity === 1 ? 'empleado' : 'empleados';
        }

        if ($this->countsPosts()) {
            return $quantity === 1 ? 'puesto' : 'puestos';
        }

        return $quantity === 1 ? 'instalación' : 'instalaciones';
    }

    public function maxQuantity(): int
    {
        return $this->countsEmployees() ? self::MAX_EMPLOYEES : CompanyPackageSku::MAX_SEATS;
    }
}
