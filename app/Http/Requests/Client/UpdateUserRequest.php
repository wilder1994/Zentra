<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Http\Requests\Concerns\ValidatesManagedUser;
use App\Models\User;
use App\Support\Auth\AssignableRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    use ValidatesManagedUser;

    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return array_merge(
            $this->baseUserRules(false),
            [
                'name' => ['required', 'string', 'max:120'],
                'document_number' => ['required', 'string', 'max:30'],
                'job_title' => ['required', 'string', 'max:80'],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'role' => ['required', 'string', Rule::in(AssignableRoles::forClient())],
                'installation_ids' => [
                    Rule::requiredIf(fn () => AssignableRoles::isInstallationAdmin((string) $this->input('role'))),
                    'nullable',
                    'array',
                ],
                'installation_ids.*' => ['integer', 'exists:installations,id'],
                'installation_permission' => ['nullable', 'in:admin,support'],
            ],
        );
    }
}
