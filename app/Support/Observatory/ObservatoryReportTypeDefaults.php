<?php

declare(strict_types=1);

namespace App\Support\Observatory;

final class ObservatoryReportTypeDefaults
{
    /** @return list<array{slug: string, name: string, impacto: int, urgencia: int, color: string, sort_order: int}> */
    public static function rows(): array
    {
        return [
            ['slug' => 'amenaza', 'name' => 'Amenaza', 'impacto' => 8, 'urgencia' => 8, 'color' => '#ef4444', 'sort_order' => 1],
            ['slug' => 'rina', 'name' => 'Riña', 'impacto' => 6, 'urgencia' => 6, 'color' => '#f59e0b', 'sort_order' => 2],
            ['slug' => 'hurto', 'name' => 'Hurto', 'impacto' => 6, 'urgencia' => 6, 'color' => '#3b82f6', 'sort_order' => 3],
            ['slug' => 'otro', 'name' => 'Otro', 'impacto' => 4, 'urgencia' => 5, 'color' => '#94a3b8', 'sort_order' => 4],
        ];
    }

    /** @return list<string> */
    public static function palette(): array
    {
        return [
            '#ef4444', '#f59e0b', '#3b82f6', '#94a3b8', '#22c55e',
            '#a855f7', '#ec4899', '#14b8a6', '#eab308', '#f97316',
        ];
    }
}
