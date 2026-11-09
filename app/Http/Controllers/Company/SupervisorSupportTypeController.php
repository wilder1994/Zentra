<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorSupportType;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorSupportTypeController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorSupportType::class);

        return SettingsCatalog::hubRedirect('apoyos');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createSupportType($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-support-types.index')->with('success', 'Tipo de apoyo creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorSupportType $supportType): RedirectResponse
    {
        abort_unless((int) $supportType->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateSupportType($supportType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-support-types.index')->with('success', 'Tipo de apoyo actualizado.');
    }

    public function destroy(Request $request, SupervisorSupportType $supportType): RedirectResponse
    {
        abort_unless((int) $supportType->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteSupportType($supportType);

        return redirect()->route('company.supervision-support-types.index')->with('success', 'Tipo de apoyo eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
