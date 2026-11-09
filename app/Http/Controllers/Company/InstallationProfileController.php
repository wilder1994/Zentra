<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Support\Company\SettingsCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyInstallationProfileRequest;
use App\Http\Requests\Company\UpdateCompanyInstallationProfileRequest;
use App\Models\InstallationProfile;
use App\Services\Company\EnsureInstallationProfilesService;
use App\Services\Company\ManageCompanyInstallationProfileService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InstallationProfileController extends Controller
{
    public function __construct(
        private readonly ManageCompanyInstallationProfileService $manage,
        private readonly EnsureInstallationProfilesService $ensure,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', InstallationProfile::class);

        return SettingsCatalog::hubRedirect('perfiles');
    }


    public function store(StoreCompanyInstallationProfileRequest $request): RedirectResponse
    {
        $this->manage->create($this->companyId($request), [
            'name' => $request->validated('name'),
            'requires_dane' => $request->boolean('requires_dane'),
            'observatory_community' => $request->boolean('observatory_community'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('company.installation-profiles.index')
            ->with('success', 'Perfil de sitio creado.');
    }

    public function update(UpdateCompanyInstallationProfileRequest $request, InstallationProfile $installationProfile): RedirectResponse
    {
        $this->assertCompany($request, $installationProfile);

        try {
            $this->manage->update($installationProfile, [
                'name' => $request->validated('name'),
                'requires_dane' => $request->boolean('requires_dane'),
                'observatory_community' => $request->boolean('observatory_community'),
                'is_active' => $request->boolean('is_active'),
            ]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.installation-profiles.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo actualizar el perfil.');
        }

        return redirect()
            ->route('company.installation-profiles.index')
            ->with('success', 'Perfil de sitio actualizado.');
    }

    public function destroy(Request $request, InstallationProfile $installationProfile): RedirectResponse
    {
        $this->assertCompany($request, $installationProfile);
        $this->authorize('delete', $installationProfile);

        try {
            $this->manage->delete($installationProfile);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.installation-profiles.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo eliminar el perfil.');
        }

        return redirect()
            ->route('company.installation-profiles.index')
            ->with('success', 'Perfil de sitio eliminado.');
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    private function assertCompany(Request $request, InstallationProfile $profile): void
    {
        abort_unless((int) $profile->security_company_id === $this->companyId($request), 404);
    }
}
