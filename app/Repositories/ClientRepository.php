<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ClientLifecycle;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Support\Catalog\CompanyEntitlements;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class ClientRepository
{
    public function paginateOperableForUser(
        User $user,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        bool $accessOnly = true,
    ): LengthAwarePaginator {
        $query = Client::query()
            ->whereIn('id', $user->assignedClientIds())
            ->when($accessOnly, fn ($q) => $q->where('has_access', true))
            ->with(['securityCompany'])
            ->withCount('assignments');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('address', 'like', $term);
            });
        }

        return $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForCompany(
        int $companyId,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        bool $accessOnly = false,
    ): LengthAwarePaginator {
        $query = Client::query()
            ->where('security_company_id', $companyId)
            ->when($accessOnly, fn ($q) => $q->where('has_access', true))
            ->with(['securityCompany'])
            ->withCount('assignments');

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('address', 'like', $term);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForCompany(int $clientId, int $companyId): ?Client
    {
        return Client::query()
            ->where('security_company_id', $companyId)
            ->whereKey($clientId)
            ->first();
    }

    public function slugExists(int $companyId, string $slug, ?int $exceptId = null): bool
    {
        $query = Client::query()
            ->where('security_company_id', $companyId)
            ->where('slug', $slug);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function loginSuffixExists(int $companyId, string $suffix, ?int $exceptId = null): bool
    {
        $query = Client::query()
            ->where('security_company_id', $companyId)
            ->where('login_suffix', $suffix);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /** @return Collection<int, Client> */
    public function activeForCompany(int $companyId): Collection
    {
        return Client::query()
            ->with('securityCompany')
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Client> */
    public function activeAll(): Collection
    {
        return Client::query()
            ->with('securityCompany')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    public function metricsForCompany(int $companyId): array
    {
        $company = SecurityCompany::query()->findOrFail($companyId);
        $base = Client::query()->where('security_company_id', $companyId);
        $total = (clone $base)->where('lifecycle', ClientLifecycle::Active)->count();
        $active = (clone $base)->where('lifecycle', ClientLifecycle::Active)->where('is_active', true)->count();
        $inactive = $total - $active;
        $archived = (clone $base)->where('lifecycle', ClientLifecycle::ArchivedCompany)->count();
        $released = (clone $base)->where('lifecycle', ClientLifecycle::Released)->count();
        $maxClients = (int) ($company->max_clients ?: 0);
        $maxSupervision = $company->hasUnlimitedSupervision()
            ? null
            : (int) ($company->max_supervision_clients ?: 0);
        $quota = $company->installationQuota();
        $capacity = $quota->capacity();
        $accessUsed = $company->accessSeatsCount();
        $installationsUsed = $company->installationSeatsCount();
        $supervisionUsed = $company->supervisionSeatsCount();
        $entitlements = CompanyEntitlements::for($company);
        $usageRatio = $capacity > 0 ? round(($installationsUsed / $capacity) * 100, 1) : 0.0;
        $features = $company->package_modality?->features() ?? [];
        $featureLabels = $company->package_modality?->featureLabels() ?? [];
        $contractedAmount = $company->contractedAmount();
        $costPerClientSlot = $capacity > 0 ? round($contractedAmount / $capacity, 0) : 0.0;
        $endsAt = $company->package_ends_at;
        $daysUntilRenewal = $endsAt !== null
            ? (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false)
            : null;

        return [
            'company_name' => $company->trade_name,
            'users_count' => $company->users()->count(),
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'archived' => $archived,
            'released' => $released,
            'max_clients' => $maxClients,
            'access_used' => $accessUsed,
            'access_remaining' => $company->accessSeatsRemaining(),
            'installation_capacity' => $capacity,
            'installations_used' => $installationsUsed,
            'installations_remaining' => $company->installationSeatsRemaining(),
            'installations_breakdown' => $quota->breakdown(),
            'is_installation_quota_full' => $capacity > 0 && $installationsUsed >= $capacity,
            'max_observatory' => $entitlements->observatorySeats(),
            'observatory_used' => $company->observatorySeatsCount(),
            'observatory_remaining' => $company->observatorySeatsRemaining(),
            'plan_metal_label' => $entitlements->metal()?->label(),
            'employee_cap' => $entitlements->employeeCap(),
            'posts_per_installation' => $entitlements->postsPerInstallation(),
            'employees_per_post' => $entitlements->employeesPerPost(),
            'max_supervision_clients' => $maxSupervision,
            'supervision_used' => $supervisionUsed,
            'supervision_remaining' => $company->supervisionSeatsRemaining(),
            'supervision_remaining_label' => $company->hasUnlimitedSupervision()
                ? 'Ilimitada'
                : (string) $company->supervisionSeatsRemaining(),
            'supervision_unlimited' => $company->hasUnlimitedSupervision(),
            'supervision_package_sku' => $company->supervision_package_sku?->value,
            'supervision_package_label' => $company->supervision_package_sku?->label(),
            'usage_ratio' => $usageRatio,
            'package_sku' => $company->package_sku?->value,
            'package_label' => $company->packageLabel(),
            'package_modality' => $company->package_modality?->value ?? 'manual',
            'package_modality_label' => $company->package_modality?->label() ?? 'Sin hardware',
            'package_price' => (float) ($company->package_price_monthly ?? 0),
            'package_price_annual' => (float) ($company->package_price_annual ?? 0),
            'contracted_amount' => $company->contractedAmount(),
            'billing_cycle' => $company->billing_cycle?->value ?? 'monthly',
            'billing_cycle_label' => $company->billingPeriodLabel(),
            'subscription_status' => $company->subscription_status?->value ?? 'active',
            'subscription_status_label' => $company->subscription_status?->label() ?? 'Activa',
            'package_starts_at' => $company->package_starts_at,
            'package_ends_at' => $company->package_ends_at,
            'volume_discount_pct' => (float) ($company->volume_discount_pct ?? 0),
            'annual_discount_pct' => (float) ($company->annual_discount_pct ?? 0),
            'features' => $features,
            'feature_labels' => $featureLabels,
            'cost_per_client_slot' => $costPerClientSlot,
            'days_until_renewal' => $daysUntilRenewal,
            'is_renewal_soon' => $daysUntilRenewal !== null && $daysUntilRenewal >= 0 && $daysUntilRenewal <= 30,
            'is_expired' => $daysUntilRenewal !== null && $daysUntilRenewal < 0,
            'is_quota_full' => $maxClients > 0 && $accessUsed >= $maxClients,
            'is_supervision_quota_full' => ! $company->hasUnlimitedSupervision()
                && $maxSupervision !== null
                && $maxSupervision > 0
                && $supervisionUsed >= $maxSupervision,
            'is_hardware' => (int) ($company->package_hardware_seats ?? 0) > 0,
        ];
    }
}
