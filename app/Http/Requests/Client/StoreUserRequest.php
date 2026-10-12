<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\ClientAdminOrigin;
use App\Http\Requests\Concerns\ValidatesManagedUser;
use App\Models\User;
use App\Support\Auth\AssignableRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    use ValidatesManagedUser;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'document_number' => ['required', 'string', 'max:30'],
            'job_title' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::in(AssignableRoles::forClient())],
            'password' => ['required', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'installation_ids' => [
                Rule::requiredIf(fn () => AssignableRoles::isInstallationAdmin((string) $this->input('role'))),
                'array',
            ],
            'installation_ids.*' => ['integer', 'exists:installations,id'],
            'installation_permission' => ['nullable', 'in:admin,support'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'document_number' => 'cédula',
            'job_title' => 'cargo',
            'installation_ids' => 'instalaciones',
        ];
    }

    public function origin(): ClientAdminOrigin
    {
        return ClientAdminOrigin::External;
    }
}
