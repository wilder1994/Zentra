<?php

declare(strict_types=1);

namespace App\Support\Resident;

final class ResidentAppUrl
{
    public static function web(): string
    {
        $configured = trim((string) config('resident.web_url'));
        if ($configured !== '') {
            return rtrim($configured, '/').'/';
        }

        return rtrim((string) config('app.url'), '/').'/r/';
    }

    public static function apkPath(): ?string
    {
        $path = public_path('downloads/zentra-residentes.apk');

        return is_file($path) ? $path : null;
    }

    public static function apkReady(): bool
    {
        return self::apkPath() !== null;
    }

    public static function maxUsersPerStructure(): int
    {
        return max(1, (int) config('resident.max_app_users_per_structure', 4));
    }
}
