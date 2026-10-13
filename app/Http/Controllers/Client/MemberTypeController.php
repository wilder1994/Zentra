<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreMemberTypeRequest;
use App\Http\Requests\Client\UpdateMemberTypeRequest;
use App\Models\MemberType;
use App\Services\Client\ManageClientMemberTypeService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class MemberTypeController extends Controller
{
    public function __construct(
        private readonly ManageClientMemberTypeService $manageClientMemberTypeService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('client.members.index');
    }

    public function store(StoreMemberTypeRequest $request): RedirectResponse|JsonResponse
    {
        $type = $this->manageClientMemberTypeService->create(
            (int) $this->tenantContext->clientId(),
            [
                'name' => $request->validated('name'),
                'is_active' => $request->boolean('is_active', true),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['id' => $type->id, 'name' => $type->name], 201);
        }

        return redirect()
            ->route('client.members.index')
            ->with('success', 'Tipo de persona creado.');
    }

    public function update(UpdateMemberTypeRequest $request, MemberType $memberType): RedirectResponse
    {
        $this->authorize('update', $memberType);

        $this->manageClientMemberTypeService->update($memberType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('client.members.index')
            ->with('success', 'Tipo de persona actualizado.');
    }

    public function destroy(MemberType $memberType): RedirectResponse
    {
        $this->authorize('delete', $memberType);

        try {
            $this->manageClientMemberTypeService->delete($memberType);
        } catch (ValidationException $e) {
            return redirect()
                ->route('client.members.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo eliminar el tipo.');
        }

        return redirect()
            ->route('client.members.index')
            ->with('success', 'Tipo de persona eliminado.');
    }
}
