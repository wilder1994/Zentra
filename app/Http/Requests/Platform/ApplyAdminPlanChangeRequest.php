<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Enums\BillingCycle;
use App\Http\Requests\Concerns\ValidatesPlanSeats;
use App\Services\Platform\ApplyAdminPlanChangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ApplyAdminPlanChangeRequest extends FormRequest
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
            'apply_when' => ['required', Rule::in([
                ApplyAdminPlanChangeService::WHEN_NOW,
                ApplyAdminPlanChangeService::WHEN_ON_DATE,
                ApplyAdminPlanChangeService::WHEN_PERIOD_END,
            ])],
            'effective_on' => [
                'nullable',
                'date',
                'after_or_equal:today',
                Rule::requiredIf($this->input('apply_when') === ApplyAdminPlanChangeService::WHEN_ON_DATE),
            ],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validatePlanSeats($validator, false)];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->planSeatAttributes();
    }
}
