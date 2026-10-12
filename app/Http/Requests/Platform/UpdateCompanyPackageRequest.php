<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Enums\BillingCycle;
use App\Http\Requests\Concerns\ValidatesPlanSeats;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateCompanyPackageRequest extends FormRequest
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
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            ...$this->planSeatRules(),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validatePlanSeats($validator, false)];
    }
}
