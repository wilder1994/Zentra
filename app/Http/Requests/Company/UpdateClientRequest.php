<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\PartyType;
use App\Models\Client;
use App\Support\Geo\GeoAddressRules;
use App\Support\Legal\CorpusAcceptanceRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = $this->route('client');

        return $this->user()?->can('update', $client) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');
        $companyId = (int) $client->security_company_id;
        $isLegalEntity = $this->input('party_type') === PartyType::LegalEntity->value;

        return [
            'party_type' => ['required', Rule::enum(PartyType::class)],
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => [$isLegalEntity ? 'required' : 'nullable', 'string', 'max:150'],
            'document_type' => CorpusAcceptanceRules::documentTypeRule(),
            'tax_id' => [
                'required',
                'string',
                'max:40',
                Rule::unique('clients', 'tax_id')
                    ->where('security_company_id', $companyId)
                    ->ignore($client->id),
            ],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'representative_name' => [$isLegalEntity ? 'required' : 'nullable', 'string', 'max:150'],
            'representative_email' => [$isLegalEntity ? 'required' : 'nullable', 'email', 'max:150'],
            'structure_type_id' => [
                'required',
                'integer',
                Rule::exists('structure_types', 'id')
                    ->where('is_active', true)
                    ->where('security_company_id', $companyId),
            ],
            ...GeoAddressRules::optional(),
            'service_started_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'has_access' => ['sometimes', 'boolean'],
            'has_supervision' => ['sometimes', 'boolean'],
            'has_observatory' => ['sometimes', 'boolean'],
            'show_personnel_folders' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'party_type' => 'tipo de cliente',
            'name' => 'nombre comercial',
            'legal_name' => 'razón social',
            'document_type' => 'tipo de documento',
            'tax_id' => 'número de documento',
            'email' => 'correo de contacto',
            'representative_name' => 'representante legal',
            'representative_email' => 'correo del representante',
            'structure_type_id' => 'tipo de estructura',
        ];
    }
}
