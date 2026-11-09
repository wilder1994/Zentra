<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Models\InstallationProfile;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyInstallationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('installationProfile');

        return $profile instanceof InstallationProfile
            && ($this->user()?->can('update', $profile) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'requires_dane' => ['sometimes', 'boolean'],
            'observatory_community' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'requires_dane' => 'requiere DANE',
            'observatory_community' => 'Observatorio comunidad',
            'is_active' => 'activo',
        ];
    }
}
