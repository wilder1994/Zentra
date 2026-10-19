<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use App\Models\User;

/** Responsables de un cliente: administradores de cliente internos activos con correo corporativo. */
final class ClientResponsibles
{
    /** @return list<string> */
    public function emails(int $clientId): array
    {
        return User::query()
            ->role('client-admin')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('admin_origin')->orWhere('admin_origin', '!=', 'external'))
            ->whereNotNull('corporate_email')
            ->whereHas('clients', fn ($q) => $q->where('clients.id', $clientId))
            ->pluck('corporate_email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values()
            ->all();
    }
}
