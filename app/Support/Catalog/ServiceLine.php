<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/** Líneas comerciales del cliente: Accesos, Supervisión, Observatorio. */
final class ServiceLine
{
    public const ACCESS = 'access';

    public const SUPERVISION = 'supervision';

    public const OBSERVATORY = 'observatory';

    public const MODE_TEMPORARY = 'temporary';

    public const MODE_ARCHIVE = 'archive';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ACCESS, self::SUPERVISION, self::OBSERVATORY];
    }

    public static function flag(string $line): string
    {
        return 'has_'.$line;
    }

    public static function archivedAtColumn(string $line): string
    {
        return $line.'_line_archived_at';
    }

    public static function label(string $line): string
    {
        return match ($line) {
            self::ACCESS => 'Accesos',
            self::SUPERVISION => 'Supervisión',
            self::OBSERVATORY => 'Observatorio',
            default => $line,
        };
    }

    public static function isValid(string $line): bool
    {
        return in_array($line, self::all(), true);
    }

    public static function isValidMode(string $mode): bool
    {
        return in_array($mode, [self::MODE_TEMPORARY, self::MODE_ARCHIVE], true);
    }
}
