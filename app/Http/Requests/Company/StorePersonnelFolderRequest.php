<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

final class StorePersonnelFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.settings.manage') || $this->user()?->hasRole('super-admin') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
