<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\SupervisorFieldModule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSupervisorFieldLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('supervisor') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'module' => ['required', Rule::enum(SupervisorFieldModule::class)],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'supervisor_shift_review_id' => ['nullable', 'integer', 'exists:supervisor_shift_reviews,id'],
            'payload' => ['required', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'client_event_id' => ['nullable', 'uuid'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $module = SupervisorFieldModule::tryFrom((string) $this->input('module'));
            if ($module === null) {
                return;
            }

            if ($module->requiresClient() && $this->input('client_id') === null) {
                $validator->errors()->add('client_id', 'Este módulo requiere un sitio con Supervisión.');
            }

            if ($module->requiresGps() && ($this->input('latitude') === null || $this->input('longitude') === null)) {
                $validator->errors()->add('latitude', 'Active la ubicación del dispositivo para registrar este módulo.');
            }
        });
    }
}
