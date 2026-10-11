<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\SecurityCompany;
use App\Repositories\ClientRepository;
use App\Support\Catalog\CompanyEntitlements;
use App\Support\Company\CompanyServiceAccess;
use App\Support\Platform\ActingCompanyResolver;
use App\Support\Platform\SupportCompanyContext;
use Illuminate\View\View;

final class CompanyLayoutComposer
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        $companyContext = [
            'company_name' => null,
            'is_quota_full' => true,
        ];

        $supportMode = [
            'active' => false,
            'company_name' => null,
            'company_id' => null,
        ];

        if ($user !== null) {
            $companyId = app(ActingCompanyResolver::class)->id($user);

            if ($companyId !== null) {
                $metrics = $this->clientRepository->metricsForCompany($companyId);
                $companyContext = [
                    'company_name' => $metrics['company_name'],
                    'is_quota_full' => (bool) $metrics['is_quota_full'],
                ];
            }

            if ($user->hasRole('super-admin') && SupportCompanyContext::isActive()) {
                $actingId = SupportCompanyContext::companyId();
                $company = $actingId !== null
                    ? SecurityCompany::query()->find($actingId)
                    : null;

                $supportMode = [
                    'active' => true,
                    'company_name' => $company?->displayName() ?? $companyContext['company_name'],
                    'company_id' => $actingId,
                ];
            }
        }

        $suspended = false;
        if ($user !== null && ! $user->hasRole('super-admin')) {
            $user->loadMissing('securityCompany');
            $suspended = CompanyServiceAccess::isCut($user->securityCompany);
        }

        $moduleKeys = array_keys(\App\Support\Catalog\CatalogSettings::defaults()['module_options'] ?? []);
        $canMod = array_fill_keys($moduleKeys, true);
        $canMod['devices'] = true;
        if ($user !== null && (! $user->hasRole('super-admin') || SupportCompanyContext::isActive())) {
            $entitled = $user->securityCompany;
            if (SupportCompanyContext::isActive()) {
                $actingId = SupportCompanyContext::companyId();
                $entitled = $actingId !== null ? SecurityCompany::query()->find($actingId) : $entitled;
            } elseif ($user->security_company_id) {
                $entitled = $user->securityCompany ?? SecurityCompany::query()->find($user->security_company_id);
            }
            $canMod = array_fill_keys(CompanyEntitlements::for($entitled)->modules(), true);
            $canMod['devices'] = (bool) $entitled?->hasSupervisionPackage();
        }

        $view->with('companyContext', $companyContext);
        $view->with('supportMode', $supportMode);
        $view->with('companyServiceSuspended', $suspended);
        $view->with('canMod', $canMod);
    }
}
