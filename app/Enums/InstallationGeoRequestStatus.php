<?php

declare(strict_types=1);

namespace App\Enums;

enum InstallationGeoRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Superseded => 'Reemplazada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-500/15 text-amber-300 ring-amber-500/40',
            self::Approved => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
            self::Rejected => 'bg-red-500/15 text-red-300 ring-red-500/40',
            self::Superseded => 'bg-slate-800 text-slate-400 ring-slate-700',
        };
    }
}
