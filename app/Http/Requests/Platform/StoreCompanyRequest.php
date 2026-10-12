<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Enums\BillingCycle;
use App\Enums\PartyType;
use App\Http\Requests\Concerns\ValidatesPlanSeats;
use App\Support\Geo\GeoAddressRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreCompanyRequest extends FormRequest
{
    use ValidatesPlanSeats;

    public function authorize(): bool
    {
        return $this->user()?->can('platform.companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:160'],
            'trade_name' => ['required', 'string', 'max:160'],
            'tax_id' => ['required', 'string', 'max:40', Rule::unique('security_companies', 'tax_id')],
            'party_type' => ['required', Rule::enum(PartyType::class)],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            ...$this->planSeatRules(),
            ...GeoAddressRules::required(),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validatePlanSeats($validator, false)];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'legal_name' => 'razón social',
            'trade_name' => 'nombre comercial',
            'tax_id' => 'NIT',
            'party_type' => 'tipo de suscriptor',
            'email' => 'email comercial',
            'phone' => 'teléfono',
            'billing_cycle' => 'ciclo',
            ...$this->planSeatAttributes(),
            'address' => 'dirección',
            'city' => 'ciudad',
            'department' => 'departamento',
            'latitude' => 'coordenadas',
            'longitude' => 'coordenadas',
        ];
    }
}
