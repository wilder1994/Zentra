<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;

/**
 * Km de un turno para reportes y cuadros de mando.
 * Descarta valores absurdos (p. ej. odómetro absoluto guardado como recorrido).
 */
final class ResolveSupervisorShiftTraveledKm
{
    /** Tope razonable de km por un solo turno de supervisión en campo. */
    public const MAX_SHIFT_KM = 1500;

    public function execute(SupervisorShift $shift): int
    {
        $stored = (int) ($shift->km_traveled ?? 0);
        $odometer = $this->odometerDelta($shift);

        if ($stored > 0 && $stored <= self::MAX_SHIFT_KM) {
            return $stored;
        }

        if ($odometer !== null && $odometer <= self::MAX_SHIFT_KM) {
            return $odometer;
        }

        return 0;
    }

    private function odometerDelta(SupervisorShift $shift): ?int
    {
        if ($shift->km_start === null || $shift->km_end === null) {
            return null;
        }

        $start = (int) $shift->km_start;
        $end = (int) $shift->km_end;
        if ($end < $start) {
            return null;
        }

        return $end - $start;
    }
}
