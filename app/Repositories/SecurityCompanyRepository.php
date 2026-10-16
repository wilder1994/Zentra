<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ClientLifecycle;
use App\Enums\CompanyAlertBucket;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Support\Tenancy\CompanySubscriptionState;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class SecurityCompanyRepository
{
    public function platformMetrics(): array
    {
        return [
            'companies_total' => SecurityCompany::query()->count(),
            'companies_active' => SecurityCompany::query()->where('is_active', true)->count(),
            'clients_total' => Client::query()->count(),
            'clients_active' => Client::query()->where('is_active', true)->count(),
            'users_total' => User::query()->count(),
        ];
    }

    /**
     * KPIs de la vista /admin/companies (cartera completa, no página paginada).
     *
     * @return array{
     *     companies_total: int,
     *     companies_active: int,
     *     companies_archived: int,
     *     companies_suspended: int,
     *     companies_deleted: int,
     *     companies_risk_total: int,
     *     clients_total: int,
     *     clients_operational: int,
     *     clients_archived: int
     * }
     */
    public function companiesIndexKpis(): array
    {
        $companiesTotal = SecurityCompany::query()->count();
        $companiesArchived = SecurityCompany::query()->whereNotNull('archived_at')->count();
        $companiesSuspended = SecurityCompany::query()
            ->whereNull('archived_at')
            ->where('subscription_status', SubscriptionStatus::Suspended)
            ->count();
        $companiesOverdue = SecurityCompany::query()
            ->whereNull('archived_at')
            ->get()
            ->filter(fn (SecurityCompany $c) => CompanySubscriptionState::bucket($c) === CompanyAlertBucket::Overdue)
            ->count();
        $companiesActive = SecurityCompany::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->count();

        $clientsTotal = Client::query()->count();
        $clientsOperational = Client::query()
            ->where('lifecycle', ClientLifecycle::Active)
            ->count();

        return [
            'companies_total' => $companiesTotal,
            'companies_active' => $companiesActive,
            'companies_archived' => $companiesArchived,
            'companies_suspended' => $companiesSuspended,
            'companies_overdue' => $companiesOverdue,
            'companies_risk_total' => $companiesSuspended + $companiesOverdue,
            'clients_total' => $clientsTotal,
            'clients_operational' => $clientsOperational,
            'clients_archived' => $clientsTotal - $clientsOperational,
        ];
    }

    /** @return Collection<int, SecurityCompany> */
    public function recentCompanies(int $limit = 5): Collection
    {
        return SecurityCompany::query()
            ->withCount('clients')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return SecurityCompany::query()
            ->withCount('clients')
            ->withCount([
                'clients as operational_clients_count' => fn ($q) => $q->where('lifecycle', ClientLifecycle::Active),
                'employees as employees_active_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('trade_name')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): SecurityCompany
    {
        return SecurityCompany::query()
            ->withCount('clients')
            ->findOrFail($id);
    }
}
