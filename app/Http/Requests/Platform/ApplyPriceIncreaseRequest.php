<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class ApplyPriceIncreaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'percent' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'effective_on' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'percent' => 'porcentaje',
            'effective_on' => 'fecha desde',
        ];
    }
}
