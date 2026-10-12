<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Festivos nacionales de Colombia (Ley 51 de 1983): fijos, trasladados al lunes y móviles por Pascua.
 */
final class ColombiaHolidays
{
    /** @var array<int, array<string, string>> */
    private static array $cache = [];

    public static function isHoliday(CarbonInterface $date): bool
    {
        return isset(self::forYear((int) $date->year)[$date->format('Y-m-d')]);
    }

    public static function name(CarbonInterface $date): ?string
    {
        return self::forYear((int) $date->year)[$date->format('Y-m-d')] ?? null;
    }

    /** @return array<string, string> fecha Y-m-d => nombre */
    public static function forYear(int $year): array
    {
        if (isset(self::$cache[$year])) {
            return self::$cache[$year];
        }

        $days = [];
        $monday = function (int $m, int $d, string $name) use (&$days, $year): void {
            $date = CarbonImmutable::create($year, $m, $d);
            if (! $date->isMonday()) {
                $date = $date->next(CarbonInterface::MONDAY);
            }
            $days[$date->format('Y-m-d')] = $name;
        };

        $days[CarbonImmutable::create($year, 1, 1)->format('Y-m-d')] = 'Año Nuevo';
        $days[CarbonImmutable::create($year, 5, 1)->format('Y-m-d')] = 'Día del Trabajo';
        $days[CarbonImmutable::create($year, 7, 20)->format('Y-m-d')] = 'Independencia';
        $days[CarbonImmutable::create($year, 8, 7)->format('Y-m-d')] = 'Batalla de Boyacá';
        $days[CarbonImmutable::create($year, 12, 8)->format('Y-m-d')] = 'Inmaculada Concepción';
        $days[CarbonImmutable::create($year, 12, 25)->format('Y-m-d')] = 'Navidad';

        $monday(1, 6, 'Reyes Magos');
        $monday(3, 19, 'San José');
        $monday(6, 29, 'San Pedro y San Pablo');
        $monday(8, 15, 'Asunción de la Virgen');
        $monday(10, 12, 'Día de la Raza');
        $monday(11, 1, 'Todos los Santos');
        $monday(11, 11, 'Independencia de Cartagena');

        $easter = self::easterSunday($year);
        $days[$easter->subDays(3)->format('Y-m-d')] = 'Jueves Santo';
        $days[$easter->subDays(2)->format('Y-m-d')] = 'Viernes Santo';
        $days[$easter->addDays(43)->format('Y-m-d')] = 'Ascensión del Señor';
        $days[$easter->addDays(64)->format('Y-m-d')] = 'Corpus Christi';
        $days[$easter->addDays(71)->format('Y-m-d')] = 'Sagrado Corazón';

        ksort($days);

        return self::$cache[$year] = $days;
    }

    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
