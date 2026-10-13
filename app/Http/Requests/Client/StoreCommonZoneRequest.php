<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Models\CommonZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCommonZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.bookings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_full_day' => $this->boolean('allow_full_day'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(CommonZone::TYPES))],
            'booking_mode' => ['required', Rule::in([CommonZone::MODE_EXCLUSIVE, CommonZone::MODE_SHARED])],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            'allow_full_day' => ['boolean'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'min_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'max_hours' => ['nullable', 'numeric', 'gte:min_hours', 'max:24'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_per_unit_month' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'installation_id.required' => 'Elige la instalación de la zona.',
            'name.required' => 'Escribe el nombre de la zona.',
            'close_time.after' => 'La hora de cierre debe ser posterior a la de apertura.',
            'weekdays.required' => 'Elige al menos un día en que se puede reservar.',
            'max_hours.gte' => 'La duración máxima no puede ser menor que la mínima.',
        ];
    }
}
