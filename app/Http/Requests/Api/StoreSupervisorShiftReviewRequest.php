<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\SupervisorFieldModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSupervisorShiftReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('supervisor') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('has_novelty')) {
            $this->merge([
                'has_novelty' => filter_var($this->input('has_novelty'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $logs = $this->input('logs');
        if (is_string($logs)) {
            $decoded = json_decode($logs, true);
            $this->merge(['logs' => is_array($decoded) ? $decoded : []]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'supervisor_post_id' => ['required', 'integer', 'exists:supervisor_posts,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'has_novelty' => ['required', 'boolean'],
            'guard_photo' => ['required', 'image', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'logs' => ['nullable', 'array'],
            'logs.*.module' => ['required', Rule::enum(SupervisorFieldModule::class)],
            'logs.*.payload' => ['required', 'array'],
            'log_photos' => ['nullable', 'array'],
            'log_photos.*.*' => ['image', 'max:5120'],
            'client_event_id' => ['nullable', 'uuid'],
            'recorded_at' => ['nullable', 'date'],
            'app_build' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Active la ubicación del dispositivo para guardar la revista.',
            'longitude.required' => 'Active la ubicación del dispositivo para guardar la revista.',
            'guard_photo.required' => 'La foto del vigilante es obligatoria.',
        ];
    }
}
