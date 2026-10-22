<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class CloseSupervisorShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('supervisor') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'km_end' => ['required', 'integer', 'min:0'],
            'odometer_photo' => ['required', 'image', 'max:5120'],
            'selfie_photo' => ['required', 'image', 'max:5120'],
            'client_event_id' => ['nullable', 'uuid'],
            'shift_id' => ['nullable', 'integer'],
        ];
    }
}
