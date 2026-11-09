<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorAlarmType;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorAlarmTypeController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorAlarmType::class);

        return SettingsCatalog::hubRedirect('alarmas');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $this->catalog->createAlarmType($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-alarm-types.index')->with('success', 'Tipo de alarma creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorAlarmType $alarmType): RedirectResponse
    {
        abort_unless((int) $alarmType->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateAlarmType($alarmType, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-alarm-types.index')->with('success', 'Tipo de alarma actualizado.');
    }

    public function destroy(Request $request, SupervisorAlarmType $alarmType): RedirectResponse
    {
        abort_unless((int) $alarmType->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteAlarmType($alarmType);

        return redirect()->route('company.supervision-alarm-types.index')->with('success', 'Tipo de alarma eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
