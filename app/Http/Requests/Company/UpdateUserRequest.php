<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

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
        $external = $user->admin_origin === 'external'
            || $user->hasRole('client-installation-admin');

        $rules = array_merge(
            $this->baseUserRules(false),
            $this->roleRule(AssignableRoles::forCompanyActor($this->user())),
            $this->clientIdsRule(),
            [
                'job_title' => ['required', 'string', 'max:80'],
                'installation_ids' => ['nullable', 'array'],
                'installation_ids.*' => ['integer', 'exists:installations,id'],
                'installation_permission' => ['nullable', 'in:admin,support'],
                'grants' => ['nullable', 'array'],
                'grants.company' => ['nullable', 'array'],
                'grants.company.*' => ['nullable', 'in:none,view,manage'],
                'grants.client' => ['nullable', 'array'],
                'grants.client.*' => ['nullable', 'array'],
                'grants.client.*.*' => ['nullable', 'in:none,view,manage'],
                'grants.installation' => ['nullable', 'array'],
                'grants.installation.*' => ['nullable', 'array'],
                'grants.installation.*.*' => ['nullable', 'in:none,view,manage'],
            ],
        );

        if ($external) {
            $rules['name'] = ['required', 'string', 'max:120'];
            $rules['document_number'] = ['required', 'string', 'max:30'];
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ];
        } else {
            $rules['name'] = ['nullable', 'string', 'max:120'];
        }

        return $rules;
    }
}
