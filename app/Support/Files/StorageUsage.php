<?php

declare(strict_types=1);

namespace App\Support\Files;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class StorageUsage
{
    public static function bytesForCompany(int $companyId): int
    {
        $root = storage_path('app/companies/'.$companyId.'/employees');
        if (! is_dir($root)) {
            return 0;
        }

        $total = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            try {
                $total += $file->getSize();
            } catch (RuntimeException) {
                continue;
            }
        }

        return $total;
    }

    public static function label(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }

        $units = [
            'TB' => 1024 ** 4,
            'GB' => 1024 ** 3,
            'MB' => 1024 ** 2,
            'KB' => 1024,
        ];

        $parts = [];
        $rest = $bytes;
        foreach ($units as $name => $size) {
            if ($rest < $size) {
                continue;
            }

            $count = intdiv($rest, $size);
            $rest %= $size;
            $parts[] = $count.' '.$name;
        }

        return $parts === [] ? $bytes.' B' : implode(' ', $parts);
    }
}
