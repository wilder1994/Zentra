<?php

namespace App\Support\Personnel;

use App\Enums\CourseDocumentType;
use App\Enums\DocumentFolder;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Support\Str;

final class OtherSupportNamer
{
    public static function slug(string $tipo): string
    {
        return Str::of($tipo)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->limit(40, '')
            ->toString();
    }

    public static function normalize(string $text): string
    {
        return Str::of($text)
            ->ascii()
            ->lower()
            ->replaceMatches('/\.pdf$/i', '')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    public static function suffix(Employee $employee): string
    {
        $who = Str::of($employee->full_name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return '_'.$employee->document_number.'_'.$who;
    }

    public static function suggestedName(string $tipo, Employee $employee): string
    {
        $slug = self::slug($tipo);

        return $slug === '' ? '' : $slug.self::suffix($employee);
    }

    public static function stem(string $name, Employee $employee): string
    {
        $base = (string) preg_replace('/\.pdf$/i', '', $name);
        $suffix = self::suffix($employee);
        if ($suffix !== '' && str_ends_with($base, $suffix)) {
            $base = substr($base, 0, -strlen($suffix));
        }

        return self::normalize($base);
    }

    /** @return list<array{folder: string, label: string, slug: string, label_slug: string, value: string, name: string}> */
    public static function reserved(Employee $employee): array
    {
        $rows = [];
        foreach (DocumentFolder::cases() as $folder) {
            if ($folder === DocumentFolder::Otros || $folder === DocumentFolder::Parafiscales) {
                continue;
            }

            foreach (IndexedFolder::types($folder) as $type) {
                if ($type instanceof CourseDocumentType && $type->isRepeatable()) {
                    continue;
                }

                $rows[] = [
                    'folder' => $folder->label(),
                    'label' => $type->label(),
                    'slug' => self::normalize($type->filenameSlug()),
                    'label_slug' => self::normalize($type->label()),
                    'value' => $type->value,
                    'name' => self::normalize($type->suggestedName($employee)),
                ];
            }
        }

        return $rows;
    }

    /** @return array{folder: string, label: string}|null */
    public static function conflict(string $tipo, string $displayName, Employee $employee): ?array
    {
        $needles = array_values(array_filter([
            self::normalize($tipo),
            self::normalize($displayName),
            self::stem($displayName, $employee),
        ]));

        foreach (self::reserved($employee) as $row) {
            foreach ($needles as $needle) {
                if (self::hits($needle, $row)) {
                    return ['folder' => $row['folder'], 'label' => $row['label']];
                }
            }
        }

        return null;
    }

    public static function loadedCount(Employee $employee): int
    {
        return $employee->documents
            ->filter(fn (EmployeeDocument $doc) => (string) $doc->folder === DocumentFolder::Otros->value && $doc->hasFile())
            ->count();
    }

    /** @param array{folder: string, label: string, slug: string, label_slug: string, value: string, name: string} $row */
    private static function hits(string $needle, array $row): bool
    {
        foreach ([$row['slug'], $row['label_slug'], $row['value'], $row['name']] as $reserved) {
            if ($reserved === '' || $needle === '') {
                continue;
            }
            if ($needle === $reserved || str_starts_with($needle, $reserved.'_')) {
                return true;
            }
        }

        return false;
    }
}
