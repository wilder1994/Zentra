<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Enums\ColombianAreaKind;
use App\Models\Installation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CaliComunaLayer
{
    public const FUERA = 'fuera';

    /** @var list<array{code: string, name: string, bbox: array{0: float, 1: float, 2: float, 3: float}, type: string, coordinates: array<int, mixed>}>|null */
    private static ?array $polygons = null;

    public function __construct(private readonly PointInPolygon $pip) {}

    public function path(): string
    {
        return resource_path('data/cali-comunas.geojson');
    }

    public function download(): BinaryFileResponse
    {
        return response()->file($this->path(), [
            'Content-Type' => 'application/geo+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function catalog(): array
    {
        return array_map(
            static fn (array $row): array => ['code' => $row['code'], 'name' => $row['name']],
            $this->polygons(),
        );
    }

    public function label(string $code): string
    {
        if ($code === '') {
            return 'Todas';
        }
        if ($code === self::FUERA) {
            return 'Fuera de Cali';
        }
        foreach ($this->catalog() as $row) {
            if ($row['code'] === $code) {
                return $row['name'];
            }
        }

        return $code;
    }

    /**
     * Punto dentro de cada comuna. Sirve para la dona y el pin de cantidad.
     *
     * @return list<array{code: string, name: string, lat: float, lng: float}>
     */
    public function places(): array
    {
        return array_map(function (array $row): array {
            [$lng, $lat] = $this->interior($row);

            return [
                'code' => $row['code'],
                'name' => $row['name'],
                'lat' => $lat,
                'lng' => $lng,
            ];
        }, $this->polygons());
    }

    /**
     * Un punto que caiga dentro del polígono. El centro del recuadro se sale en comunas alargadas.
     *
     * @param  array{code: string, name: string, bbox: array{0: float, 1: float, 2: float, 3: float}, type: string, coordinates: array<int, mixed>}  $row
     * @return array{0: float, 1: float}
     */
    private function interior(array $row): array
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $row['bbox'];
        $ring = $this->outerRing($row);
        $candidates = [[($minLng + $maxLng) / 2, ($minLat + $maxLat) / 2]];
        if ($ring !== []) {
            $count = count($ring);
            $candidates[] = [
                array_sum(array_column($ring, 0)) / $count,
                array_sum(array_column($ring, 1)) / $count,
            ];
        }

        foreach ($candidates as [$lng, $lat]) {
            if ($this->pip->geometryContains($lat, $lng, $row['type'], $row['coordinates'])) {
                return [$lng, $lat];
            }
        }

        for ($step = 5; $step <= 15; $step += 5) {
            for ($y = 1; $y < $step; $y++) {
                for ($x = 1; $x < $step; $x++) {
                    $lng = $minLng + ($maxLng - $minLng) * $x / $step;
                    $lat = $minLat + ($maxLat - $minLat) * $y / $step;
                    if ($this->pip->geometryContains($lat, $lng, $row['type'], $row['coordinates'])) {
                        return [$lng, $lat];
                    }
                }
            }
        }

        return $candidates[0];
    }

    /**
     * @param  array{type: string, coordinates: array<int, mixed>}  $row
     * @return list<array{0: float, 1: float}>
     */
    private function outerRing(array $row): array
    {
        $coordinates = $row['coordinates'];
        if ($row['type'] === 'Polygon') {
            return is_array($coordinates[0] ?? null) ? $coordinates[0] : [];
        }
        if ($row['type'] !== 'MultiPolygon') {
            return [];
        }

        $best = [];
        foreach ($coordinates as $polygon) {
            $ring = is_array($polygon) ? ($polygon[0] ?? []) : [];
            if (is_array($ring) && count($ring) > count($best)) {
                $best = $ring;
            }
        }

        return $best;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_column($this->polygons(), 'code');
    }

    public function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'todas') {
            return '';
        }
        if ($value === self::FUERA) {
            return self::FUERA;
        }
        if (preg_match('/^\d{1,2}$/', $value) !== 1) {
            return '';
        }

        $code = str_pad($value, 2, '0', STR_PAD_LEFT);

        return in_array($code, $this->codes(), true) ? $code : '';
    }

    /**
     * @return array{code: string, name: string}|null
     */
    /**
     * @return array{commune: ?string, area_kind: string}
     */
    public function areaAttributes(mixed $commune, mixed $city, mixed $lat = null, mixed $lng = null): array
    {
        $hit = $this->locate(
            is_numeric($lat) ? (float) $lat : null,
            is_numeric($lng) ? (float) $lng : null,
        );
        if ($hit !== null) {
            return [
                'commune' => $hit['name'],
                'area_kind' => ColombianAreaKind::Comuna->value,
            ];
        }

        $name = is_string($commune) ? $commune : null;
        $town = is_string($city) ? $city : null;

        return [
            'commune' => ColombianArea::persistableValue($name, $town),
            'area_kind' => ColombianArea::classify($name, $town)->value,
        ];
    }

    public function locate(?float $lat, ?float $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        foreach ($this->polygons() as $row) {
            [$minLng, $minLat, $maxLng, $maxLat] = $row['bbox'];
            if ($lng < $minLng || $lng > $maxLng || $lat < $minLat || $lat > $maxLat) {
                continue;
            }
            if ($this->pip->geometryContains($lat, $lng, $row['type'], $row['coordinates'])) {
                return ['code' => $row['code'], 'name' => $row['name']];
            }
        }

        return null;
    }

    /**
     * @param  list<int>|null  $limitIds
     * @return list<int>
     */
    public function installationIds(string $comuna, ?int $companyId, ?int $clientId, ?array $limitIds): array
    {
        $comuna = $this->normalize($comuna);
        if ($comuna === '') {
            return $limitIds ?? [];
        }

        return Installation::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->when($companyId !== null, fn ($q) => $q->whereHas(
                'client',
                fn ($c) => $c->where('security_company_id', $companyId),
            ))
            ->when($limitIds !== null, fn ($q) => $q->whereIn('id', $limitIds))
            ->get(['id', 'latitude', 'longitude'])
            ->filter(function (Installation $site) use ($comuna): bool {
                $hit = $this->locate((float) $site->latitude, (float) $site->longitude);

                return $comuna === self::FUERA ? $hit === null : ($hit['code'] ?? null) === $comuna;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $limitIds
     * @return list<int>|null
     */
    public function scopeInstallationIds(string $comuna, ?int $companyId, ?int $clientId, ?array $limitIds): ?array
    {
        $comuna = $this->normalize($comuna);
        if ($comuna === '') {
            return $limitIds;
        }

        return $this->installationIds($comuna, $companyId, $clientId, $limitIds);
    }

    /**
     * @return list<array{code: string, name: string, bbox: array{0: float, 1: float, 2: float, 3: float}, type: string, coordinates: array<int, mixed>}>
     */
    private function polygons(): array
    {
        if (self::$polygons !== null) {
            return self::$polygons;
        }

        $raw = json_decode((string) file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];
        foreach ($raw['features'] ?? [] as $feature) {
            $code = (string) ($feature['properties']['code'] ?? '');
            $geom = $feature['geometry'] ?? null;
            if ($code === '' || ! is_array($geom)) {
                continue;
            }
            $coordinates = $geom['coordinates'] ?? [];
            $rows[] = [
                'code' => $code,
                'name' => (string) ($feature['properties']['name'] ?? ('Comuna '.$code)),
                'bbox' => $this->bbox($coordinates),
                'type' => (string) ($geom['type'] ?? 'MultiPolygon'),
                'coordinates' => $coordinates,
            ];
        }

        return self::$polygons = $rows;
    }

    /**
     * @param  array<int, mixed>  $coordinates
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function bbox(array $coordinates): array
    {
        $minLng = 180.0;
        $minLat = 90.0;
        $maxLng = -180.0;
        $maxLat = -90.0;
        $walk = static function ($node) use (&$walk, &$minLng, &$minLat, &$maxLng, &$maxLat): void {
            if (! is_array($node) || $node === []) {
                return;
            }
            if (isset($node[0], $node[1]) && is_numeric($node[0]) && is_numeric($node[1])) {
                $lng = (float) $node[0];
                $lat = (float) $node[1];
                $minLng = min($minLng, $lng);
                $maxLng = max($maxLng, $lng);
                $minLat = min($minLat, $lat);
                $maxLat = max($maxLat, $lat);

                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($coordinates);

        return [$minLng, $minLat, $maxLng, $maxLat];
    }
}
