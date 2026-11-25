<?php

declare(strict_types=1);

namespace App\Http\Requests\Observatory;

use App\Support\Observatory\ObservatoryPhotoInput;
use Illuminate\Foundation\Http\FormRequest;

final class StorePanelObservatoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'integer'],
            'kind' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'is_anonymous' => ['sometimes', 'boolean'],
            ...ObservatoryPhotoInput::optionalRules(),
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
            ...ObservatoryPhotoInput::attributes(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ObservatoryPhotoInput::messages();
    }
}
