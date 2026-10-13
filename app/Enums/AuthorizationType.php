<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthorizationType: string
{
    case Visit = 'visit';
    case Move = 'move';
    case Event = 'event';
    case Interview = 'interview';
    case Work = 'work';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Visit => 'Visita',
            self::Move => 'Trasteo',
            self::Event => 'Evento',
            self::Interview => 'Entrevista',
            self::Work => 'Obra / contratista',
            self::Other => 'Otro',
        };
    }

    /** Trasteos, eventos y obras implican entrar y salir varias veces dentro de la vigencia. */
    public function defaultMultiEntry(): bool
    {
        return in_array($this, [self::Move, self::Event, self::Work], true);
    }

    public function visitorType(): string
    {
        return match ($this) {
            self::Work => 'contratista',
            default => 'persona',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
