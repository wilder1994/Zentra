<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domain\Structure\Data\CreateStructureAppUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreAppUserRequest;
use App\Http\Requests\Client\UpdateAppUserRequest;
use App\Models\Client;
use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Repositories\StructureAppUserRepository;
use App\Repositories\StructureRepository;
use App\Services\Structure\CreateStructureAppUserService;
use App\Services\Structure\ResendStructureAppUserCredentialsService;
use App\Services\Structure\UpdateStructureAppUserService;
use App\Support\Resident\ResidentAppUrl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AppUserController extends Controller
{
    public function __construct(
        private readonly StructureAppUserRepository $appUserRepository,
        private readonly StructureRepository $structureRepository,
        private readonly CreateStructureAppUserService $createStructureAppUserService,
        private readonly UpdateStructureAppUserService $updateStructureAppUserService,
        private readonly ResendStructureAppUserCredentialsService $resendCredentialsService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->can('client.app_users.manage'), 403);

        $clientId = (int) $this->tenantContext->clientId();
        $includeArchived = $request->boolean('archived');
        $appUsers = $this->appUserRepository->paginateForClient($clientId, includeArchived: $includeArchived);
        $client = Client::query()->find($clientId);

        return view('modules.client.app-users.index', [
            'appUsers' => $appUsers,
            'client' => $client,
            'includeArchived' => $includeArchived,
            'maxPerStructure' => ResidentAppUrl::maxUsersPerStructure(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(auth()->user()?->can('client.app_users.manage'), 403);

        $clientId = (int) $this->tenantContext->clientId();
        $allowed = $this->tenantContext->installationIds();
        $picker = $this->structureRepository->censusPickerData($clientId, $allowed);
        $client = Client::query()->find($clientId);

        $takenMemberIds = StructureAppUser::withTrashed()
            ->where('client_id', $clientId)
            ->whereNotNull('member_id')
            ->pluck('member_id')
            ->all();

        $membersQuery = StructureMember::query()
            ->shareable()
            ->with(['structure'])
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->when($takenMemberIds !== [], fn ($q) => $q->whereNotIn('id', $takenMemberIds))
            ->orderBy('last_name')
            ->orderBy('first_name');
        if ($allowed !== null) {
            $membersQuery->whereHas('structure', fn ($q) => $q->whereIn('installation_id', $allowed));
        }

        $membersByStructure = [];
        foreach ($membersQuery->get() as $member) {
            $sid = (string) $member->structure_id;
            $membersByStructure[$sid][] = [
                'id' => (int) $member->id,
                'name' => $member->full_name,
                'email' => (string) ($member->email ?? ''),
            ];
        }

        $prefillMember = null;
        $selectedMemberId = $request->integer('member_id') ?: null;
        if ($selectedMemberId) {
            $prefillMember = StructureMember::query()
                ->with('structure.installation')
                ->whereKey($selectedMemberId)
                ->where('client_id', $clientId)
                ->first();
        }

        return view('modules.client.app-users.create', [
            'client' => $client,
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'membersByStructure' => $membersByStructure,
            'prefillMember' => $prefillMember,
            'maxPerStructure' => ResidentAppUrl::maxUsersPerStructure(),
        ]);
    }

    public function store(StoreAppUserRequest $request): RedirectResponse
    {
        $clientId = (int) $this->tenantContext->clientId();

        $result = $this->createStructureAppUserService->execute(new CreateStructureAppUserData(
            clientId: $clientId,
            memberId: (int) $request->validated('member_id'),
            deliveryChannel: (string) $request->validated('delivery_channel'),
            actorUserId: auth()->id(),
        ));

        return redirect()
            ->route('client.app-users.show', $result['appUser'])
            ->with('success', 'Usuario app creado. Credenciales enviadas al correo de la persona.');
    }

    public function show(StructureAppUser $appUser): View
    {
        $this->assertOwned($appUser);
        abort_unless(auth()->user()?->can('client.app_users.manage'), 403);

        $appUser->load(['member.structure.installation', 'client', 'events.actor']);

        return view('modules.client.app-users.show', [
            'appUser' => $appUser,
            'events' => $appUser->events()->with('actor')->limit(50)->get(),
        ]);
    }

    public function edit(StructureAppUser $appUser): View
    {
        $this->assertOwned($appUser);
        abort_unless(auth()->user()?->can('client.app_users.manage'), 403);
        abort_if($appUser->trashed(), 404);

        $appUser->load(['member.structure.installation', 'client']);

        return view('modules.client.app-users.edit', compact('appUser'));
    }

    public function update(UpdateAppUserRequest $request, StructureAppUser $appUser): RedirectResponse
    {
        $this->assertOwned($appUser);
        abort_if($appUser->trashed(), 404);

        $this->updateStructureAppUserService->execute(
            $appUser,
            (string) $request->validated('delivery_channel'),
            (bool) $request->validated('is_active'),
            auth()->id(),
        );

        return redirect()
            ->route('client.app-users.show', $appUser)
            ->with('success', 'Usuario app actualizado.');
    }

    public function destroy(StructureAppUser $appUser): RedirectResponse
    {
        $this->assertOwned($appUser);
        abort_unless(auth()->user()?->can('client.app_users.manage'), 403);
        abort_if($appUser->trashed(), 404);

        $this->updateStructureAppUserService->archive($appUser, auth()->id());

        return redirect()
            ->route('client.app-users.index')
            ->with('success', 'Usuario app archivado.');
    }

    public function resend(StructureAppUser $appUser): RedirectResponse
    {
        $this->assertOwned($appUser);
        abort_unless(auth()->user()?->can('client.app_users.manage'), 403);
        abort_if($appUser->trashed(), 404);

        $this->resendCredentialsService->execute($appUser, auth()->id());

        return redirect()
            ->route('client.app-users.show', $appUser)
            ->with('success', 'Nueva contraseña enviada al correo.');
    }

    private function assertOwned(StructureAppUser $appUser): void
    {
        abort_unless((int) $appUser->client_id === (int) $this->tenantContext->clientId(), 404);

        $allowed = $this->tenantContext->installationIds();
        if ($allowed === null) {
            return;
        }

        $installationId = (int) ($appUser->member?->structure?->installation_id
            ?? $appUser->loadMissing('member.structure')->member?->structure?->installation_id);
        abort_unless($installationId > 0 && in_array($installationId, $allowed, true), 403);
    }
}
