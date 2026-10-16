<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Blocklist;
use App\Models\Resident;
use App\Models\Vehicle;
use App\Models\Visitor;

class BlocklistGuard
{
    /**
     * @return Blocklist|null Entry activa de bloqueo para una persona.
     */
    public function checkPerson(?Visitor $visitor = null, ?Resident $resident = null, ?string $documentNumber = null): ?Blocklist
    {
        $documentNumber = $this->normalizeDocument(
            $documentNumber
                ?? $visitor?->document_number
                ?? $resident?->document_number
        );

        if ($documentNumber === null) {
            return null;
        }

        $query = Blocklist::query()
            ->active()
            ->persons()
            ->with('blockable');

        $byDocument = (clone $query)
            ->whereNotNull('document_number')
            ->where('document_number', $documentNumber)
            ->first();

        if ($byDocument !== null) {
            return $byDocument;
        }

        if ($visitor !== null) {
            $byMorph = (clone $query)
                ->where('blockable_type', Blocklist::TYPE_VISITOR)
                ->where('blockable_id', (int) $visitor->id)
                ->first();
            if ($byMorph !== null) {
                return $byMorph;
            }
        }

        if ($resident !== null) {
            $byMorph = (clone $query)
                ->where('blockable_type', Blocklist::TYPE_RESIDENT)
                ->where('blockable_id', (int) $resident->id)
                ->first();
            if ($byMorph !== null) {
                return $byMorph;
            }
        }

        return $query
            ->where(function ($inner) use ($documentNumber): void {
                $inner->whereNull('document_number')
                    ->orWhere('document_number', '');
            })
            ->get()
            ->first(function (Blocklist $entry) use ($documentNumber): bool {
                $blocked = $entry->blockable;

                return $blocked !== null
                    && isset($blocked->document_number)
                    && $this->normalizeDocument((string) $blocked->document_number) === $documentNumber;
            });
    }

    /**
     * @return Blocklist|null Entry activa de bloqueo para un vehículo.
     */
    public function checkVehicle(?Vehicle $vehicle = null, ?string $plate = null): ?Blocklist
    {
        $plate = $this->normalizePlate($plate ?? $vehicle?->plate);

        if ($plate === null) {
            return null;
        }

        $query = Blocklist::query()
            ->active()
            ->vehicles()
            ->with('blockable');

        $byPlate = (clone $query)
            ->whereNotNull('plate')
            ->where('plate', $plate)
            ->first();

        if ($byPlate !== null) {
            return $byPlate;
        }

        if ($vehicle !== null) {
            $byMorph = (clone $query)
                ->where('blockable_type', Blocklist::TYPE_VEHICLE)
                ->where('blockable_id', (int) $vehicle->id)
                ->first();
            if ($byMorph !== null) {
                return $byMorph;
            }
        }

        return $query
            ->where(function ($inner): void {
                $inner->whereNull('plate')->orWhere('plate', '');
            })
            ->get()
            ->first(function (Blocklist $entry) use ($plate): bool {
                $blocked = $entry->blockable;

                return $blocked !== null
                    && $this->normalizePlate((string) ($blocked->plate ?? '')) === $plate;
            });
    }

    public function normalizeDocument(?string $value): ?string
    {
        $value = preg_replace('/\s+/', '', (string) $value) ?? '';
        $value = strtoupper($value);

        return $value !== '' ? $value : null;
    }

    public function normalizePlate(?string $value): ?string
    {
        $value = preg_replace('/[\s\-]+/', '', (string) $value) ?? '';
        $value = strtoupper($value);

        return $value !== '' ? $value : null;
    }
}
