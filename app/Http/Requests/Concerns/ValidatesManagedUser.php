<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Auth\AssignableRoles;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait ValidatesManagedUser
{
    /** @return array<string, mixed> */
    protected function baseUserRules(bool $passwordRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
            'corporate_email' => ['nullable', 'email', 'max:255'],
            'password' => $passwordRequired
                ? ['required', 'confirmed', Password::defaults()]
                : ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'regenerate_supervisor_code' => ['sometimes', 'boolean'],
            'installation_permission' => ['nullable', 'in:admin,support'],
        ];
    }

    /** @return array<string, mixed> */
    protected function roleRule(array $allowedRoles): array
    {
        return [
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }

    /** @return array<string, mixed> */
    protected function clientIdsRule(): array
    {
        return [
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
        ];
    }

    protected function roleRequiresClients(?string $role): bool
    {
        return $role !== null && in_array($role, AssignableRoles::requiringClientAssignment(), true);
    }
}
