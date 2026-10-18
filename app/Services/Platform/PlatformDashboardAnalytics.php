<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\BillingCycle;
use App\Enums\ClientLifecycle;
use App\Enums\CommercialMetal;
use App\Enums\CompanyAlertBucket;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Support\Geo\PlaceLabel;
use App\Support\Tenancy\CompanySubscriptionState;
use Illuminate\Database\Eloquent\Collection;

final class PlatformDashboardAnalytics
{
    /** @param  Collection<int, SecurityCompany>  $companies */
    public function build(Collection $companies): array
    {
        $companies->loadMissing(['clients' => fn ($q) => $q->orderBy('name')]);

        $activeCompanies = $companies->filter(
            function (SecurityCompany $company): bool {
                $bucket = CompanySubscriptionState::bucket($company);

                return $bucket !== CompanyAlertBucket::Archived
                    && $bucket !== CompanyAlertBucket::Suspended;
            }
        );

        $operationalClients = $this->operationalClientsCount($companies);
        $mrr = $this->totalMrr($activeCompanies);

        return [
            'kpis' => [
                'active_companies' => $activeCompanies->count(),
                'operational_clients' => $operationalClients,
                'mrr' => $mrr,
                'retention_rate' => $this->retentionRate($companies),
            ],
            'portfolio_status' => $this->portfolioStatus($companies),
            'plans_access' => $this->plansBreakdown($activeCompanies, 'access'),
            'plans_supervision' => $this->plansBreakdown($activeCompanies, 'supervision'),
            'billing_cycles' => $this->billingCycleBreakdown($activeCompanies),
            'top_billing' => $this->topBillingCompanies($activeCompanies),
            'growth_monthly' => $this->monthlyGrowthSeries($companies),
            'commercial_kpis' => $this->commercialKpis($companies, $activeCompanies, $mrr),
            'mrr_trend' => $this->mrrTrendSeries($activeCompanies),
            'clients_trend' => $this->clientsTrendSeries($companies),
            'map_markers' => $this->mapMarkers($companies),
            'google_maps' => [
                'api_key' => config('google-maps.api_key'),
                'center' => config('google-maps.default_center'),
                'zoom' => config('google-maps.default_zoom'),
            ],
        ];
    }

    /** @param  Collection<int, SecurityCompany>  $companies */
    private function operationalClientsCount(Collection $companies): int
    {
        return (int) $companies->sum(
            fn (SecurityCompany $company) => $company->clients
                ->where('lifecycle', ClientLifecycle::Active)
                ->count()
        );
    }

    /** @param  Collection<int, SecurityCompany>  $companies */
    private function totalMrr(Collection $companies): float
    {
        return round($companies->sum(fn (SecurityCompany $company) => $this->monthlyRevenue($company)), 2);
    }

    private function monthlyRevenue(SecurityCompany $company): float
    {
        if ($company->billing_cycle === BillingCycle::Annual) {
            return $company->contractedAmount() / 12;
        }

        return $company->contractedAmount();
    }

    /** @param  Collection<int, SecurityCompany>  $companies */
    private function retentionRate(Collection $companies): float
    {
        if ($companies->isEmpty()) {
            return 0.0;
        }

        $active = $companies->filter(
            fn (SecurityCompany $company) => CompanySubscriptionState::bucket($company) === CompanyAlertBucket::Current
        )->count();

        return round(($active / $companies->count()) * 100, 1);
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return list<array{label: string, value: int, key: string}>
     */
    private function portfolioStatus(Collection $companies): array
    {
        $counts = [
            CompanyAlertBucket::Current->value => 0,
            CompanyAlertBucket::DueSoon->value => 0,
            CompanyAlertBucket::Overdue->value => 0,
            CompanyAlertBucket::Suspended->value => 0,
            CompanyAlertBucket::Archived->value => 0,
        ];

        foreach ($companies as $company) {
            $counts[CompanySubscriptionState::bucket($company)->value]++;
        }

        $deleted = SecurityCompany::onlyTrashed()->count();

        return [
            ['key' => CompanyAlertBucket::Current->value, 'label' => CompanyAlertBucket::Current->label(), 'value' => $counts[CompanyAlertBucket::Current->value]],
            ['key' => CompanyAlertBucket::DueSoon->value, 'label' => CompanyAlertBucket::DueSoon->label(), 'value' => $counts[CompanyAlertBucket::DueSoon->value]],
            ['key' => CompanyAlertBucket::Overdue->value, 'label' => CompanyAlertBucket::Overdue->label(), 'value' => $counts[CompanyAlertBucket::Overdue->value]],
            ['key' => CompanyAlertBucket::Suspended->value, 'label' => CompanyAlertBucket::Suspended->label(), 'value' => $counts[CompanyAlertBucket::Suspended->value]],
            ['key' => CompanyAlertBucket::Archived->value, 'label' => CompanyAlertBucket::Archived->label(), 'value' => $counts[CompanyAlertBucket::Archived->value]],
            ['key' => 'deleted', 'label' => 'Eliminadas', 'value' => $deleted],
        ];
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return list<array{label: string, value: int, key: string}>
     */
    private function plansBreakdown(Collection $companies, string $product): array
    {
        $rows = [];
        foreach (CommercialMetal::cases() as $metal) {
            $rows[] = [
                'key' => $metal->value,
                'label' => $metal->label(),
                'value' => $companies->filter(
                    fn (SecurityCompany $company) => ($product === 'access' ? $company->accessMetal() : $company->supervisionMetal()) === $metal
                )->count(),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return list<array{label: string, value: int, key: string}>
     */
    private function billingCycleBreakdown(Collection $companies): array
    {
        return [
            [
                'key' => BillingCycle::Monthly->value,
                'label' => BillingCycle::Monthly->label(),
                'value' => $companies->where('billing_cycle', BillingCycle::Monthly)->count(),
            ],
            [
                'key' => BillingCycle::Annual->value,
                'label' => BillingCycle::Annual->label(),
                'value' => $companies->where('billing_cycle', BillingCycle::Annual)->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return list<array{id: int, name: string, mrr: float, share: float}>
     */
    private function topBillingCompanies(Collection $companies): array
    {
        $totalMrr = max(1.0, $this->totalMrr($companies));

        return $companies
            ->map(fn (SecurityCompany $company) => [
                'id' => $company->id,
                'name' => $company->trade_name,
                'mrr' => $this->monthlyRevenue($company),
            ])
            ->sortByDesc('mrr')
            ->take(5)
            ->values()
            ->map(fn (array $row) => [
                ...$row,
                'share' => round(($row['mrr'] / $totalMrr) * 100, 1),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return array{labels: list<string>, nuevos: list<int>, retenidos: list<int>}
     */
    private function monthlyGrowthSeries(Collection $companies): array
    {
        $labels = [];
        $nuevos = [];
        $retenidos = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $labels[] = $month->translatedFormat('M');
            $nuevos[] = $companies->filter(
                fn (SecurityCompany $company) => $company->created_at?->isSameMonth($month) ?? false
            )->count();
            $retenidos[] = $companies->filter(
                fn (SecurityCompany $company) => ($company->created_at?->lt($month) ?? false)
                    && CompanySubscriptionState::bucket($company) !== CompanyAlertBucket::Archived
                    && CompanySubscriptionState::bucket($company) !== CompanyAlertBucket::Suspended
            )->count();
        }

        return compact('labels', 'nuevos', 'retenidos');
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return array{coverage: float, mrr_growth: float, churn: float}
     */
    private function commercialKpis(Collection $companies, Collection $activeCompanies, float $mrr): array
    {
        $geolocatedCompanies = $companies->filter(fn (SecurityCompany $company) => $this->companyHasCoordinates($company))->count();
        $coverage = $companies->isEmpty() ? 0.0 : round(($geolocatedCompanies / $companies->count()) * 100, 1);

        $previousMrr = $this->estimatePreviousMrr($activeCompanies);
        $mrrGrowth = $previousMrr > 0
            ? round((($mrr - $previousMrr) / $previousMrr) * 100, 1)
            : 0.0;

        $archivedLastMonth = $companies->filter(
            fn (SecurityCompany $company) => $company->archived_at?->greaterThanOrEqualTo(now()->subMonth()) ?? false
        )->count();
        $churn = $activeCompanies->isEmpty()
            ? 0.0
            : round(($archivedLastMonth / max(1, $activeCompanies->count())) * 100, 1);

        return [
            'coverage' => $coverage,
            'mrr_growth' => $mrrGrowth,
            'churn' => $churn,
        ];
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return list<array{label: string, value: float}>
     */
    private function mrrTrendSeries(Collection $companies): array
    {
        $points = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $value = $companies
                ->filter(fn (SecurityCompany $company) => $company->package_starts_at?->lte($month->copy()->endOfMonth()) ?? false)
                ->sum(fn (SecurityCompany $company) => $this->monthlyRevenue($company));

            $points[] = [
                'label' => $month->translatedFormat('M'),
                'value' => round($value / 1_000_000, 1),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return list<array{label: string, value: int}>
     */
    private function clientsTrendSeries(Collection $companies): array
    {
        $points = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->endOfMonth();
            $count = Client::query()
                ->where('lifecycle', ClientLifecycle::Active)
                ->where('created_at', '<=', $month)
                ->count();

            $points[] = [
                'label' => $month->translatedFormat('M'),
                'value' => $count,
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, SecurityCompany>  $companies
     * @return array{empresa: list<array<string, mixed>>, clientes: list<array<string, mixed>>}
     */
    private function mapMarkers(Collection $companies): array
    {
        $empresa = [];
        $clientes = [];

        foreach ($companies as $company) {
            $coords = $this->companyCoordinates($company);

            if ($coords !== null) {
                $bucket = CompanySubscriptionState::bucket($company);
                $empresa[] = [
                    'id' => $company->id,
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'title' => $company->trade_name,
                    'subtitle' => sprintf(
                        '%d clientes · %s',
                        $company->clients->where('lifecycle', ClientLifecycle::Active)->count(),
                        $bucket->label()
                    ),
                    'address' => PlaceLabel::of($company),
                    'url' => route('admin.companies.show', $company),
                    'enter_url' => route('admin.companies.enter', $company),
                ];
            }

            foreach ($company->clients as $client) {
                if ($client->latitude === null || $client->longitude === null) {
                    continue;
                }

                $clientes[] = [
                    'id' => $client->id,
                    'lat' => (float) $client->latitude,
                    'lng' => (float) $client->longitude,
                    'title' => $client->name,
                    'subtitle' => sprintf(
                        '%s · %s',
                        $company->trade_name,
                        $client->lifecycle?->label() ?? '—'
                    ),
                    'company' => $company->trade_name,
                    'address' => PlaceLabel::of($client),
                    'url' => route('admin.companies.show', $company),
                    'enter_url' => route('admin.companies.enter', $company),
                ];
            }
        }

        return [
            'empresa' => $empresa,
            'clientes' => $clientes,
        ];
    }

    private function companyHasCoordinates(SecurityCompany $company): bool
    {
        return $this->companyCoordinates($company) !== null;
    }

    /** @return array{lat: float, lng: float}|null */
    private function companyCoordinates(SecurityCompany $company): ?array
    {
        if ($company->latitude !== null && $company->longitude !== null) {
            return [
                'lat' => (float) $company->latitude,
                'lng' => (float) $company->longitude,
            ];
        }

        $clientCoords = $company->clients
            ->filter(fn (Client $client) => $client->latitude !== null && $client->longitude !== null);

        if ($clientCoords->isEmpty()) {
            return null;
        }

        return [
            'lat' => round($clientCoords->avg(fn (Client $client) => (float) $client->latitude), 6),
            'lng' => round($clientCoords->avg(fn (Client $client) => (float) $client->longitude), 6),
        ];
    }

    /** @param  Collection<int, SecurityCompany>  $companies */
    private function estimatePreviousMrr(Collection $companies): float
    {
        $cutoff = now()->subMonth()->endOfMonth();

        return round($companies
            ->filter(fn (SecurityCompany $company) => ($company->package_starts_at?->lte($cutoff) ?? false)
                && ($company->archived_at === null || $company->archived_at->gt($cutoff)))
            ->sum(fn (SecurityCompany $company) => $this->monthlyRevenue($company)), 2);
    }
}
