<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\ClientLifecycle;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\SupervisorPost;

/** Resumen de cupos para Facturación (empresa) y la ficha del superadmin. */
final class QuotaOverview
{
    /** @return array<string, mixed> */
    public static function for(SecurityCompany $company): array
    {
        $entitlements = CompanyEntitlements::for($company);
        $quota = $company->installationQuota();

        $lines = [];
        foreach ([InstallationQuota::ACCESS, InstallationQuota::SUPERVISION, InstallationQuota::OBSERVATORY] as $line) {
            $capacity = $quota->capacity($line);
            if ($capacity < 1) {
                continue;
            }
            $lines[] = [
                'label' => InstallationQuota::label($line),
                'capacity' => $capacity === PHP_INT_MAX ? null : $capacity,
                'used' => $quota->used($line),
            ];
        }

        $installations = Installation::query()
            ->withoutGlobalScopes()
            ->whereNull('installations.deleted_at')
            ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id)->where('lifecycle', ClientLifecycle::Active))
            ->with('client:id,name')
            ->withCount(['supervisorPosts as active_posts' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active', true)])
            ->orderBy('client_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Installation $i) => [
                'id' => $i->id,
                'label' => ($i->client?->name ?? 'Cliente').' · '.$i->name,
                'is_active' => (bool) $i->is_active,
                'posts' => (int) $i->active_posts,
                'limit' => PostQuota::postsLimit($i, $company),
                'override' => $i->max_posts,
                'note' => $i->max_posts_note,
            ]);

        $posts = SupervisorPost::query()
            ->withoutGlobalScopes()
            ->whereNull('supervisor_posts.deleted_at')
            ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id)->where('lifecycle', ClientLifecycle::Active))
            ->with(['client:id,name', 'installation' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->withCount(['employees as assigned' => fn ($q) => $q->where('employees.is_active', true)])
            ->orderBy('client_id')
            ->orderBy('name')
            ->get()
            ->map(fn (SupervisorPost $p) => [
                'id' => $p->id,
                'label' => ($p->client?->name ?? 'Cliente').' · '.($p->installation?->name ?? '—').' · '.$p->name,
                'is_active' => (bool) $p->is_active,
                'assigned' => (int) $p->assigned,
                'limit' => PostQuota::employeesLimit($p, $company),
                'override' => $p->max_employees,
                'note' => $p->max_employees_note,
            ]);

        return [
            'metal' => $entitlements->metal()?->label(),
            'capacity' => $quota->capacity(),
            'used' => $quota->used(),
            'breakdown' => $quota->breakdown(),
            'lines' => $lines,
            'employees_used' => Employee::query()->where('security_company_id', $company->id)->where('is_active', true)->count(),
            'employee_cap' => $entitlements->employeeCap(),
            'plan_employee_cap' => $entitlements->planEmployeeCap(),
            'extra_employees' => (int) $company->extra_employees,
            'posts_per_installation' => $entitlements->postsPerInstallation(),
            'plan_posts_per_installation' => $entitlements->metal()?->postsPerInstallation(),
            'employees_per_post' => $entitlements->employeesPerPost(),
            'plan_employees_per_post' => $entitlements->metal()?->employeesPerPost(),
            'quota_note' => $company->quota_note,
            'installations' => $installations->all(),
            'posts' => $posts->all(),
        ];
    }
}
