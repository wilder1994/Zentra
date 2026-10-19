<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\User\CreateUserData;
use App\Domain\User\UpdateUserData;
use App\Enums\ClientAdminOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreUserRequest;
use App\Http\Requests\Company\UpdateUserRequest;
use App\Models\Client;
use App\Models\CompanyJobTitle;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\User;
use App\Repositories\EmployeeRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AllocateLoginUsername;
use App\Services\Company\GrantEmployeeAccessService;
use App\Services\User\ManageScopedUserService;
use App\Services\User\ParseAccessGrants;
use App\Support\Auth\AssignableRoles;
use App\Support\Auth\UserManagementContext;
use App\Support\Platform\ActingCompanyResolver;
use App\Support\User\UserAvatarUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly ManageScopedUserService $manageUserService,
        private readonly GrantEmployeeAccessService $grantEmployeeAccessService,
        private readonly AllocateLoginUsername $usernames,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $users = $this->userRepository->paginateScoped(
            $request->user(),
            15,
            $search !== '' ? $search : null,
            $status,
        );

        return view('modules.company.users.index', compact('users', 'search', 'status'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $clients = Client::query()
            ->where('security_company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);
        $jobTitles = CompanyJobTitle::query()
            ->where('security_company_id', $companyId)
            ->active()
            ->get(['id', 'name']);

        return view('modules.company.users.create', [
            'roleOptions' => AssignableRoles::forCompanyActor($request->user()),
            'clients' => $clients,
            'jobTitles' => $jobTitles,
        ]);
    }

    public function searchEmployees(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());

        return response()->json([
            'employees' => $this->employeeRepository->searchWithoutUser(
                $companyId,
                $request->string('q')->toString(),
            ),
        ]);
    }

    public function previewCredentials(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());

        if ($request->filled('employee_id')) {
            $employee = Employee::query()->findOrFail((int) $request->input('employee_id'));
            abort_unless((int) $employee->security_company_id === $companyId, 404);

            return response()->json($this->grantEmployeeAccessService->previewCredentials($employee));
        }

        $name = $request->string('name')->trim()->toString();
        abort_unless($name !== '', 422, 'Indica el nombre para generar el usuario.');

        return response()->json([
            'username' => $this->usernames->forFullName($name),
            'password' => $this->usernames->randomPassword(),
        ]);
    }

    public function installations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $clientId = $request->integer('client_id');
        $client = Client::query()
            ->where('security_company_id', $companyId)
            ->whereKey($clientId)
            ->firstOrFail();

        $rows = Installation::query()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderByDesc('is_client_site')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'installations' => $rows->map(fn (Installation $row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
            ])->all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        if ($request->isExternalPayload()) {
            $user = $this->createExternal($request, $password);
        } else {
            $employee = Employee::query()->findOrFail((int) $request->validated('employee_id'));
            $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
            $user = $this->grantEmployeeAccessService->execute(
                $employee,
                $request->user(),
                $request->validated('role'),
                $password,
                array_map('intval', $request->input('client_ids', [])),
                $request->validated('username'),
                $request->validated('job_title'),
                app(ParseAccessGrants::class)->fromInput($request->input('grants', []), $companyId),
            );
        }

        if ($request->hasFile('avatar')) {
            $path = UserAvatarUploader::store($request->file('avatar'));
            if ($path !== null) {
                $user->update(['avatar_path' => $path]);
            }
        }
        $this->syncCorporateEmail($user, $request->validated('corporate_email'));

        $message = 'Usuario creado.';
        if ($user->supervisor_code) {
            $message .= ' Código de 6 dígitos (revista Accesos): '.$user->supervisor_code;
        }

        return redirect()
            ->route('company.users.edit', $user)
            ->with([
                'success' => $message,
                'issued_login' => $user->username,
                'issued_password' => $password,
            ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        $user->load(['roles', 'clients', 'employee.jobTitle', 'assignedInstallations', 'moduleGrants']);
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $clients = Client::query()
            ->where('security_company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);
        $jobTitles = CompanyJobTitle::query()
            ->where('security_company_id', $companyId)
            ->active()
            ->get(['id', 'name']);

        return view('modules.company.users.edit', [
            'managedUser' => $user,
            'roleOptions' => AssignableRoles::forCompanyActor($request->user()),
            'clients' => $clients,
            'jobTitles' => $jobTitles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $external = $user->admin_origin === 'external' || $user->hasRole('client-installation-admin');

        $this->manageUserService->update(
            $user,
            new UpdateUserData(
                name: $external
                    ? (string) $request->validated('name')
                    : ($user->employee?->fullName() ?: $request->validated('name') ?: $user->name),
                email: $external ? $request->validated('email') : $user->email,
                password: $request->validated('password'),
                role: $request->validated('role'),
                clientIds: array_map('intval', $request->input('client_ids', [])),
                isActive: $request->boolean('is_active', true),
                jobTitle: $request->validated('job_title'),
                avatarPath: UserAvatarUploader::store($request->file('avatar')),
                regenerateSupervisorCode: $request->boolean('regenerate_supervisor_code'),
                documentNumber: $external ? $request->validated('document_number') : $user->document_number,
                installationIds: array_map('intval', $request->input('installation_ids', [])),
                installationPermission: $request->validated('installation_permission'),
                grants: app(ParseAccessGrants::class)->fromInput(
                    $request->input('grants', []),
                    app(ActingCompanyResolver::class)->requireId($request->user()),
                ),
            ),
            $request->user(),
            UserManagementContext::Company,
        );
        $this->syncCorporateEmail($user->fresh(), $request->validated('corporate_email'));

        return redirect()
            ->route('company.users.edit', $user)
            ->with('success', 'Usuario actualizado.');
    }

    /** Solo el admin de cliente interno (responsable) lleva correo corporativo. */
    private function syncCorporateEmail(User $user, mixed $email): void
    {
        $internal = $user->hasRole('client-admin') && $user->admin_origin !== 'external';
        $value = $internal && is_string($email) && trim($email) !== '' ? trim($email) : null;
        if ($user->corporate_email !== $value) {
            $user->forceFill(['corporate_email' => $value])->save();
        }
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->manageUserService->setActive($user, $request->user(), false);

        return redirect()
            ->route('company.users.index', ['status' => 'inactive'])
            ->with('success', 'Usuario desactivado. El historial se conserva.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->manageUserService->setActive($user, $request->user(), true);

        return redirect()
            ->route('company.users.index', ['status' => 'active'])
            ->with('success', 'Usuario reactivado.');
    }

    private function createExternal(StoreUserRequest $request, string $password): User
    {
        $origin = ClientAdminOrigin::External;
        $role = $request->validated('role');
        if (AssignableRoles::isInstallationAdmin($role)) {
            $origin = ClientAdminOrigin::External;
        }

        return $this->manageUserService->create(
            new CreateUserData(
                name: $request->validated('name'),
                username: $request->validated('username'),
                email: $request->validated('email'),
                password: $password,
                role: $role,
                securityCompanyId: null,
                clientIds: array_map('intval', $request->input('client_ids', [])),
                isActive: $request->boolean('is_active', true),
                jobTitle: $request->validated('job_title'),
                avatarPath: UserAvatarUploader::store($request->file('avatar')),
                mustChangePassword: true,
                adminOrigin: $origin,
                documentNumber: $request->validated('document_number'),
                installationIds: array_map('intval', $request->input('installation_ids', [])),
                installationPermission: (string) ($request->validated('installation_permission') ?: 'admin'),
                grants: app(ParseAccessGrants::class)->fromInput(
                    $request->input('grants', []),
                    app(ActingCompanyResolver::class)->requireId($request->user()),
                ),
            ),
            $request->user(),
            UserManagementContext::Company,
        );
    }
}
