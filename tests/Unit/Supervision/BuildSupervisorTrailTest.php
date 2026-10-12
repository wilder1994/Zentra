<?php

declare(strict_types=1);

namespace Tests\Unit\Supervision;

use App\Models\SupervisorShiftLocation;
use App\Services\Company\BuildSupervisorTrailService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class BuildSupervisorTrailTest extends TestCase
{
    public function test_stationary_pings_become_a_stop(): void
    {
        $start = CarbonImmutable::parse('2026-09-05 10:00:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start),
            $this->point(3.45162, -76.53202, $start->addMinutes(2)),
            $this->point(3.45161, -76.53201, $start->addMinutes(6)),
            $this->point(3.4600, -76.5400, $start->addMinutes(9)),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true);

        $this->assertNotEmpty($trail['path']);
        $this->assertCount(1, $trail['stops']);
        $this->assertGreaterThanOrEqual(5, $trail['stops'][0]['minutes']);
        $this->assertNotNull($trail['start']);
        $this->assertNotNull($trail['end']);
        $this->assertNull(app(BuildSupervisorTrailService::class)->execute($locations, false)['parked']);
    }

    public function test_current_parked_grows_with_clock_and_is_not_a_past_stop(): void
    {
        $now = CarbonImmutable::parse('2026-09-05 10:20:00');
        $start = CarbonImmutable::parse('2026-09-05 10:00:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start),
            $this->point(3.45161, -76.53201, $start->addMinutes(3)),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true, $now);

        $this->assertNotNull($trail['parked']);
        $this->assertTrue($trail['parked']['current']);
        $this->assertGreaterThanOrEqual(18, $trail['parked']['minutes']);
        $this->assertSame([], $trail['stops']);
        $this->assertFalse($trail['online']);
    }

    public function test_isolated_gps_spike_is_dropped_from_path(): void
    {
        $start = CarbonImmutable::parse('2026-09-14 10:00:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start),
            $this->point(3.45161, -76.53201, $start->addSeconds(15)),
            $this->point(3.4700, -76.5500, $start->addSeconds(30)),
            $this->point(3.45162, -76.53202, $start->addSeconds(45)),
            $this->point(3.45163, -76.53203, $start->addSeconds(60)),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true);

        $lats = array_column($trail['path'], 'lat');
        $this->assertNotContains(3.47, $lats);
        $this->assertLessThan(0.3, $trail['km']);
        $this->assertEqualsWithDelta(3.4516, $trail['end']['lat'], 0.001);
    }

    public function test_inaccurate_ping_is_dropped(): void
    {
        $start = CarbonImmutable::parse('2026-09-14 11:00:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start, 12.0),
            $this->point(3.4600, -76.5400, $start->addSeconds(15), 400.0),
            $this->point(3.45161, -76.53201, $start->addSeconds(30), 14.0),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true);

        $this->assertLessThan(0.3, $trail['km']);
        foreach ($trail['path'] as $pt) {
            $this->assertLessThan(3.452, $pt['lat']);
        }
    }

    public function test_real_movement_is_kept(): void
    {
        $start = CarbonImmutable::parse('2026-09-14 12:00:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start),
            $this->point(3.4534, -76.5320, $start->addSeconds(20)),
            $this->point(3.4552, -76.5320, $start->addSeconds(40)),
            $this->point(3.4570, -76.5320, $start->addSeconds(60)),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true);

        $this->assertGreaterThanOrEqual(3, count($trail['path']));
        $this->assertGreaterThan(0.4, $trail['km']);
        $this->assertEqualsWithDelta(3.4570, $trail['end']['lat'], 0.0002);
    }

    public function test_network_fixes_are_ignored_when_gps_exists(): void
    {
        $start = CarbonImmutable::parse('2026-10-11 09:00:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start, 4.0),
            $this->point(3.4530, -76.5335, $start->addSeconds(15), 150.0),
            $this->point(3.45161, -76.53201, $start->addSeconds(30), 4.0),
            $this->point(3.4502, -76.5306, $start->addSeconds(45), 150.0),
            $this->point(3.45162, -76.53202, $start->addSeconds(60), 5.0),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true);

        $this->assertSame(0.0, $trail['km']);
        $this->assertCount(1, $trail['path']);
    }

    public function test_short_boomerang_jitter_is_dropped(): void
    {
        $start = CarbonImmutable::parse('2026-10-11 09:30:00');
        $locations = new Collection([
            $this->point(3.4516, -76.5320, $start, 8.0),
            $this->point(3.4521, -76.5320, $start->addSeconds(15), 10.0),
            $this->point(3.45161, -76.53201, $start->addSeconds(30), 8.0),
            $this->point(3.4511, -76.5320, $start->addSeconds(45), 10.0),
            $this->point(3.45162, -76.53202, $start->addSeconds(60), 8.0),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true);

        $this->assertSame(0.0, $trail['km']);
    }

    public function test_parked_moto_stays_on_stop_centroid(): void
    {
        $start = CarbonImmutable::parse('2026-10-11 10:00:00');
        $locations = new Collection([
            $this->point(3.45160, -76.53200, $start, 10.0),
            $this->point(3.45172, -76.53200, $start->addMinutes(1), 10.0),
            $this->point(3.45155, -76.53200, $start->addMinutes(2), 10.0),
            $this->point(3.45168, -76.53200, $start->addMinutes(3), 10.0),
        ]);

        $trail = app(BuildSupervisorTrailService::class)->execute($locations, true, $start->addMinutes(3));

        $this->assertEqualsWithDelta(3.451637, $trail['end']['lat'], 0.00001);
        $this->assertNotNull($trail['parked']);
    }

    private function point(float $lat, float $lng, CarbonImmutable $at, ?float $accuracy = null): SupervisorShiftLocation
    {
        $location = new SupervisorShiftLocation;
        $location->latitude = $lat;
        $location->longitude = $lng;
        $location->recorded_at = $at;
        $location->accuracy = $accuracy;

        return $location;
    }
}
