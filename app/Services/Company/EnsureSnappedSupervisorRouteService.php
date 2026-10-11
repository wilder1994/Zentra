<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;

final class EnsureSnappedSupervisorRouteService
{
    public function __construct(
        private readonly BuildSupervisorTrailService $trail,
        private readonly SnapSupervisorTrailToRoadsService $roads,
    ) {}

    /**
     * @return array{path: list<array{lat: float, lng: float}>, snapped: bool, start: mixed, end: mixed, stops: list<array<string, mixed>>, km: float}
     */
    public function execute(SupervisorShift $shift): array
    {
        $shift->loadMissing(['locations' => fn ($q) => $q->orderBy('recorded_at')]);
        $gps = $this->trail->execute($shift->locations, $shift->status === SupervisorShiftStatus::Open);
        $path = array_map(
            fn (array $p) => ['lat' => $p['lat'], 'lng' => $p['lng']],
            $gps['path'],
        );
        $extra = [
            'start' => $gps['start'],
            'end' => $gps['end'],
            'stops' => $gps['stops'],
            'km' => (float) $gps['km'],
        ];

        if ($shift->status !== SupervisorShiftStatus::Closed || count($path) < 2) {
            return ['path' => $path, 'snapped' => false] + $extra;
        }

        $hash = sha1((string) $shift->locations->count().'|'.(string) $shift->locations->last()?->id);
        $cached = $shift->snapped_route;
        if (is_array($cached) && $cached !== [] && $shift->snapped_route_hash === $hash) {
            return ['path' => $cached, 'snapped' => true] + $extra;
        }

        $snapped = $this->roads->execute($path);
        if ($snapped === []) {
            return ['path' => $path, 'snapped' => false] + $extra;
        }

        $shift->forceFill([
            'snapped_route' => $snapped,
            'snapped_route_hash' => $hash,
        ])->save();

        return ['path' => $snapped, 'snapped' => true] + $extra;
    }
}
