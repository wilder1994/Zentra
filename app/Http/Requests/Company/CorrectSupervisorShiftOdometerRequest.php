<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

final class CorrectSupervisorShiftOdometerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.settings.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'km_start' => ['required', 'integer', 'min:0', 'max:9999999'],
            'km_end' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'km_start' => 'kilometraje de inicio',
            'km_end' => 'kilometraje de cierre',
            'reason' => 'motivo',
        ];
    }
}
