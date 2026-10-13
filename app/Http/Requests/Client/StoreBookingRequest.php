<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.bookings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['full_day' => $this->boolean('full_day')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'common_zone_id' => ['required', 'integer'],
            'structure_id' => ['required', 'integer'],
            'structure_member_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'date_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'full_day' => ['boolean'],
            'start_time' => ['exclude_if:full_day,true', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_if:full_day,true', 'required', 'date_format:H:i', 'after:start_time'],
            'people_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'guests' => ['nullable', 'array', 'max:150'],
            'guests.*.document_type' => ['nullable', 'string', 'max:10'],
            'guests.*.document_number' => ['required', 'string', 'max:30'],
            'guests.*.first_name' => ['required', 'string', 'max:100'],
            'guests.*.last_name' => ['required', 'string', 'max:100'],
            'guests.*.phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'structure_id.required' => 'Elige la unidad que reserva.',
            'date.required' => 'Elige el día.',
            'date_until.after_or_equal' => 'El día final no puede ser anterior al inicial.',
            'start_time.required' => 'Indica la hora de inicio o marca todo el día.',
            'end_time.required' => 'Indica la hora final o marca todo el día.',
            'end_time.after' => 'La hora final debe ser posterior a la inicial.',
            'people_count.required' => 'Indica cuántas personas usarán la zona.',
            'guests.*.document_number.required' => 'Cada invitado necesita documento.',
            'guests.*.first_name.required' => 'Cada invitado necesita nombres.',
            'guests.*.last_name.required' => 'Cada invitado necesita apellidos.',
        ];
    }
}
