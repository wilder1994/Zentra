<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Models\StructureType;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompanyStructureTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('structureType');

        return $type instanceof StructureType
            && ($this->user()?->can('update', $type) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre'];
    }
};
