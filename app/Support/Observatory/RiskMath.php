<?php

declare(strict_types=1);

namespace App\Support\Observatory;

use App\Models\RiskModelParameter;

/** Escala 10×10 en base 0–100 y el cruce comuna + sede. */
final class RiskMath
{
    public static function score(int $axisA, int $axisB): float
    {
        return round($axisA * $axisB, 1);
    }

    public static function weight(int $axisA, int $axisB): float
    {
        return round(($axisA * $axisB) / 10, 1);
    }

    public static function band(float $score, RiskModelParameter $parameters): string
    {
        if ($score >= $parameters->threshold_critical) {
            return 'CRITICO';
        }
        if ($score >= $parameters->threshold_high) {
            return 'ALTO';
        }
        if ($score >= $parameters->threshold_medium) {
            return 'MEDIO';
        }

        return 'BAJO';
    }

    public static function lambda(int $halfLifeDays): float
    {
        $days = max(1, $halfLifeDays);

        return log(2) / $days;
    }

    public static function decay(float $lambda, int $days): float
    {
        return exp(-$lambda * max(0, $days));
    }

    public static function installationScore(float $weightedSum, int $pointsForFull): float
    {
        $points = max(1, $pointsForFull);

        return min(100.0, round($weightedSum * (100 / $points), 1));
    }

    public static function integrated(RiskModelParameter $parameters, float $commune, float $installation): float
    {
        $alpha = $parameters->alpha();
        $beta = $parameters->beta();
        $gamma = $parameters->gamma();
        $value = ($alpha * $commune)
            + ($beta * $installation)
            + ($gamma * ($commune / 100) * ($installation / 100) * 100);

        return max(0.0, min(100.0, round($value, 1)));
    }
}
