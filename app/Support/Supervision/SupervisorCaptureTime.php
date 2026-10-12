<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use App\Models\SupervisorShift;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Hora en que el celular capturó un registro (revista, módulo, GPS). Sin red se sube después:
 * la hora de llegada no sirve para el día operativo ni para el recorrido.
 */
final class SupervisorCaptureTime
{
    /** Desfase tolerado entre el reloj del celular y el servidor. */
    public const SKEW_SECONDS = 120;

    /** Lo capturado sin red se acepta en un turno cerrado si llega dentro de este plazo. */
    public const LATE_SYNC_HOURS = 72;

    public static function parse(mixed $raw): ?CarbonImmutable
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function fitsClosed(SupervisorShift $shift, ?CarbonImmutable $at, ?CarbonImmutable $now = null): bool
    {
        if ($shift->isOpen() || $at === null || $shift->ended_at === null || $shift->started_at === null) {
            return false;
        }

        $now ??= CarbonImmutable::now();
        $ended = CarbonImmutable::parse($shift->ended_at);
        $started = CarbonImmutable::parse($shift->started_at);

        return $at->lte($ended->addSeconds(self::SKEW_SECONDS))
            && $at->gte($started->subSeconds(self::SKEW_SECONDS))
            && $ended->gte($now->subHours(self::LATE_SYNC_HOURS));
    }

    public static function assertAccepts(SupervisorShift $shift, ?CarbonImmutable $at): void
    {
        if ($shift->isOpen() || self::fitsClosed($shift, $at)) {
            return;
        }

        throw ValidationException::withMessages([
            'shift' => 'El turno está cerrado.',
        ]);
    }

    /** Hora a guardar: la del celular, acotada al turno y nunca en el futuro. */
    public static function resolve(SupervisorShift $shift, ?CarbonImmutable $at, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        if ($at === null || $at->gt($now)) {
            return $now;
        }

        if ($shift->started_at !== null) {
            $started = CarbonImmutable::parse($shift->started_at);
            if ($at->lt($started)) {
                return $started;
            }
        }

        if (! $shift->isOpen() && $shift->ended_at !== null) {
            $ended = CarbonImmutable::parse($shift->ended_at);
            if ($at->gt($ended)) {
                return $ended;
            }
        }

        return $at;
    }
}
