<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;

/** Miniatura JPEG para fichas y PDF. La foto original no entra en el HTML. */
final class SheetImage
{
    public static function dataUri(?string $path): ?string
    {
        $thumb = self::thumb($path);
        if ($thumb === null) {
            return null;
        }

        $bytes = Storage::disk('local')->get($thumb);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $mime = str_ends_with(strtolower($thumb), '.sheet.jpg')
            ? 'image/jpeg'
            : (Storage::disk('local')->mimeType($thumb) ?: 'image/jpeg');

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    public static function thumb(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $size = Storage::disk('local')->size($path);
        if ($size !== false && $size < 180000) {
            return $path;
        }

        $thumb = $path.'.sheet.jpg';
        if (Storage::disk('local')->exists($thumb)) {
            return $thumb;
        }
        if (! function_exists('imagecreatefromstring')) {
            return $path;
        }

        $raw = Storage::disk('local')->get($path);
        if ($raw === null || $raw === '') {
            return null;
        }
        $image = @imagecreatefromstring($raw);
        unset($raw);
        if ($image === false) {
            return $path;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $edge = max($width, $height, 1);
        $scale = min(1, 640 / $edge);
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));
        $scaled = imagescale($image, $targetW, $targetH);
        imagedestroy($image);
        if ($scaled === false) {
            return $path;
        }

        ob_start();
        imagejpeg($scaled, null, 70);
        $jpeg = ob_get_clean();
        imagedestroy($scaled);
        if (! is_string($jpeg) || $jpeg === '') {
            return $path;
        }

        Storage::disk('local')->put($thumb, $jpeg);

        return $thumb;
    }
}
