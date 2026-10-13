<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Models\MemberType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageClientMemberTypeService
{
    public const DEFAULT_NAMES = ['Propietario', 'Inquilino', 'Familiar', 'Empleado doméstico', 'Administración'];

    public function ensureDefaults(int $clientId): void
    {
        $hasAny = MemberType::query()->withoutGlobalScopes()->where('client_id', $clientId)->exists();
        if ($hasAny) {
            return;
        }

        foreach (self::DEFAULT_NAMES as $i => $name) {
            MemberType::query()->withoutGlobalScopes()->create([
                'client_id' => $clientId,
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
            ]);
        }
    }

    /** @param array{name: string, is_active?: bool} $data */
    public function create(int $clientId, array $data): MemberType
    {
        $name = trim($data['name']);
        $this->assertUniqueName($clientId, $name);

        $max = (int) MemberType::query()
            ->where('client_id', $clientId)
            ->max('sort_order');

        return MemberType::query()->create([
            'client_id' => $clientId,
            'name' => $name,
            'slug' => $this->uniqueSlug($clientId, $name),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $max + 10,
        ]);
    }

    /** @param array{name?: string, is_active?: bool} $data */
    public function update(MemberType $type, array $data): MemberType
    {
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            $this->assertUniqueName((int) $type->client_id, $name, $type->id);
            $type->name = $name;
        }

        if (array_key_exists('is_active', $data)) {
            $type->is_active = (bool) $data['is_active'];
        }

        $type->save();

        return $type->refresh();
    }

    public function delete(MemberType $type): void
    {
        if ($type->members()->exists()) {
            throw ValidationException::withMessages([
                'member_type' => 'No se puede eliminar: hay personas usando este tipo.',
            ]);
        }

        $type->delete();
    }

    public function firstOrCreateNamed(int $clientId, string $name): MemberType
    {
        $name = trim($name);
        $existing = MemberType::query()
            ->where('client_id', $clientId)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->create($clientId, ['name' => $name, 'is_active' => true]);
    }

    private function assertUniqueName(int $clientId, string $name, ?int $ignoreId = null): void
    {
        $exists = MemberType::query()
            ->where('client_id', $clientId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un tipo de persona con ese nombre.',
            ]);
        }
    }

    private function uniqueSlug(int $clientId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'tipo';
        }

        $base = Str::limit($base, 45, '');
        $code = $base;
        $suffix = 2;

        while (MemberType::query()
            ->where('client_id', $clientId)
            ->where('slug', $code)
            ->exists()
        ) {
            $code = Str::limit($base, 40, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
