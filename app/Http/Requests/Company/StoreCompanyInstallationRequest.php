<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\InstallationKind;
use App\Models\Client;
use App\Models\Installation;
use App\Support\Geo\GeoAddressRules;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCompanyInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.clients.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('is_client_site')) {
            return;
        }

        $client = $this->client();
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

        $companyId = app(ActingCompanyResolver::class)->requireId($this->user());

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('security_company_id', $companyId)),
            ],
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
            ...$geo,
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
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
            'dane_code.required' => 'El colegio debe tener código DANE de la instalación.',
            'dane_code.required_if' => 'El colegio debe tener código DANE de la instalación.',
            'dane_code.regex' => 'El código DANE de la instalación debe tener entre 8 y 12 dígitos.',
            'dane_code.unique' => 'Ya existe una instalación con ese código DANE.',
        ];
    }

    public function client(): ?Client
    {
        $id = (int) $this->input('client_id');

        return $id > 0 ? Client::query()->find($id) : null;
    }
}
