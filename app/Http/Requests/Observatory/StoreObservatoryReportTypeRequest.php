<?php

declare(strict_types=1);

namespace App\Http\Requests\Observatory;

use Illuminate\Foundation\Http\FormRequest;

final class StoreObservatoryReportTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'impacto' => ['required', 'integer', 'between:1,10'],
            'urgencia' => ['required', 'integer', 'between:1,10'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'impacto' => 'impacto',
            'urgencia' => 'urgencia',
            'color' => 'color',
        ];
    }
}
