<?php

declare(strict_types=1);

namespace App\Support\Observatory;

/** Segundos de un video. Usa ffprobe si existe; si no, lee la caja mvhd de un mp4. */
final class ObservatoryVideoDuration
{
    public static function seconds(string $path): ?float
    {
        $probed = self::ffprobe($path);
        if ($probed !== null) {
            return $probed;
        }

        return self::mp4($path);
    }

    private static function ffprobe(string $path): ?float
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $bin = stripos(PHP_OS, 'WIN') === 0 ? 'ffprobe.exe' : 'ffprobe';
        $command = $bin.' -v error -show_entries format=duration -of default=nw=1:nk=1 '.escapeshellarg($path);
        $out = shell_exec($command);
        if (! is_string($out) || ! is_numeric(trim($out))) {
            return null;
        }

        return (float) trim($out);
    }

    private static function mp4(string $path): ?float
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 262144);
        fclose($handle);
        if (! is_string($header)) {
            return null;
        }

        $at = strpos($header, 'mvhd');
        if ($at === false) {
            return null;
        }

        $version = ord($header[$at + 4] ?? "\0");
        if ($version === 0 && strlen($header) >= $at + 24) {
            $timescale = self::u32($header, $at + 16);
            $duration = self::u32($header, $at + 20);
        } elseif ($version === 1 && strlen($header) >= $at + 36) {
            $timescale = self::u32($header, $at + 24);
            $high = self::u32($header, $at + 28);
            $low = self::u32($header, $at + 32);
            $duration = $high * 4294967296 + $low;
        } else {
            return null;
        }

        if ($timescale <= 0) {
            return null;
        }

        return $duration / $timescale;
    }

    private static function u32(string $bytes, int $offset): int
    {
        $chunk = substr($bytes, $offset, 4);

        return $chunk === false || strlen($chunk) < 4 ? 0 : unpack('N', $chunk)[1];
    }
}
