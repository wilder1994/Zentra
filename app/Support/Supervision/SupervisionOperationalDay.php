<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Día operativo de revistas: de 06:00 a 06:00 del día siguiente (hora de la app).
 * La fecha del día operativo es la de su inicio.
 */
final class SupervisionOperationalDay
{
    public const START_HOUR = 6;

    public static function dateFor(?CarbonInterface $at = null): CarbonImmutable
    {
        $local = CarbonImmutable::instance($at ?? now())->timezone((string) config('app.timezone'));
        $date = $local->startOfDay();

        return $local->hour < self::START_HOUR ? $date->subDay() : $date;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} inicio inclusivo, fin exclusivo */
    public static function window(CarbonInterface $date): array
    {
        $start = CarbonImmutable::parse($date->format('Y-m-d'), (string) config('app.timezone'))
            ->setTime(self::START_HOUR, 0);

        return [$start, $start->addDay()];
    }

    /** @return list<CarbonImmutable> */
    public static function datesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $tz = (string) config('app.timezone');
        $cursor = CarbonImmutable::parse($from->format('Y-m-d'), $tz);
        $last = CarbonImmutable::parse($to->format('Y-m-d'), $tz);
        $dates = [];
        while ($cursor->lte($last)) {
            $dates[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}
