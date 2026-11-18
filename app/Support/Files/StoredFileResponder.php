<?php

namespace App\Support\Files;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class StoredFileResponder
{
    public static function absolute(string $diskPath): string
    {
        return storage_path('app/'.$diskPath);
    }

    public static function stream(string $diskPath, string $downloadName, string $mime, bool $inline): BinaryFileResponse
    {
        $absolute = self::absolute($diskPath);
        abort_unless(is_file($absolute), 404);

        $safe = str_replace(['"', "\r", "\n"], '', $downloadName);
        $disposition = $inline ? 'inline' : 'attachment';

        return new BinaryFileResponse($absolute, 200, [
            'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.$safe.'"',
        ], false, null, false, false);
    }
}
