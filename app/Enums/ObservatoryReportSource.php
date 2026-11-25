<?php

declare(strict_types=1);

namespace App\Enums;

enum ObservatoryReportSource: string
{
    case Link = 'link';
    case Whatsapp = 'whatsapp';
    case Campo = 'campo';
    case Api = 'api';
    case Panel = 'panel';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Link público',
            self::Whatsapp => 'WhatsApp',
            self::Campo => 'App de patrulla',
            self::Api => 'API',
            self::Panel => 'Panel',
        };
    }

    /** El link público y WhatsApp solo aceptan instalaciones de comunidad. */
    public function usesCommunitySites(): bool
    {
        return $this === self::Link || $this === self::Whatsapp;
    }
}
