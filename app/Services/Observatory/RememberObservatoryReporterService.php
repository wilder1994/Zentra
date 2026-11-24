<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\ObservatoryReporterMemory;

/** Edad y autorización de datos por teléfono, para no volver a preguntarlas. */
final class RememberObservatoryReporterService
{
    public const ADULT_RENEWAL_MONTHS = 12;

    public const DECLINE_NOTICE = 'En los próximos reportes se seguirá preguntando la misma información.';

    public function recall(int $clientId, string $phone, string $role): ?ObservatoryReporterMemory
    {
        $phone = $this->phone($phone);
        if ($phone === '' || $role === '') {
            return null;
        }

        $row = ObservatoryReporterMemory::query()
            ->where('client_id', $clientId)
            ->where('phone', $phone)
            ->where('role', $role)
            ->where('remember', true)
            ->whereNotNull('authorization_accepted_at')
            ->first();

        if (! $row instanceof ObservatoryReporterMemory) {
            return null;
        }

        if ($row->expires_at !== null && $row->expires_at->lte(now())) {
            return null;
        }

        if ($row->is_adult === null) {
            return null;
        }

        return $row;
    }

    public function remember(int $clientId, string $phone, string $role, bool $isAdult): ?ObservatoryReporterMemory
    {
        $phone = $this->phone($phone);
        if ($phone === '' || $role === '') {
            return null;
        }

        $accepted = now();

        return ObservatoryReporterMemory::query()->updateOrCreate(
            ['client_id' => $clientId, 'phone' => $phone],
            [
                'role' => $role,
                'is_adult' => $isAdult,
                'remember' => true,
                'authorization_accepted_at' => $accepted,
                'expires_at' => $isAdult ? $accepted->copy()->addMonths(self::ADULT_RENEWAL_MONTHS) : null,
            ],
        );
    }

    public function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '57'.$digits;
        }

        return $digits;
    }
}
