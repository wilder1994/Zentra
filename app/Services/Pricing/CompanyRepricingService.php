<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\BillingCycle;
use App\Enums\EvidenceEventType;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Services\Platform\RecordLifecycleEvidenceService;
use App\Support\Catalog\CatalogSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recalcula el valor de una empresa con el catálogo vigente, conservando sus instalaciones.
 * Los cambios de catálogo o incrementos se programan y aplican desde la próxima facturación.
 */
final class CompanyRepricingService
{
    public const REASON_PLANS = 'plans_migration';

    public const REASON_CATALOG = 'catalog';

    public const REASON_INCREASE = 'annual_increase';

    public function __construct(
        private readonly PriceCalculator $priceCalculator,
        private readonly CompanyPriceHistory $history,
        private readonly RecordLifecycleEvidenceService $evidenceService,
    ) {}

    /**
     * @return array{
     *   access_monthly: float, access_annual: float, unit: float, discount: float, annual_discount: float,
     *   supervision_monthly: ?float, supervision_annual: ?float, supervision_unit: ?float, amount: float
     * }
     */
    public function quote(SecurityCompany $company, ?CatalogSettings $catalog = null): array
    {
        $cycle = $company->billing_cycle ?? BillingCycle::Monthly;
        $access = $this->priceCalculator->quoteAccess($company->accessSeats(), $cycle, $catalog);

        $sup = $company->supervision_package_sku !== null
            ? $this->priceCalculator->quoteSupervisionSku($company->supervision_package_sku, $cycle, $catalog)
            : null;

        $amount = $access->amountDue() + ($sup?->amountDue() ?? 0.0);

        return [
            'access_monthly' => $access->priceMonthly,
            'access_annual' => $access->priceAnnual,
            'unit' => $access->unitPrice,
            'discount' => $access->volumeDiscountPct,
            'annual_discount' => $access->annualDiscountPct,
            'supervision_monthly' => $sup?->priceMonthly,
            'supervision_annual' => $sup?->priceAnnual,
            'supervision_unit' => $sup?->unitPrice,
            'amount' => round($amount, 2),
        ];
    }

    public function apply(SecurityCompany $company, string $reason, ?User $actor = null, ?string $note = null): SecurityCompany
    {
        $before = $this->history->snapshot($company);
        $quote = $this->quote($company);

        $company->update([
            'unit_price_snapshot' => $quote['unit'],
            'volume_discount_pct' => $quote['discount'],
            'annual_discount_pct' => $quote['annual_discount'],
            'package_price_monthly' => $quote['access_monthly'],
            'package_price_annual' => $quote['access_annual'],
            'supervision_unit_price_snapshot' => $quote['supervision_unit'],
            'supervision_package_price_monthly' => $quote['supervision_monthly'],
            'supervision_package_price_annual' => $quote['supervision_annual'],
            'pending_reprice_at' => null,
            'pending_reprice_reason' => null,
            'pending_reprice_note' => null,
        ]);

        $this->history->record($company, $before, $reason, $actor, $note);

        return $company->fresh();
    }

    public function applyIfDue(SecurityCompany $company, ?CarbonImmutable $now = null): SecurityCompany
    {
        if (! $company->isRepriceDue($now)) {
            if ($company->pending_reprice_at !== null && $company->isPriceLocked($now)) {
                $company->update([
                    'pending_reprice_at' => null,
                    'pending_reprice_reason' => null,
                    'pending_reprice_note' => null,
                ]);
            }

            return $company;
        }

        $reason = (string) ($company->pending_reprice_reason ?: self::REASON_CATALOG);
        $note = $company->pending_reprice_note;
        $fresh = $this->apply($company, $reason, null, $note);

        $this->evidenceService->record(
            EvidenceEventType::PackageChangeApplied,
            'Nuevo valor del plan aplicado',
            ['reason' => $reason, 'amount' => $fresh->contractedAmount()],
            $fresh->id,
        );

        return $fresh;
    }

    public function applyDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $applied = 0;

        SecurityCompany::query()
            ->whereNotNull('pending_reprice_at')
            ->where('pending_reprice_at', '<=', $now)
            ->chunkById(50, function ($companies) use (&$applied, $now): void {
                foreach ($companies as $company) {
                    if ($company->isRepriceDue($now)) {
                        $applied++;
                    }
                    $this->applyIfDue($company, $now);
                }
            });

        return $applied;
    }

    /**
     * Programa el recálculo en la primera facturación desde `$from`. Empresas con precio pactado quedan fuera.
     *
     * @return array{scheduled: int, locked: int}
     */
    public function scheduleAll(CarbonImmutable $from, string $reason, ?string $note = null): array
    {
        $scheduled = 0;
        $locked = 0;

        $this->billableCompanies()->chunkById(100, function ($companies) use ($from, $reason, $note, &$scheduled, &$locked): void {
            foreach ($companies as $company) {
                if ($company->isPriceLocked()) {
                    $locked++;

                    continue;
                }

                $company->update([
                    'pending_reprice_at' => $this->nextBillingOnOrAfter($company, $from),
                    'pending_reprice_reason' => $reason,
                    'pending_reprice_note' => $note !== null ? mb_substr($note, 0, 255) : null,
                ]);
                $scheduled++;
            }
        });

        return compact('scheduled', 'locked');
    }

    /**
     * Vista previa por empresa con un catálogo dado.
     *
     * @return list<array{id: int, name: string, plan: string, current: float, new: float, locked: bool, effective_at: ?string}>
     */
    public function preview(CatalogSettings $catalog, CarbonImmutable $from): array
    {
        $rows = [];
        $this->billableCompanies()->orderBy('trade_name')->each(function (SecurityCompany $company) use ($catalog, $from, &$rows): void {
            $locked = $company->isPriceLocked();
            $rows[] = [
                'id' => (int) $company->id,
                'name' => $company->displayName(),
                'plan' => $this->history->planLabel($company),
                'current' => $company->contractedAmount(),
                'new' => $locked ? $company->contractedAmount() : $this->quote($company, $catalog)['amount'],
                'locked' => $locked,
                'effective_at' => $locked ? null : $this->nextBillingOnOrAfter($company, $from)->format('d/m/Y'),
            ];
        });

        return $rows;
    }

    public function nextBillingOnOrAfter(SecurityCompany $company, CarbonImmutable $from): CarbonImmutable
    {
        if ($company->package_ends_at === null) {
            return $from;
        }

        $cut = CarbonImmutable::parse($company->package_ends_at);
        $annual = $company->billing_cycle === BillingCycle::Annual;
        $guard = 0;
        while ($cut->lessThan($from->startOfDay()) && $guard++ < 240) {
            $cut = $annual ? $cut->addYear() : $cut->addMonth();
        }

        return $cut;
    }

    /** @return Builder<SecurityCompany> */
    private function billableCompanies(): Builder
    {
        return SecurityCompany::query()
            ->whereNull('archived_at')
            ->where(function (Builder $q): void {
                $q->whereNotNull('package_sku')->orWhereNotNull('supervision_package_sku');
            });
    }
}
