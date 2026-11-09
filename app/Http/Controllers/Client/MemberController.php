<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domain\Structure\Data\CreateMemberData;
use App\Exports\MembersAssemblyExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreMemberRequest;
use App\Models\IdentityDocumentType;
use App\Models\MemberType;
use App\Models\Structure;
use App\Models\StructureMember;
use App\Repositories\StructureMemberRepository;
use App\Repositories\StructureRepository;
use App\Services\Structure\CreateMemberService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MemberController extends Controller
{
    public function __construct(
        private readonly StructureMemberRepository $memberRepository,
        private readonly StructureRepository $structureRepository,
        private readonly CreateMemberService $createMemberService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StructureMember::class);

        $clientId = (int) $this->tenantContext->clientId();
        $allowed = $this->tenantContext->installationIds();
        $picker = $this->structureRepository->censusPickerData($clientId, $allowed);
        $installationId = $request->integer('installation_id') ?: null;
        $structureId = $request->integer('structure_id') ?: null;

        $members = $this->memberRepository->paginateForClient(
            $clientId,
            $request->string('q')->toString() ?: null,
            $structureId,
            $installationId,
            $allowed,
        );

        return view('modules.client.members.index', [
            'members' => $members,
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'installationId' => $installationId,
            'structureId' => $structureId,
            'memberTypes' => MemberType::query()->withCount('members')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StructureMember::class);

        $clientId = (int) $this->tenantContext->clientId();
        $picker = $this->structureRepository->censusPickerData($clientId, $this->tenantContext->installationIds());
        $memberTypes = MemberType::query()->active()->get();
        $structureId = old('structure_id');
        $installationId = $this->installationIdForStructure($picker, $structureId !== null ? (int) $structureId : null);

        return view('modules.client.members.create', [
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'memberTypes' => $memberTypes,
            'documentTypes' => IdentityDocumentType::optionsForSelect(),
            'installationId' => $installationId,
            'structureId' => $structureId,
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $clientId = (int) $this->tenantContext->clientId();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('members', 'public');
        }

        $member = $this->createMemberService->execute(new CreateMemberData(
            clientId: $clientId,
            structureId: (int) $request->validated('structure_id'),
            memberTypeId: (int) $request->validated('member_type_id'),
            firstName: $request->validated('first_name'),
            lastName: $request->validated('last_name'),
            documentType: $request->validated('document_type'),
            documentNumber: $request->validated('document_number'),
            birthDate: $request->validated('birth_date'),
            phonePrimary: $request->validated('phone_primary'),
            phoneSecondary: $request->validated('phone_secondary'),
            email: $request->validated('email'),
            hasAppAccess: false,
            isActive: $request->boolean('is_active', true),
            photoPath: $photoPath,
            minorTreatmentAcceptedAt: $request->minorAcceptedAt(),
        ));

        return redirect()
            ->route('client.members.show', $member)
            ->with('success', 'Persona registrada en el censo.');
    }

    public function show(StructureMember $member): View
    {
        $this->authorize('view', $member);

        $member->load(['structure.installation', 'memberType', 'appUser']);

        return view('modules.client.members.show', compact('member'));
    }

    public function edit(StructureMember $member): View
    {
        $this->authorize('update', $member);
        $member->loadMissing('appUser');

        $clientId = (int) $this->tenantContext->clientId();
        $picker = $this->structureRepository->censusPickerData($clientId, $this->tenantContext->installationIds());
        $memberTypes = MemberType::query()
            ->where(function ($q) use ($member): void {
                $q->where('is_active', true)
                    ->orWhere('id', (int) $member->member_type_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $structureId = old('structure_id', $member->structure_id);
        $installationId = $this->installationIdForStructure(
            $picker,
            $structureId !== null ? (int) $structureId : null,
        );

        return view('modules.client.members.edit', [
            'member' => $member,
            'installations' => $picker['installations'],
            'nodeOptions' => $picker['nodeOptions'],
            'memberTypes' => $memberTypes,
            'documentTypes' => IdentityDocumentType::optionsForSelect(),
            'installationId' => $installationId,
            'structureId' => $structureId,
        ]);
    }

    public function update(StoreMemberRequest $request, StructureMember $member): RedirectResponse
    {
        $this->authorize('update', $member);

        $data = [
            'structure_id' => (int) $request->validated('structure_id'),
            'member_type_id' => (int) $request->validated('member_type_id'),
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'document_type' => $request->validated('document_type'),
            'document_number' => $request->validated('document_number'),
            'birth_date' => $request->validated('birth_date'),
            'minor_treatment_accepted_at' => $request->isMinor() ? $request->minorAcceptedAt() : null,
            'phone_primary' => $request->validated('phone_primary'),
            'phone_secondary' => $request->validated('phone_secondary'),
            'email' => $request->validated('email'),
            'has_app_access' => $request->isMinor() ? false : (bool) $member->has_app_access,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('members', 'public');
        }

        $member->update($data);

        return redirect()
            ->route('client.members.show', $member)
            ->with('success', 'Persona actualizada en el censo.');
    }

    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', StructureMember::class);

        $clientId = (int) $this->tenantContext->clientId();

        return Excel::download(
            new MembersAssemblyExport($clientId),
            'listado-miembros-asamblea.xlsx',
        );
    }

    /** @param array{installations: mixed, nodeOptions: array<string, list<array{id: int, name: string, depth: int}>>} $picker */
    private function installationIdForStructure(array $picker, ?int $structureId): ?int
    {
        if ($structureId === null) {
            return null;
        }

        foreach ($picker['nodeOptions'] as $installationId => $nodes) {
            foreach ($nodes as $node) {
                if ($node['id'] === $structureId) {
                    return (int) $installationId;
                }
            }
        }

        $structure = Structure::query()->find($structureId);

        return $structure?->installation_id !== null ? (int) $structure->installation_id : null;
    }
}
