<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorWeaponType;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorWeaponTypeController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorWeaponType::class);

        return SettingsCatalog::hubRedirect('armas');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createWeaponType($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-weapon-types.index')->with('success', 'Tipo de arma creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorWeaponType $weaponType): RedirectResponse
    {
        abort_unless((int) $weaponType->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateWeaponType($weaponType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-weapon-types.index')->with('success', 'Tipo de arma actualizado.');
    }

    public function destroy(Request $request, SupervisorWeaponType $weaponType): RedirectResponse
    {
        abort_unless((int) $weaponType->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteWeaponType($weaponType);

        return redirect()->route('company.supervision-weapon-types.index')->with('success', 'Tipo de arma eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
