<?php

declare(strict_types=1);

namespace App\Http\Requests\Observatory;

use App\Enums\ObservatoryReporterRole;
use App\Support\Observatory\ObservatoryMediaLimits;
use App\Support\Observatory\ObservatoryPhotoInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StorePublicObservatoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $anonymous = $this->boolean('is_anonymous');
        $asksAge = $this->asksAge();

        return [
            'installation_id' => ['required', 'integer'],
            'kind' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'min:'.ObservatoryMediaLimits::MIN_BODY, 'max:2000'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'is_adult' => [$asksAge ? 'required' : 'prohibited', 'boolean'],
            'minor_treatment_accepted' => [
                Rule::when($asksAge, ['required', 'accepted'], ['nullable']),
            ],
            'reporter_role' => ['required', 'string', Rule::in(array_keys(ObservatoryReporterRole::publicOptions()))],
            'reporter_name' => [$anonymous ? 'nullable' : 'required', 'string', 'max:120'],
            'reporter_phone' => ['nullable', 'string', 'max:30'],
            ...ObservatoryPhotoInput::linkRules(),
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'installation_id' => 'colegio',
            'kind' => 'riesgo endógeno',
            'body' => 'qué pasó',
            'reporter_role' => 'quién eres',
            'reporter_name' => 'nombre',
            'reporter_phone' => 'teléfono',
            ...ObservatoryPhotoInput::attributes(),
            'latitude' => 'latitud',
            'longitude' => 'longitud',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.min' => 'Cuéntanos qué pasó, aunque sea una palabra.',
            'minor_treatment_accepted.accepted' => 'Marca la autorización para enviar el reporte.',
            ...ObservatoryPhotoInput::linkMessages(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->asksAge() && ! $this->boolean('is_adult') && $this->boolean('is_anonymous')) {
                $validator->errors()->add('is_anonymous', 'Un menor no puede reportar en anónimo.');
            }
        });
    }

    private function asksAge(): bool
    {
        return in_array((string) $this->input('reporter_role'), [
            ObservatoryReporterRole::Alumno->value,
            ObservatoryReporterRole::Vecino->value,
        ], true);
    }
}
