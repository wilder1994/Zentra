<?php

declare(strict_types=1);

namespace Tests\Unit\Observatory;

use App\Models\RiskModelParameter;
use App\Support\Observatory\RiskMath;
use PHPUnit\Framework\TestCase;

final class RiskMathTest extends TestCase
{
    public function test_santa_librada_matches_the_sheet(): void
    {
        $parameters = new RiskModelParameter([
            'alpha_percent' => 55,
            'gamma_percent' => 15,
            'half_life_days' => 23,
            'report_points_for_full' => 40,
            'threshold_low' => 0,
            'threshold_medium' => 30,
            'threshold_high' => 60,
            'threshold_critical' => 80,
        ]);

        $installation = RiskMath::installationScore(16.55, 40);
        $final = RiskMath::integrated($parameters, 100.0, $installation);

        $this->assertSame(41.4, $installation);
        $this->assertSame(79.8, $final);
        $this->assertSame('ALTO', RiskMath::band($final, $parameters));
        $this->assertSame(0.55, $parameters->alpha());
        $this->assertSame(0.45, $parameters->beta());
    }

    public function test_decay_halves_the_weight_at_the_configured_day(): void
    {
        $lambda = RiskMath::lambda(23);
        $factor = RiskMath::decay($lambda, 23);

        $this->assertEqualsWithDelta(0.5, $factor, 0.0001);
        $this->assertSame(10.0, RiskMath::weight(10, 10));
        $this->assertSame(80.0, RiskMath::score(10, 8));
    }
}
