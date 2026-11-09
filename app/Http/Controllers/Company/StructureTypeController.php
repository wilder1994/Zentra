<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyStructureTypeRequest;
use App\Http\Requests\Company\UpdateCompanyStructureTypeRequest;
use App\Models\StructureType;
use App\Services\Company\ManageCompanyStructureTypeService;
use App\Support\Company\SettingsCatalog;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class StructureTypeController extends Controller
{
    public function __construct(
        private readonly ManageCompanyStructureTypeService $manageCompanyStructureTypeService,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', StructureType::class);

        return SettingsCatalog::hubRedirect('estructuras');
    }

    public function store(StoreCompanyStructureTypeRequest $request): RedirectResponse
    {
        $this->manageCompanyStructureTypeService->create($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('company.structure-types.index')
            ->with('success', 'Tipo de estructura creado.');
    }

    public function update(UpdateCompanyStructureTypeRequest $request, StructureType $structureType): RedirectResponse
    {
        $this->assertCompany($request, $structureType);
        $this->manageCompanyStructureTypeService->update($structureType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('company.structure-types.index')
            ->with('success', 'Tipo de estructura actualizado.');
    }

    public function destroy(Request $request, StructureType $structureType): RedirectResponse
    {
        $this->assertCompany($request, $structureType);
        $this->authorize('delete', $structureType);

        try {
            $this->manageCompanyStructureTypeService->delete($structureType);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.structure-types.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo eliminar el tipo.');
        }

        return redirect()
            ->route('company.structure-types.index')
            ->with('success', 'Tipo de estructura eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    private function assertCompany(Request $request, StructureType $structureType): void
    {
        abort_unless((int) $structureType->security_company_id === $this->companyId($request), 404);
    }
};
