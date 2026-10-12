<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\BillingCycle;
use App\Http\Requests\Concerns\ValidatesPlanSeats;
use App\Models\SecurityCompany;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SchedulePackageChangeRequest extends FormRequest
{
    use ValidatesPlanSeats;

    public function authorize(): bool
    {
        return $this->user()?->can('company.billing.manage') ?? false;
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
        return [function (Validator $validator): void {
            $companyId = app(ActingCompanyResolver::class)->id($this->user());
            $company = $companyId !== null ? SecurityCompany::query()->find($companyId) : null;
            $this->validatePlanSeats($validator, true, $company);
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->planSeatAttributes();
    }
}
