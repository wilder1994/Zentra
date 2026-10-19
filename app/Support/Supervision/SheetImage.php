<?php

declare(strict_types=1);

namespace App\Support\Supervision;

/**
 * Recorta y reduce una foto (data URI) al tamaño del recuadro de la ficha.
 * El PDF (dompdf) no entiende object-fit: sin esto las fotos saldrían estiradas.
 */
final class SheetImage
{
    public static function cover(?string $dataUri, int $width, int $height): ?string
    {
        if ($dataUri === null || ! str_starts_with($dataUri, 'data:') || ! function_exists('imagecreatefromstring')) {
            return $dataUri;
        }
        $comma = strpos($dataUri, ',');
        if ($comma === false) {
            return $dataUri;
        }
        $bytes = base64_decode(substr($dataUri, $comma + 1), true);
        $source = $bytes !== false ? @imagecreatefromstring($bytes) : false;
        if ($source === false) {
            return $dataUri;
        }

        $sw = imagesx($source);
        $sh = imagesy($source);
        $ratio = $width / $height;
        if ($sw / $sh > $ratio) {
            $cropH = $sh;
            $cropW = (int) round($sh * $ratio);
        } else {
            $cropW = $sw;
            $cropH = (int) round($sw / $ratio);
        }
        $x = (int) floor(($sw - $cropW) / 2);
        $y = (int) floor(($sh - $cropH) / 2);

        $outW = min($width, $cropW);
        $outH = (int) round($outW / $ratio);
        $target = imagecreatetruecolor($outW, $outH);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);

        ob_start();
        imagejpeg($target, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }
}
