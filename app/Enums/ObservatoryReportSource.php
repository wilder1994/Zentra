<?php

declare(strict_types=1);

namespace App\Enums;

enum ObservatoryReportSource: string
{
    case Comunidad = 'comunidad';
    case Panel = 'panel';
    case Campo = 'campo';
    case Porteria = 'porteria';
    case Api = 'api';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Comunidad => 'Comunidad',
            self::Panel => 'Panel',
            self::Campo => 'App de patrulla',
            self::Porteria => 'Portería',
            self::Api => 'Integración',
            self::Whatsapp => 'WhatsApp',
        };
    }

    /** Canales abiertos al público: solo reportan sobre colegios con perfil de comunidad. */
    public function usesCommunitySites(): bool
    {
        return in_array($this, [self::Comunidad, self::Whatsapp], true);
    }
}
