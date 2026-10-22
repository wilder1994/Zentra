<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Imagen del mapa de calor para el PPTX: base de Google Static Maps (estilo oscuro)
 * con manchas de calor por tipo y etiquetas de folio. Sin clave o sin red, dibuja los puntos sobre fondo liso.
 */
final class RenderObservatoryHeatMapService
{
    private const WIDTH = 640;

    private const HEIGHT = 360;

    private const SCALE = 2;

    private const PADDING = 48;

    /**
     * @param  list<array{id: int, folio: string, lat: float, lng: float, color: string, site: string}>  $points
     * @return array{path: string, groups: list<array{site: string, folios: list<string>, color: string}>, basemap: bool}|null
     */
    public function execute(array $points): ?array
    {
        if ($points === [] || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        [$centerLat, $centerLng, $zoom] = $this->viewport($points);
        $basemap = $this->download($centerLat, $centerLng, $zoom);
        $image = $basemap !== null ? @imagecreatefromstring($basemap) : false;
        if (! $image instanceof GdImage) {
            $image = $this->blank();
        }
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);

        $groups = $this->group($points, $centerLat, $centerLng, $zoom);
        $this->paintHeat($image, $groups);
        $this->paintMarkers($image, $groups);

        $path = tempnam(sys_get_temp_dir(), 'zentra_obs_map_');
        if ($path === false) {
            imagedestroy($image);

            return null;
        }
        @unlink($path);
        $path .= '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return [
            'path' => $path,
            'groups' => array_map(fn (array $g): array => [
                'site' => $g['site'],
                'folios' => $g['folios'],
                'color' => $g['color'],
            ], $groups),
            'basemap' => $basemap !== null,
        ];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return array{0: float, 1: float, 2: int}
     */
    private function viewport(array $points): array
    {
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        $centerLat = (min($lats) + max($lats)) / 2;
        $centerLng = (min($lngs) + max($lngs)) / 2;

        $zoom = 15;
        for (; $zoom > 3; $zoom--) {
            [$x1, $y1] = $this->world(max($lats), min($lngs), $zoom);
            [$x2, $y2] = $this->world(min($lats), max($lngs), $zoom);
            if (abs($x2 - $x1) <= self::WIDTH - 2 * self::PADDING && abs($y2 - $y1) <= self::HEIGHT - 2 * self::PADDING) {
                break;
            }
        }

        return [$centerLat, $centerLng, $zoom];
    }

    /** @return array{0: float, 1: float} Píxeles del mundo (escala 1) a ese zoom. */
    private function world(float $lat, float $lng, int $zoom): array
    {
        $size = 256 * (2 ** $zoom);
        $sin = min(max(sin(deg2rad($lat)), -0.9999), 0.9999);

        return [
            ($lng + 180) / 360 * $size,
            (0.5 - log((1 + $sin) / (1 - $sin)) / (4 * M_PI)) * $size,
        ];
    }

    /** @return array{0: int, 1: int} Píxel dentro de la imagen final (con escala). */
    private function pixel(float $lat, float $lng, float $centerLat, float $centerLng, int $zoom): array
    {
        [$cx, $cy] = $this->world($centerLat, $centerLng, $zoom);
        [$x, $y] = $this->world($lat, $lng, $zoom);

        return [
            (int) round((self::WIDTH / 2 + ($x - $cx)) * self::SCALE),
            (int) round((self::HEIGHT / 2 + ($y - $cy)) * self::SCALE),
        ];
    }

    /**
     * Agrupa folios que caen en el mismo punto del mapa (misma instalación o muy cerca).
     *
     * @param  list<array{id: int, folio: string, lat: float, lng: float, color: string, site: string}>  $points
     * @return list<array{x: int, y: int, site: string, folios: list<string>, ids: list<int>, color: string, colors: list<string>}>
     */
    private function group(array $points, float $centerLat, float $centerLng, int $zoom): array
    {
        $bySite = [];
        foreach ($points as $point) {
            $bySite[$point['site']][] = $point;
        }

        $groups = [];
        foreach ($bySite as $sitePoints) {
            $point = $sitePoints[0];
            $lat = array_sum(array_column($sitePoints, 'lat')) / count($sitePoints);
            $lng = array_sum(array_column($sitePoints, 'lng')) / count($sitePoints);
            [$x, $y] = $this->pixel($lat, $lng, $centerLat, $centerLng, $zoom);
            $found = null;
            foreach ($groups as $index => $group) {
                if (abs($group['x'] - $x) <= 22 && abs($group['y'] - $y) <= 22) {
                    $found = $index;
                    break;
                }
            }
            if ($found === null) {
                $groups[] = ['x' => $x, 'y' => $y, 'site' => $point['site'], 'folios' => [], 'ids' => [], 'color' => $point['color'], 'colors' => []];
                $found = array_key_last($groups);
            }
            foreach ($sitePoints as $sitePoint) {
                $groups[$found]['folios'][] = $sitePoint['folio'];
                $groups[$found]['ids'][] = $sitePoint['id'];
                $groups[$found]['colors'][] = $sitePoint['color'];
            }
        }

        foreach ($groups as &$group) {
            $counts = array_count_values($group['colors']);
            arsort($counts);
            $group['color'] = (string) array_key_first($counts);
        }
        unset($group);

        usort($groups, fn (array $a, array $b): int => count($b['folios']) <=> count($a['folios']));

        return $groups;
    }

    /** @param  list<array{x: int, y: int, folios: list<string>, color: string}>  $groups */
    private function paintHeat(GdImage $image, array $groups): void
    {
        $max = max(array_map(fn (array $g): int => count($g['folios']), $groups));
        foreach ($groups as $group) {
            [$r, $g, $b] = $this->rgb($group['color']);
            $weight = count($group['folios']) / max(1, $max);
            $radius = (int) round(44 + 80 * $weight);
            for ($ring = 10; $ring >= 1; $ring--) {
                $size = (int) round($radius * 2 * $ring / 10);
                $alpha = (int) round(127 - (8 + 5 * $weight) * (11 - $ring) / 2);
                $color = imagecolorallocatealpha($image, $r, $g, $b, max(40, min(127, $alpha)));
                imagefilledellipse($image, $group['x'], $group['y'], $size, $size, $color);
            }
        }
    }

    /** @param  list<array{x: int, y: int, folios: list<string>, ids: list<int>, color: string}>  $groups */
    private function paintMarkers(GdImage $image, array $groups): void
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $hasFont = is_file($font) && function_exists('imagettftext');
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 15, 23, 42);
        $labelBg = imagecolorallocatealpha($image, 15, 23, 42, 22);
        $labelLine = imagecolorallocatealpha($image, 148, 163, 184, 60);

        foreach ($groups as $group) {
            [$r, $g, $b] = $this->rgb($group['color']);
            $fill = imagecolorallocate($image, $r, $g, $b);
            $count = count($group['folios']);
            $dot = $count > 1 ? 34 : 22;
            imagefilledellipse($image, $group['x'], $group['y'], $dot + 6, $dot + 6, $white);
            imagefilledellipse($image, $group['x'], $group['y'], $dot, $dot, $fill);

            if (! $hasFont) {
                imagestring($image, 3, $group['x'] + 18, $group['y'] - 8, $this->label($group['ids']), $white);

                continue;
            }

            if ($count > 1) {
                $this->centered($image, (string) $count, $font, 13, $group['x'], $group['y'], $ink);
            }

            $text = $this->label($group['ids']);
            $box = imagettfbbox(15, 0, $font, $text);
            $width = abs($box[2] - $box[0]);
            $x = $group['x'] + (int) ($dot / 2) + 10;
            $y = $group['y'] - 15;
            if ($x + $width + 16 > self::WIDTH * self::SCALE) {
                $x = $group['x'] - (int) ($dot / 2) - 10 - $width - 16;
            }
            imagefilledrectangle($image, $x, $y, $x + $width + 16, $y + 30, $labelBg);
            imagerectangle($image, $x, $y, $x + $width + 16, $y + 30, $labelLine);
            imagettftext($image, 15, 0, $x + 8, $y + 22, $white, $font, $text);
        }
    }

    /** @param  list<int>  $ids  Folios cortos: «EV-24» o «EV-24, 23, 19 +7». */
    private function label(array $ids): string
    {
        $shown = array_slice($ids, 0, 3);
        $rest = count($ids) - count($shown);

        return 'EV-'.implode(', ', $shown).($rest > 0 ? ' +'.$rest : '');
    }

    private function centered(GdImage $image, string $text, string $font, int $size, int $x, int $y, int $color): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = abs($box[2] - $box[0]);
        $height = abs($box[7] - $box[1]);
        imagettftext($image, $size, 0, $x - (int) ($width / 2), $y + (int) ($height / 2), $color, $font, $text);
    }

    private function blank(): GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);
        imagefill($image, 0, 0, imagecolorallocate($image, 15, 23, 42));
        $grid = imagecolorallocate($image, 30, 41, 59);
        for ($x = 0; $x < self::WIDTH * self::SCALE; $x += 80) {
            imageline($image, $x, 0, $x, self::HEIGHT * self::SCALE, $grid);
        }
        for ($y = 0; $y < self::HEIGHT * self::SCALE; $y += 80) {
            imageline($image, 0, $y, self::WIDTH * self::SCALE, $y, $grid);
        }

        return $image;
    }

    private function download(float $lat, float $lng, int $zoom): ?string
    {
        $key = (string) config('google-maps.server_api_key');
        if ($key === '') {
            return null;
        }

        $styles = [
            'element:geometry|color:0x1e293b',
            'element:labels.text.fill|color:0xcbd5e1',
            'element:labels.text.stroke|color:0x0f172a',
            'feature:road|element:geometry|color:0x334155',
            'feature:road|element:labels.icon|visibility:off',
            'feature:water|element:geometry|color:0x0c4a6e',
            'feature:poi|visibility:off',
            'feature:transit|visibility:off',
            'feature:administrative|element:geometry.stroke|color:0x475569',
        ];
        $url = 'https://maps.googleapis.com/maps/api/staticmap?'.http_build_query([
            'center' => round($lat, 6).','.round($lng, 6),
            'zoom' => $zoom,
            'size' => self::WIDTH.'x'.self::HEIGHT,
            'scale' => self::SCALE,
            'maptype' => 'roadmap',
            'key' => $key,
        ]);
        foreach ($styles as $style) {
            $url .= '&style='.rawurlencode($style);
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders(['Referer' => rtrim((string) config('app.url'), '/').'/'])
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('Mapa de calor del Observatorio no disponible', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            Log::warning('Mapa de calor del Observatorio no disponible', ['status' => $response->status(), 'body' => mb_substr((string) $response->body(), 0, 300)]);

            return null;
        }

        return $response->body();
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [148, 163, 184];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
