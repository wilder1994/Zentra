<?php

declare(strict_types=1);

namespace App\Enums;

enum SupervisorTrainingKind: string
{
    case Monthly = 'monthly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensual',
            self::Custom => 'Personalizada',
        };
    }
}
