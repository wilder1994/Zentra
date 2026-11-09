<?php

declare(strict_types=1);

namespace Tests\Unit\Company;

use App\Models\SupervisorShift;
use App\Services\Company\ResolveSupervisorShiftTraveledKm;
use PHPUnit\Framework\TestCase;

final class ResolveSupervisorShiftTraveledKmTest extends TestCase
{
    private ResolveSupervisorShiftTraveledKm $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ResolveSupervisorShiftTraveledKm;
    }

    public function test_uses_stored_when_plausible(): void
    {
        $shift = new SupervisorShift([
            'km_traveled' => 42,
            'km_start' => 1000,
            'km_end' => 1042,
        ]);

        $this->assertSame(42, $this->resolver->execute($shift));
    }

    public function test_prefers_odometer_delta_when_stored_is_absolute_odometer(): void
    {
        $shift = new SupervisorShift([
            'km_traveled' => 992609,
            'km_start' => 992500,
            'km_end' => 992609,
        ]);

        $this->assertSame(109, $this->resolver->execute($shift));
    }

    public function test_rejects_absurd_values_without_usable_odometer(): void
    {
        $shift = new SupervisorShift([
            'km_traveled' => 782601038,
            'km_start' => null,
            'km_end' => null,
        ]);

        $this->assertSame(0, $this->resolver->execute($shift));
    }

    public function test_uses_odometer_when_stored_is_zero(): void
    {
        $shift = new SupervisorShift([
            'km_traveled' => 0,
            'km_start' => 12000,
            'km_end' => 12055,
        ]);

        $this->assertSame(55, $this->resolver->execute($shift));
    }
}
