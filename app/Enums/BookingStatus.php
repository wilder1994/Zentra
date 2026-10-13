<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por aprobar',
            self::Confirmed => 'Confirmada',
            self::CheckedIn => 'En uso',
            self::Completed => 'Finalizada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-500/15 text-amber-300 ring-amber-500/40',
            self::Confirmed => 'bg-sky-500/15 text-sky-300 ring-sky-500/40',
            self::CheckedIn => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
            self::Rejected, self::Cancelled => 'bg-red-500/10 text-red-300 ring-red-500/30',
            self::Completed => 'bg-slate-700/40 text-slate-300 ring-slate-600',
        };
    }

    /** Estados que ocupan la zona. */
    public static function holding(): array
    {
        return [self::Confirmed->value, self::CheckedIn->value];
    }

    /** Estados que siguen abiertos (se pueden cancelar). */
    public static function open(): array
    {
        return [self::Pending->value, self::Confirmed->value];
    }
}
