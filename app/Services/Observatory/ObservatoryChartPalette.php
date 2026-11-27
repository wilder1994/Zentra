<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Enums\ObservatoryReportSource;
use App\Models\ObservatoryReportType;
use App\Models\SecurityCompany;

/** Colores elegidos con la brocha del tablero. Si no hay, una paleta diferenciada. */
final class ObservatoryChartPalette
{
    /** @var list<string> */
    public const SWATCHES = [
        '#14b8a6',
        '#3b82f6',
        '#f59e0b',
        '#a855f7',
        '#22c55e',
        '#f43f5e',
        '#eab308',
        '#818cf8',
        '#fb7185',
        '#06b6d4',
    ];

    /** @var list<string> */
    public const ORIGIN_KEYS = ['alumno', 'padre', 'vecino', 'panel', 'api', 'campo'];

    /** @return array<string, array<string, string>> */
    public function stored(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }

        $raw = SecurityCompany::query()->whereKey($companyId)->value('observatory_chart_colors');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /** @param  array<string, array<string, string>>  $stored */
    public function color(array $stored, string $chart, string $key, int $index): string
    {
        $saved = $stored[$chart][$key] ?? null;
        if (is_string($saved) && preg_match('/^#[0-9A-Fa-f]{6}$/', $saved) === 1) {
            return strtolower($saved);
        }

        return self::SWATCHES[$index % count(self::SWATCHES)];
    }

    /** @param  array<string, string>  $colors */
    public function save(int $companyId, string $chart, array $colors): void
    {
        $allowed = match ($chart) {
            'sources' => array_map(fn (ObservatoryReportSource $source): string => $source->value, ObservatoryReportSource::cases()),
            'origins' => self::ORIGIN_KEYS,
            'trend' => ObservatoryReportType::query()
                ->where('security_company_id', $companyId)
                ->pluck('slug')
                ->all(),
            default => [],
        };

        $current = $this->stored($companyId);
        $bucket = is_array($current[$chart] ?? null) ? $current[$chart] : [];
        foreach ($colors as $key => $hex) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                continue;
            }
            if (! is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) !== 1) {
                continue;
            }
            $bucket[$key] = strtolower($hex);
        }
        $current[$chart] = $bucket;

        SecurityCompany::query()->whereKey($companyId)->update([
            'observatory_chart_colors' => json_encode($current, JSON_THROW_ON_ERROR),
        ]);
    }
}
