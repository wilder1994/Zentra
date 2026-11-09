<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorDocumentType;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorDocumentTypeController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorDocumentType::class);

        return SettingsCatalog::hubRedirect('documentos');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createDocumentType($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-document-types.index')->with('success', 'Tipo de documento creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorDocumentType $documentType): RedirectResponse
    {
        abort_unless((int) $documentType->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateDocumentType($documentType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-document-types.index')->with('success', 'Tipo de documento actualizado.');
    }

    public function destroy(Request $request, SupervisorDocumentType $documentType): RedirectResponse
    {
        abort_unless((int) $documentType->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteDocumentType($documentType);

        return redirect()->route('company.supervision-document-types.index')->with('success', 'Tipo de documento eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
