<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\ObservatoryReporterMemory;

/** Política, rol, edad y nombre de contacto por teléfono, para no volver a preguntarlos. */
final class RememberObservatoryReporterService
{
    public const ADULT_RENEWAL_MONTHS = 12;

    public const DECLINE_NOTICE = 'En el próximo reporte volveremos a preguntar quién eres, si eres mayor de edad, tu número y tu nombre.';

    public function acceptedPrivacy(int $clientId, string $phone): bool
    {
        $phone = $this->phone($phone);
        if ($phone === '') {
            return false;
        }

        return ObservatoryReporterMemory::query()
            ->where('client_id', $clientId)
            ->where('phone', $phone)
            ->whereNotNull('authorization_accepted_at')
            ->exists();
    }

    public function acceptPrivacy(int $clientId, string $phone): void
    {
        $phone = $this->phone($phone);
        if ($phone === '') {
            return;
        }

        $row = ObservatoryReporterMemory::query()->firstOrNew([
            'client_id' => $clientId,
            'phone' => $phone,
        ]);
        if (! $row->exists) {
            $row->role = '';
            $row->remember = false;
        }
        if ($row->authorization_accepted_at === null) {
            $row->authorization_accepted_at = now();
        }
        $row->save();
    }

    public function recall(int $clientId, string $phone, ?string $role = null): ?ObservatoryReporterMemory
    {
        $phone = $this->phone($phone);
        if ($phone === '') {
            return null;
        }

        $row = ObservatoryReporterMemory::query()
            ->where('client_id', $clientId)
            ->where('phone', $phone)
            ->when($role !== null && $role !== '', fn ($query) => $query->where('role', $role))
            ->where('role', '!=', '')
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

    public function remember(int $clientId, string $phone, string $role, bool $isAdult, ?string $contactName = null): ?ObservatoryReporterMemory
    {
        $phone = $this->phone($phone);
        if ($phone === '' || $role === '') {
            return null;
        }

        $accepted = now();
        $current = ObservatoryReporterMemory::query()
            ->where('client_id', $clientId)
            ->where('phone', $phone)
            ->first();
        $name = trim((string) $contactName);

        return ObservatoryReporterMemory::query()->updateOrCreate(
            ['client_id' => $clientId, 'phone' => $phone],
            [
                'contact_name' => $name !== '' ? mb_substr($name, 0, 120) : $current?->contact_name,
                'role' => $role,
                'is_adult' => $isAdult,
                'remember' => true,
                'authorization_accepted_at' => $current?->authorization_accepted_at ?? $accepted,
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
