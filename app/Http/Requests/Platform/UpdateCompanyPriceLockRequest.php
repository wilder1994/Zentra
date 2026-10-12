<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyPriceLockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'price_locked' => ['nullable', 'boolean'],
            'price_locked_until' => ['nullable', 'date', 'after_or_equal:today'],
            'price_lock_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'price_locked_until' => 'vigencia del precio pactado',
            'price_lock_note' => 'nota del acuerdo',
        ];
    }
}
