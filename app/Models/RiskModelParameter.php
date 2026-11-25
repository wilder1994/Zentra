<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Observatory\RiskMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RiskModelParameter extends Model
{
    protected $fillable = [
        'security_company_id',
        'alpha_percent',
        'gamma_percent',
        'half_life_days',
        'report_points_for_full',
        'threshold_low',
        'threshold_medium',
        'threshold_high',
        'threshold_critical',
        'color_low',
        'color_medium',
        'color_high',
        'color_critical',
    ];

    protected function casts(): array
    {
        return [
            'alpha_percent' => 'integer',
            'gamma_percent' => 'integer',
            'half_life_days' => 'integer',
            'report_points_for_full' => 'integer',
            'threshold_low' => 'integer',
            'threshold_medium' => 'integer',
            'threshold_high' => 'integer',
            'threshold_critical' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(SecurityCompany::class, 'security_company_id');
    }

    public static function forCompany(int $companyId): self
    {
        return static::query()->firstOrCreate(
            ['security_company_id' => $companyId],
            [
                'alpha_percent' => 55,
                'gamma_percent' => 15,
                'half_life_days' => 23,
                'report_points_for_full' => 40,
                'threshold_low' => 0,
                'threshold_medium' => 30,
                'threshold_high' => 60,
                'threshold_critical' => 80,
                'color_low' => '#14532d',
                'color_medium' => '#854d0e',
                'color_high' => '#9a3412',
                'color_critical' => '#9f1239',
            ],
        );
    }

    public function colorFor(string $band): string
    {
        $color = match ($band) {
            'CRITICO' => $this->color_critical,
            'ALTO' => $this->color_high,
            'MEDIO' => $this->color_medium,
            default => $this->color_low,
        };

        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1
            ? $color
            : match ($band) {
                'CRITICO' => '#9f1239',
                'ALTO' => '#9a3412',
                'MEDIO' => '#854d0e',
                default => '#14532d',
            };
    }

    public function bandStyle(string $band): string
    {
        $background = $this->colorFor($band);

        return 'background:'.$background.';color:'.$this->contrast($background);
    }

    private function contrast(string $hex): string
    {
        $value = ltrim($hex, '#');
        $red = hexdec(substr($value, 0, 2));
        $green = hexdec(substr($value, 2, 2));
        $blue = hexdec(substr($value, 4, 2));
        $lightness = (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255;

        return $lightness > 0.62 ? '#1e293b' : '#f8fafc';
    }

    public function alpha(): float
    {
        return $this->alpha_percent / 100;
    }

    public function betaPercent(): int
    {
        return max(0, 100 - $this->alpha_percent);
    }

    public function beta(): float
    {
        return $this->betaPercent() / 100;
    }

    public function gamma(): float
    {
        return $this->gamma_percent / 100;
    }

    public function lambda(): float
    {
        return RiskMath::lambda($this->half_life_days);
    }

    public function bandOf(float $score): string
    {
        return RiskMath::band($score, $this);
    }
}
