<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\AuthorizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.authorizations.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $guests = collect($this->input('guests', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->except('document_type')->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())
            ->values()
            ->all();

        $this->merge([
            'guests' => $guests,
            'multi_entry' => $this->boolean('multi_entry'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AuthorizationType::class)],
            'destination_mode' => ['required', Rule::in(['unit', 'zone'])],
            'installation_id' => ['nullable', 'integer'],
            'structure_id' => ['nullable', 'required_if:destination_mode,unit', 'integer'],
            'destination_text' => ['nullable', 'required_if:destination_mode,zone', 'string', 'max:150'],
            'member_id' => ['nullable', 'integer'],
            'authorized_text' => ['nullable', 'string', 'max:150'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'entry_time_from' => ['nullable', 'date_format:H:i', 'required_with:entry_time_until'],
            'entry_time_until' => ['nullable', 'date_format:H:i', 'required_with:entry_time_from', 'after:entry_time_from'],
            'multi_entry' => ['boolean'],
            'vehicle_plate' => ['nullable', 'string', 'max:15'],
            'notes' => ['nullable', 'string', 'max:500'],
            'guests' => ['required', 'array', 'min:1', 'max:300'],
            'guests.*.document_type' => ['nullable', 'string', 'max:10'],
            'guests.*.document_number' => ['required', 'string', 'max:30'],
            'guests.*.first_name' => ['required', 'string', 'max:100'],
            'guests.*.last_name' => ['required', 'string', 'max:100'],
            'guests.*.phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'structure_id.required_if' => 'Elige la unidad de destino.',
            'destination_text.required_if' => 'Escribe la zona común de destino.',
            'guests.required' => 'Agrega al menos una persona.',
            'guests.*.document_number.required' => 'Cada persona necesita su documento.',
            'guests.*.first_name.required' => 'Cada persona necesita su nombre.',
            'guests.*.last_name.required' => 'Cada persona necesita su apellido.',
            'entry_time_until.after' => 'La hora final debe ser posterior a la inicial.',
            'valid_until.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
