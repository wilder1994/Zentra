<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imagen del recorrido del turno para la ficha (mismos puntos que el Historial).
 * Turno cerrado: se descarga una vez y queda guardada. Turno abierto: se renueva cada 5 minutos.
 */
final class BuildShiftRouteMapService
{
    private const DIR = 'supervision/route-maps';

    private const MAX_POINTS = 350;

    private const LIVE_TTL_SECONDS = 300;

    public function __construct(
        private readonly BuildSupervisorTrailService $trail,
    ) {}

    /**
     * @return array{src: ?string, km: float, stops: int, has_points: bool}
     */
    public function execute(SupervisorShift $shift): array
    {
        $shift->loadMissing(['locations' => fn ($q) => $q->orderBy('recorded_at'), 'reviews']);
        $built = $this->trail->execute($shift->locations, $shift->isOpen());
        $path = $built['path'];
        $result = ['src' => null, 'km' => (float) $built['km'], 'stops' => count($built['stops']), 'has_points' => $path !== []];

        if ($path === []) {
            return $result;
        }

        $file = self::DIR.'/shift-'.$shift->id.($shift->isOpen() ? '-live' : '').'.png';
        $disk = Storage::disk('local');
        $fresh = $disk->exists($file)
            && (! $shift->isOpen() || (time() - $disk->lastModified($file)) < self::LIVE_TTL_SECONDS);

        $failKey = 'route-map-failed:'.$shift->id;
        if (! $fresh && ! Cache::has($failKey)) {
            $bytes = $this->download($path, $built['start'], $shift->isOpen() ? null : $built['end'], $this->stopsOf($shift));
            if ($bytes === null) {
                Cache::put($failKey, true, now()->addMinutes(10));
            }
            if ($bytes !== null) {
                $disk->put($file, $bytes);
                if (! $shift->isOpen()) {
                    $disk->delete(self::DIR.'/shift-'.$shift->id.'-live.png');
                }
                $fresh = true;
            }
        }

        if ($fresh) {
            $result['src'] = 'data:image/png;base64,'.base64_encode((string) $disk->get($file));
        }

        return $result;
    }

    /** @return list<array{lat: float, lng: float}> */
    private function stopsOf(SupervisorShift $shift): array
    {
        return $shift->reviews
            ->filter(fn ($review) => $review->latitude !== null && $review->longitude !== null)
            ->sortBy('recorded_at')
            ->map(fn ($review) => ['lat' => (float) $review->latitude, 'lng' => (float) $review->longitude])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{lat: float, lng: float, at?: ?string}>  $path
     * @param  array{lat: float, lng: float}|null  $start
     * @param  array{lat: float, lng: float}|null  $end
     * @param  list<array{lat: float, lng: float}>  $stops
     */
    private function download(array $path, ?array $start, ?array $end, array $stops): ?string
    {
        $key = (string) config('google-maps.server_api_key');
        if ($key === '') {
            return null;
        }

        $query = [
            'size' => '640x320',
            'scale' => '2',
            'maptype' => 'hybrid',
            'path' => 'color:0xf59e0bff|weight:4|enc:'.$this->encode($this->sample($path)),
            'key' => $key,
        ];
        $markers = [];
        if ($start !== null) {
            $markers[] = 'color:0x22c55e|label:I|'.$this->coord($start);
        }
        if ($end !== null) {
            $markers[] = 'color:0xef4444|label:F|'.$this->coord($end);
        }
        foreach (array_slice($stops, 0, 9) as $i => $stop) {
            $markers[] = 'size:mid|color:0x6366f1|label:'.($i + 1).'|'.$this->coord($stop);
        }

        $url = 'https://maps.googleapis.com/maps/api/staticmap?'.http_build_query($query);
        foreach ($markers as $marker) {
            $url .= '&markers='.rawurlencode($marker);
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders(['Referer' => rtrim((string) config('app.url'), '/').'/'])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('Mapa de recorrido no disponible', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            Log::warning('Mapa de recorrido no disponible', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);

            return null;
        }

        return $response->body();
    }

    /** @param  array{lat: float, lng: float}  $point */
    private function coord(array $point): string
    {
        return round($point['lat'], 6).','.round($point['lng'], 6);
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $path
     * @return list<array{lat: float, lng: float}>
     */
    private function sample(array $path): array
    {
        $count = count($path);
        if ($count <= self::MAX_POINTS) {
            return $path;
        }
        $step = $count / self::MAX_POINTS;
        $out = [];
        for ($i = 0; $i < self::MAX_POINTS; $i++) {
            $out[] = $path[(int) floor($i * $step)];
        }
        $out[] = $path[$count - 1];

        return $out;
    }

    /** Polilínea codificada de Google. @param  list<array{lat: float, lng: float}>  $points */
    private function encode(array $points): string
    {
        $out = '';
        $prevLat = 0;
        $prevLng = 0;
        foreach ($points as $point) {
            $lat = (int) round($point['lat'] * 1e5);
            $lng = (int) round($point['lng'] * 1e5);
            $out .= $this->encodeValue($lat - $prevLat).$this->encodeValue($lng - $prevLng);
            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $out;
    }

    private function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $out = '';
        while ($value >= 0x20) {
            $out .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        return $out.chr($value + 63);
    }
}
