<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyCollaboratorTypeRequest;
use App\Http\Requests\Company\UpdateCompanyCollaboratorTypeRequest;
use App\Models\CompanyCollaboratorType;
use App\Services\Company\ManageCompanyCollaboratorTypeService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CollaboratorTypeController extends Controller
{
    public function __construct(
        private readonly ManageCompanyCollaboratorTypeService $manageCompanyCollaboratorTypeService,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', CompanyCollaboratorType::class);

        return SettingsCatalog::hubRedirect('tipos');
    }


    public function store(StoreCompanyCollaboratorTypeRequest $request): RedirectResponse
    {
        $this->manageCompanyCollaboratorTypeService->create($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('company.collaborator-types.index')
            ->with('success', 'Tipo de colaborador creado.');
    }

    public function update(UpdateCompanyCollaboratorTypeRequest $request, CompanyCollaboratorType $collaboratorType): RedirectResponse
    {
        $this->assertCompany($request, $collaboratorType);
        $this->manageCompanyCollaboratorTypeService->update($collaboratorType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('company.collaborator-types.index')
            ->with('success', 'Tipo de colaborador actualizado.');
    }

    public function destroy(Request $request, CompanyCollaboratorType $collaboratorType): RedirectResponse
    {
        $this->assertCompany($request, $collaboratorType);
        $this->authorize('delete', $collaboratorType);

        try {
            $this->manageCompanyCollaboratorTypeService->delete($collaboratorType);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.collaborator-types.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo eliminar el tipo.');
        }

        return redirect()
            ->route('company.collaborator-types.index')
            ->with('success', 'Tipo de colaborador eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    private function assertCompany(Request $request, CompanyCollaboratorType $collaboratorType): void
    {
        abort_unless((int) $collaboratorType->security_company_id === $this->companyId($request), 404);
    }
}
