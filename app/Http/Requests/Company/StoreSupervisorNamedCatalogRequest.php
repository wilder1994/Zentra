<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\SupervisorChecklistKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSupervisorNamedCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^(\d{2}:\d{2})/', $value, $matches) === 1) {
                $this->merge([$field => $matches[1]]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($this->routeIs('company.supervision-shifts.*')) {
            $rules['starts_at'] = ['required', 'date_format:H:i'];
            $rules['ends_at'] = ['required', 'date_format:H:i'];
        }

        if ($this->routeIs('company.supervision-preop.store')) {
            $rules['kind'] = ['required', Rule::enum(SupervisorChecklistKind::class)];
        }

        return $rules;
    }
}
