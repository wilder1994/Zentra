<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Enums\ClientAdminOrigin;
use App\Http\Requests\Concerns\ValidatesManagedUser;
use App\Models\Employee;
use App\Models\User;
use App\Support\Auth\AssignableRoles;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
        $companyId = app(ActingCompanyResolver::class)->requireId($this->user());
        $external = $this->isExternalPayload();

        $rules = array_merge(
            $this->roleRule(AssignableRoles::forCompanyActor($this->user())),
            $this->clientIdsRule(),
            [
                'origin' => ['nullable', 'string', Rule::enum(ClientAdminOrigin::class)],
                'corporate_email' => ['nullable', 'email', 'max:255'],
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
                'username' => [
                    'required',
                    'string',
                    'max:64',
                    'regex:/^[a-z]+\.[a-z]+\.\d{4}$/',
                    Rule::unique('users', 'username'),
                ],
                'password' => ['required', 'confirmed', Password::defaults()],
                'is_active' => ['sometimes', 'boolean'],
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
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['job_title'] = ['required', 'string', 'max:80'];
            $rules['employee_id'] = ['nullable'];
        } else {
            $rules['employee_id'] = ['required', 'integer', 'exists:employees,id'];
            $rules['job_title'] = [
                'required',
                'string',
                'max:80',
                Rule::exists('company_job_titles', 'name')->where('security_company_id', $companyId),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $role = (string) $this->input('role');
            $origin = $this->input('origin');

            if (AssignableRoles::isClientFacingAdmin($role) && ! AssignableRoles::isInstallationAdmin($role) && ! filled($origin)) {
                $validator->errors()->add('origin', 'Indica si el administrador es interno o externo.');
            }

            if (! $this->isExternalPayload()) {
                $this->assertEmployee($validator);
            }
        });
    }

    public function isExternalPayload(): bool
    {
        return AssignableRoles::isExternalClientAdmin(
            (string) $this->input('role'),
            $this->input('origin') !== null ? (string) $this->input('origin') : null,
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_id' => 'empleado',
            'username' => 'usuario de acceso',
            'job_title' => 'cargo',
            'role' => 'rol',
            'client_ids' => 'cliente',
            'origin' => 'origen',
            'document_number' => 'cédula',
            'installation_ids' => 'instalaciones',
            'corporate_email' => 'correo corporativo',
        ];
    }

    private function assertEmployee(Validator $validator): void
    {
        $employeeId = (int) $this->input('employee_id');
        if ($employeeId === 0) {
            return;
        }

        $companyId = app(ActingCompanyResolver::class)->requireId($this->user());
        $employee = Employee::query()->find($employeeId);

        if ($employee === null || (int) $employee->security_company_id !== $companyId) {
            $validator->errors()->add('employee_id', 'Seleccione un empleado de esta empresa.');

            return;
        }

        if (! $employee->is_active || $employee->ceased_at !== null) {
            $validator->errors()->add('employee_id', 'El empleado no está activo.');
        }

        if ($employee->user()->exists()) {
            $validator->errors()->add('employee_id', 'Este empleado ya tiene un usuario de acceso.');
        }
    }
}
