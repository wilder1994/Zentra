<?php

declare(strict_types=1);

namespace App\Enums;

enum SupervisorTrainingModality: string
{
    case Virtual = 'virtual';
    case Onsite = 'onsite';

    public function label(): string
    {
        return match ($this) {
            self::Virtual => 'Virtual',
            self::Onsite => 'En puesto',
        };
    }
}
