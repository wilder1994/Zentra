<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorShiftTemplate;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorShiftTemplateController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorShiftTemplate::class);

        return SettingsCatalog::hubRedirect('turnos');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createTemplate($this->companyId($request), [
            'name' => $request->validated('name'),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-shifts.index')->with('success', 'Turno creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorShiftTemplate $template): RedirectResponse
    {
        abort_unless((int) $template->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateTemplate($template, [
            'name' => $request->validated('name'),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-shifts.index')->with('success', 'Turno actualizado.');
    }

    public function destroy(Request $request, SupervisorShiftTemplate $template): RedirectResponse
    {
        abort_unless((int) $template->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteTemplate($template);

        return redirect()->route('company.supervision-shifts.index')->with('success', 'Turno eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
