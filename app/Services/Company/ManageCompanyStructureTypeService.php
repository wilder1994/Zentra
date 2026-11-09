<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\StructureType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageCompanyStructureTypeService
{
    /** @param array{name: string, is_active?: bool} $data */
    public function create(int $companyId, array $data): StructureType
    {
        $name = trim($data['name']);
        $this->assertUniqueName($companyId, $name);

        $max = (int) StructureType::query()
            ->where('security_company_id', $companyId)
            ->max('sort_order');

        return StructureType::query()->create([
            'security_company_id' => $companyId,
            'code' => $this->uniqueCodeFromName($companyId, $name),
            'name' => $name,
            'description' => null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $max + 10,
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function update(StructureType $type, array $data): StructureType
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            $this->assertUniqueName((int) $type->security_company_id, $name, $type->id);
            $type->name = $name;
        }

        if (array_key_exists('is_active', $data)) {
            $type->is_active = (bool) $data['is_active'];
        }

        $type->save();

        return $type->refresh();
    }

    public function delete(StructureType $type): void
    {
        if ($type->structures()->exists() || $type->clients()->exists()) {
            throw ValidationException::withMessages([
                'structure_type' => 'No se puede eliminar: hay clientes o estructuras usando este tipo.',
            ]);
        }

        $type->delete();
    }

    private function assertUniqueName(int $companyId, string $name, ?int $ignoreId = null): void
    {
        $exists = StructureType::query()
            ->where('security_company_id', $companyId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un tipo de estructura con ese nombre en la empresa.',
            ]);
        }
    }

    private function uniqueCodeFromName(int $companyId, string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'tipo';
        }

        $base = Str::limit($base, 45, '');
        $code = $base;
        $suffix = 2;

        while (StructureType::query()
            ->where('security_company_id', $companyId)
            ->where('code', $code)
            ->exists()
        ) {
            $code = Str::limit($base, 40, '').'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
};
