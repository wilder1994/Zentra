<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreSupervisorPostModalityRequest;
use App\Models\SupervisorPostModality;
use App\Services\Company\ManageSupervisorCompanyCatalogService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SupervisorPostModalityController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', SupervisorPostModality::class);

        return SettingsCatalog::hubRedirect('modalidades');
    }


    public function store(StoreSupervisorPostModalityRequest $request): RedirectResponse
    {
        $this->catalog->createPostModality($this->companyId($request), [
            'hours' => (int) $request->validated('hours'),
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('company.supervision-post-modalities.index')
            ->with('success', 'Modalidad de servicio creada.');
    }

    public function update(StoreSupervisorPostModalityRequest $request, SupervisorPostModality $postModality): RedirectResponse
    {
        abort_unless((int) $postModality->security_company_id === $this->companyId($request), 404);
        $this->catalog->updatePostModality($postModality, [
            'hours' => (int) $request->validated('hours'),
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('company.supervision-post-modalities.index')
            ->with('success', 'Modalidad de servicio actualizada.');
    }

    public function destroy(Request $request, SupervisorPostModality $postModality): RedirectResponse
    {
        abort_unless((int) $postModality->security_company_id === $this->companyId($request), 404);
        $this->catalog->deletePostModality($postModality);

        return redirect()->route('company.supervision-post-modalities.index')
            ->with('success', 'Modalidad de servicio eliminada.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
