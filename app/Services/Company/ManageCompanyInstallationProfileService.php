<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\InstallationProfile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageCompanyInstallationProfileService
{
    /**
     * @param  array{
     *     name: string,
     *     requires_dane?: bool,
     *     observatory_community?: bool,
     *     is_active?: bool
     * }  $data
     */
    public function create(int $companyId, array $data): InstallationProfile
    {
        $name = trim($data['name']);
        $this->assertUniqueName($companyId, $name);

        $max = (int) InstallationProfile::query()
            ->where('security_company_id', $companyId)
            ->max('sort_order');

        return InstallationProfile::query()->create([
            'security_company_id' => $companyId,
            'code' => $this->uniqueCodeFromName($companyId, $name),
            'name' => $name,
            'requires_dane' => (bool) ($data['requires_dane'] ?? false),
            'observatory_community' => (bool) ($data['observatory_community'] ?? false),
            'is_system' => false,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $max + 10,
        ]);
    }

    /**
     * @param  array{
     *     name?: string,
     *     requires_dane?: bool,
     *     observatory_community?: bool,
     *     is_active?: bool
     * }  $data
     */
    public function update(InstallationProfile $profile, array $data): InstallationProfile
    {
        if ($profile->is_system) {
            throw ValidationException::withMessages([
                'profile' => 'El perfil «Colegio» es del sistema y no se puede modificar.',
            ]);
        }

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            $this->assertUniqueName((int) $profile->security_company_id, $name, $profile->id);
            $profile->name = $name;
        }

        if (array_key_exists('requires_dane', $data)) {
            $profile->requires_dane = (bool) $data['requires_dane'];
        }

        if (array_key_exists('observatory_community', $data)) {
            $profile->observatory_community = (bool) $data['observatory_community'];
        }

        if (array_key_exists('is_active', $data)) {
            $profile->is_active = (bool) $data['is_active'];
        }

        $profile->save();

        return $profile->refresh();
    }

    public function delete(InstallationProfile $profile): void
    {
        if ($profile->is_system) {
            throw ValidationException::withMessages([
                'profile' => 'El perfil «Colegio» es del sistema y no se puede eliminar.',
            ]);
        }

        if ($profile->installations()->exists()) {
            throw ValidationException::withMessages([
                'profile' => 'No se puede eliminar: hay instalaciones usando este perfil.',
            ]);
        }

        $profile->delete();
    }

    private function assertUniqueName(int $companyId, string $name, ?int $ignoreId = null): void
    {
        $exists = InstallationProfile::query()
            ->where('security_company_id', $companyId)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un perfil de sitio con ese nombre en la empresa.',
            ]);
        }
    }

    private function uniqueCodeFromName(int $companyId, string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'perfil';
        }

        $base = Str::limit($base, 45, '');
        $code = $base;
        $suffix = 2;

        while (InstallationProfile::query()
            ->where('security_company_id', $companyId)
            ->where('code', $code)
            ->exists()
        ) {
            $code = Str::limit($base, 40, '').'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
