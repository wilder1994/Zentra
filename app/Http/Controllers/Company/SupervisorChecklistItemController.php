<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Enums\SupervisorChecklistKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorNamedCatalogRequest;
use App\Models\SupervisorChecklistItem;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorChecklistItemController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorChecklistItem::class);

        return SettingsCatalog::hubRedirect('preop');
    }


    public function store(StoreSupervisorNamedCatalogRequest $request): RedirectResponse
    {
        $kind = SupervisorChecklistKind::from((string) $request->input('kind'));
        $this->catalog->createItem($this->companyId($request), $kind, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-preop.index')->with('success', 'Ítem creado.');
    }

    public function update(StoreSupervisorNamedCatalogRequest $request, SupervisorChecklistItem $item): RedirectResponse
    {
        abort_unless((int) $item->security_company_id === $this->companyId($request), 404);
        $this->catalog->updateItem($item, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-preop.index')->with('success', 'Ítem actualizado.');
    }

    public function destroy(Request $request, SupervisorChecklistItem $item): RedirectResponse
    {
        abort_unless((int) $item->security_company_id === $this->companyId($request), 404);
        $this->catalog->deleteItem($item);

        return redirect()->route('company.supervision-preop.index')->with('success', 'Ítem eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
