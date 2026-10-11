<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceChangeStatus: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Vencida = 'vencida';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::Vencida => 'Vencida',
        };
    }
}
