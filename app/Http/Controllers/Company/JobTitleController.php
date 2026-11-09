<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyJobTitleRequest;
use App\Http\Requests\Company\UpdateCompanyJobTitleRequest;
use App\Models\CompanyJobTitle;
use App\Services\Company\ManageCompanyJobTitleService;
use App\Support\Company\SettingsCatalog;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class JobTitleController extends Controller
{
    public function __construct(
        private readonly ManageCompanyJobTitleService $manageCompanyJobTitleService,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', CompanyJobTitle::class);

        return SettingsCatalog::hubRedirect('cargos');
    }

    public function store(StoreCompanyJobTitleRequest $request): RedirectResponse
    {
        $this->manageCompanyJobTitleService->create($this->companyId($request), [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('company.job-titles.index')
            ->with('success', 'Cargo creado.');
    }

    public function update(UpdateCompanyJobTitleRequest $request, CompanyJobTitle $jobTitle): RedirectResponse
    {
        $this->assertCompany($request, $jobTitle);
        $this->manageCompanyJobTitleService->update($jobTitle, [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('company.job-titles.index')
            ->with('success', 'Cargo actualizado.');
    }

    public function destroy(Request $request, CompanyJobTitle $jobTitle): RedirectResponse
    {
        $this->assertCompany($request, $jobTitle);
        $this->authorize('delete', $jobTitle);

        try {
            $this->manageCompanyJobTitleService->delete($jobTitle);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.job-titles.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo eliminar el cargo.');
        }

        return redirect()
            ->route('company.job-titles.index')
            ->with('success', 'Cargo eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    private function assertCompany(Request $request, CompanyJobTitle $jobTitle): void
    {
        abort_unless((int) $jobTitle->security_company_id === $this->companyId($request), 404);
    }
}
