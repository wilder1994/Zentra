<?php

declare(strict_types=1);

namespace App\Support\Supervision;

final class SupervisionAppUrl
{
    public static function pwa(): string
    {
        $configured = trim((string) config('supervision.pwa_url'));
        if ($configured !== '') {
            $url = rtrim($configured, '/');

            return str_ends_with($url, '/campo') ? $url.'/' : $url;
        }

        $app = rtrim((string) config('app.url'), '/');
        $host = (string) (parse_url($app, PHP_URL_HOST) ?: '');

        if (str_ends_with($host, '.test') && str_starts_with($host, 'zentra.')) {
            return (string) preg_replace('#://zentra\.#i', '://zentra_supervision.', $app, 1);
        }

        return $app.'/campo/';
    }

    public static function apkPath(): ?string
    {
        $path = public_path('downloads/zentra-supervision.apk');

        return is_file($path) ? $path : null;
    }

    public static function apkReady(): bool
    {
        return self::apkPath() !== null;
    }
}
