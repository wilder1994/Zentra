<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorWeaponBrand;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorWeaponBrandController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorWeaponBrand::class);

        return SettingsCatalog::hubRedirect('marcas');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createWeaponBrand($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-weapon-brands.index')->with('success', 'Marca creada.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorWeaponBrand $weaponBrand): RedirectResponse
    {
        abort_unless((int) $weaponBrand->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateWeaponBrand($weaponBrand, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-weapon-brands.index')->with('success', 'Marca actualizada.');
    }

    public function destroy(Request $request, SupervisorWeaponBrand $weaponBrand): RedirectResponse
    {
        abort_unless((int) $weaponBrand->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteWeaponBrand($weaponBrand);

        return redirect()->route('company.supervision-weapon-brands.index')->with('success', 'Marca eliminada.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
