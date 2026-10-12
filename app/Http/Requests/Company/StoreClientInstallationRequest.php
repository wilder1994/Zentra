<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\InstallationKind;
use App\Models\Client;
use App\Models\Installation;
use App\Support\Geo\GeoAddressRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClientInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($this->user()?->can('update', $client) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('is_client_site')) {
            return;
        }

        $client = $this->route('client');
        if ($client instanceof Client) {
            $this->merge(['name' => $client->name]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $geo = $this->boolean('is_client_site')
            ? GeoAddressRules::optional()
            : GeoAddressRules::required();

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'kind' => ['nullable', 'string', Rule::enum(InstallationKind::class)],
            'dane_code' => [
                Rule::requiredIf(fn () => $this->input('kind') === InstallationKind::Colegio->value),
                'nullable',
                'string',
                'regex:/^\d{8,12}$/',
                Rule::unique('installations', 'dane_code')->ignore($this->route('installation') instanceof Installation ? $this->route('installation')->id : null),
            ],
            'commune' => ['nullable', 'string', 'max:80'],
            'rector_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_client_site' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'vista' => ['nullable', 'in:sitio,puertas,accesos,supervision'],
            ...$geo,
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'code' => 'código',
            'kind' => 'tipo de instalación',
            'dane_code' => 'código DANE de la instalación',
            'commune' => 'área',
            'rector_user_id' => 'contacto en directorio',
            'is_client_site' => 'la instalación es el mismo cliente',
            'address' => 'dirección',
            'city' => 'ciudad',
            'department' => 'departamento',
            'latitude' => 'ubicación en el mapa',
            'longitude' => 'ubicación en el mapa',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Fija la ubicación de la instalación en el mapa.',
            'longitude.required' => 'Fija la ubicación de la instalación en el mapa.',
            'dane_code.required' => 'El colegio debe tener código DANE de la instalación.',
            'dane_code.required_if' => 'El colegio debe tener código DANE de la instalación.',
            'dane_code.regex' => 'El código DANE de la instalación debe tener entre 8 y 12 dígitos.',
            'dane_code.unique' => 'Ya existe una instalación con ese código DANE.',
        ];
    }
}
