<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorRiskType;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorRiskTypeController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorRiskType::class);

        return SettingsCatalog::hubRedirect('riesgos');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createRiskType($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-risk-types.index')->with('success', 'Tipo de riesgo creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorRiskType $riskType): RedirectResponse
    {
        abort_unless((int) $riskType->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateRiskType($riskType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-risk-types.index')->with('success', 'Tipo de riesgo actualizado.');
    }

    public function destroy(Request $request, SupervisorRiskType $riskType): RedirectResponse
    {
        abort_unless((int) $riskType->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteRiskType($riskType);

        return redirect()->route('company.supervision-risk-types.index')->with('success', 'Tipo de riesgo eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
