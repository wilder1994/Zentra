<?php

declare(strict_types=1);

namespace App\Enums;

enum MetaConversationState: string
{
    case Role = 'role';
    case Age = 'age';
    case Consent = 'consent';
    case School = 'school';
    case Type = 'type';
    case Description = 'description';
    case Media = 'media';
    case Anonymous = 'anonymous';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Done, self::Cancelled], true);
    }
}
