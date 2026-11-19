<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\EmployeeDocument;
use App\Models\PersonnelFolder;
use App\Models\PersonnelFolderType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManagePersonnelCatalogService
{
    public function createFolder(int $companyId, string $name): PersonnelFolder
    {
        $name = $this->cleanName($name, 120);

        return PersonnelFolder::query()->create([
            'security_company_id' => $companyId,
            'slug' => $this->uniqueFolderSlug($companyId, $name),
            'name' => $name,
            'behavior' => PersonnelFolder::STANDARD,
            'sort_order' => (int) PersonnelFolder::query()->where('security_company_id', $companyId)->max('sort_order') + 1,
            'is_active' => true,
        ]);
    }

    public function renameFolder(PersonnelFolder $folder, string $name): void
    {
        $folder->update(['name' => $this->cleanName($name, 120)]);
    }

    public function setFolderActive(PersonnelFolder $folder, bool $active): void
    {
        $folder->update(['is_active' => $active]);
    }

    public function deleteFolder(PersonnelFolder $folder): void
    {
        if ($this->folderDocumentCount($folder) > 0) {
            throw ValidationException::withMessages([
                'folder' => 'Esta carpeta tiene documentos de trabajadores. No se puede eliminar; solo desactivar.',
            ]);
        }

        $folder->delete();
    }

    public function createType(PersonnelFolder $folder, string $name): PersonnelFolderType
    {
        $name = $this->cleanName($name, 160);

        return $folder->types()->create([
            'security_company_id' => $folder->security_company_id,
            'slug' => $this->uniqueTypeSlug($folder, $name),
            'name' => $name,
            'requirement' => 'optional',
            'is_repeatable' => false,
            'sort_order' => (int) $folder->types()->max('sort_order') + 1,
            'is_active' => true,
        ]);
    }

    public function renameType(PersonnelFolderType $type, string $name): void
    {
        $type->update(['name' => $this->cleanName($name, 160)]);
    }

    public function setTypeActive(PersonnelFolderType $type, bool $active): void
    {
        $type->update(['is_active' => $active]);
    }

    public function deleteType(PersonnelFolderType $type): void
    {
        $type->loadMissing('folder');
        if ($this->typeDocumentCount($type) > 0) {
            throw ValidationException::withMessages([
                'type' => 'Este tipo tiene documentos asociados. No se puede eliminar; solo desactivar.',
            ]);
        }

        $type->delete();
    }

    public function folderDocumentCount(PersonnelFolder $folder): int
    {
        return EmployeeDocument::query()
            ->where('security_company_id', $folder->security_company_id)
            ->where('folder', $folder->slug)
            ->count();
    }

    public function typeDocumentCount(PersonnelFolderType $type): int
    {
        $type->loadMissing('folder');

        return EmployeeDocument::query()
            ->where('security_company_id', $type->security_company_id)
            ->where('folder', $type->folder?->slug)
            ->where('document_type', $type->slug)
            ->count();
    }

    private function cleanName(string $name, int $max): string
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'El nombre es obligatorio.',
            ]);
        }

        return Str::limit($name, $max, '');
    }

    private function uniqueFolderSlug(int $companyId, string $name): string
    {
        $base = $this->slug($name, 32);
        $candidate = $base;
        $n = 2;
        while (PersonnelFolder::query()->where('security_company_id', $companyId)->where('slug', $candidate)->exists()) {
            $suffix = '_'.$n;
            $candidate = $this->slug(substr($base, 0, 32 - strlen($suffix)).$suffix, 32);
            $n++;
        }

        return $candidate;
    }

    private function uniqueTypeSlug(PersonnelFolder $folder, string $name): string
    {
        $base = $this->slug($name, 64);
        $candidate = $base;
        $n = 2;
        while ($folder->types()->where('slug', $candidate)->exists()) {
            $suffix = '_'.$n;
            $candidate = $this->slug(substr($base, 0, 64 - strlen($suffix)).$suffix, 64);
            $n++;
        }

        return $candidate;
    }

    private function slug(string $name, int $limit): string
    {
        $slug = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit($limit, '')
            ->toString();

        return $slug !== '' ? $slug : 'item';
    }
}
