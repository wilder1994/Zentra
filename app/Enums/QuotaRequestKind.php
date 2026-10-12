<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotaRequestKind: string
{
    case Employees = 'employees';
    case Posts = 'posts';
    case Assignments = 'assignments';

    public function label(): string
    {
        return match ($this) {
            self::Employees => 'Empleados adicionales',
            self::Posts => 'Puestos por instalación',
            self::Assignments => 'Empleados asignados por puesto',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
