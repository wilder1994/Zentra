<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domain\User\CreateUserData;
use App\Domain\User\UpdateUserData;
use App\Enums\ClientAdminOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreUserRequest;
use App\Http\Requests\Client\UpdateUserRequest;
use App\Models\Installation;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Auth\AllocateLoginUsername;
use App\Services\User\ManageScopedUserService;
use App\Support\Auth\AssignableRoles;
use App\Support\Auth\UserManagementContext;
use App\Support\Tenancy\TenantContext;
use App\Support\User\UserAvatarUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ManageScopedUserService $manageUserService,
        private readonly AllocateLoginUsername $usernames,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $users = $this->userRepository->paginateClientPanel(
            $this->requireClientId(),
            15,
            $search !== '' ? $search : null,
            $status,
        );

        return view('modules.client.users.index', compact('users', 'search', 'status'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('modules.client.users.create', [
            'roleOptions' => AssignableRoles::forClient(),
            'installations' => $this->clientInstallations(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->manageUserService->create(
            new CreateUserData(
                name: $request->validated('name'),
                username: $this->usernames->forFullName($request->validated('name')),
                email: $request->validated('email'),
                password: $request->validated('password'),
                role: $request->validated('role'),
                securityCompanyId: null,
                clientIds: [$this->requireClientId()],
                isActive: $request->boolean('is_active', true),
                jobTitle: $request->validated('job_title'),
                avatarPath: UserAvatarUploader::store($request->file('avatar')),
                mustChangePassword: true,
                adminOrigin: ClientAdminOrigin::External,
                documentNumber: $request->validated('document_number'),
                installationIds: array_map('intval', $request->input('installation_ids', [])),
                installationPermission: (string) ($request->validated('installation_permission') ?: 'admin'),
            ),
            $request->user(),
            UserManagementContext::Client,
        );

        return redirect()
            ->route('client.users.edit', $user)
            ->with('success', 'Administrador externo creado.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);
        $this->assertClientPanelUser($user);

        $user->load(['roles', 'clients', 'assignedInstallations']);

        return view('modules.client.users.edit', [
            'managedUser' => $user,
            'roleOptions' => AssignableRoles::forClient(),
            'installations' => $this->clientInstallations(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->assertClientPanelUser($user);

        $this->manageUserService->update(
            $user,
            new UpdateUserData(
                name: $request->validated('name'),
                email: $request->validated('email'),
                password: $request->validated('password'),
                role: $request->validated('role'),
                clientIds: null,
                isActive: $request->boolean('is_active', true),
                jobTitle: $request->validated('job_title'),
                avatarPath: UserAvatarUploader::store($request->file('avatar')),
                documentNumber: $request->validated('document_number'),
                installationIds: array_map('intval', $request->input('installation_ids', [])),
                installationPermission: $request->validated('installation_permission'),
            ),
            $request->user(),
            UserManagementContext::Client,
        );

        return redirect()
            ->route('client.users.edit', $user)
            ->with('success', 'Usuario actualizado.');
    }

    private function requireClientId(): int
    {
        $clientId = (int) $this->tenantContext->clientId();
        abort_unless($clientId > 0, 403);

        return $clientId;
    }

    private function assertClientPanelUser(User $user): void
    {
        abort_unless($this->userRepository->isClientPanelUser($user, $this->requireClientId()), 403);
    }

    /** @return Collection<int, Installation> */
    private function clientInstallations(): Collection
    {
        return Installation::query()
            ->where('client_id', (int) $this->tenantContext->clientId())
            ->where('is_active', true)
            ->orderByDesc('is_client_site')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
