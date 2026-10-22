<?php

declare(strict_types=1);

namespace Tests\Unit\Supervision;

use App\Services\Company\ResolveSupervisorShiftDeadlineService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ResolveSupervisorShiftDeadlineTest extends TestCase
{
    public function test_day_shift_ends_same_calendar_day(): void
    {
        $end = $this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-09-05 06:05:00'),
            '06:00',
            '14:00',
        );

        $this->assertSame('2026-09-05 14:00:00', $end->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function test_night_shift_ends_next_morning(): void
    {
        $end = $this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-09-05 18:10:00'),
            '18:00',
            '06:00',
        );

        $this->assertSame('2026-09-06 06:00:00', $end->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function test_night_shift_after_midnight_ends_that_morning(): void
    {
        $end = $this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-09-06 01:20:00'),
            '18:00',
            '06:00',
        );

        $this->assertSame('2026-09-06 06:00:00', $end->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function test_night_shift_opened_early_ends_next_morning(): void
    {
        $end = $this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-10-12 17:30:00', (string) config('app.timezone')),
            '18:00',
            '06:00',
        );

        $this->assertSame('2026-10-13 06:00:00', $end->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function test_day_shift_opened_early_ends_that_afternoon(): void
    {
        $end = $this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-10-13 05:30:00', (string) config('app.timezone')),
            '06:00',
            '18:00',
        );

        $this->assertSame('2026-10-13 18:00:00', $end->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function test_day_shift_opened_at_night_is_outside_the_schedule(): void
    {
        $this->assertNull($this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-10-14 20:10:00', (string) config('app.timezone')),
            '06:00',
            '18:00',
        ));
        $this->assertNull($this->service()->scheduledEnd(
            CarbonImmutable::parse('2026-10-14 15:00:00', (string) config('app.timezone')),
            '18:00',
            '06:00',
        ));
    }

    private function service(): ResolveSupervisorShiftDeadlineService
    {
        return new ResolveSupervisorShiftDeadlineService;
    }
}
