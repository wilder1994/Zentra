<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\CompanyPriceChange;
use App\Models\SecurityCompany;
use App\Models\User;
use Carbon\CarbonImmutable;

final class CompanyPriceHistory
{
    /** @return array{amount: float, plan: string} */
    public function snapshot(SecurityCompany $company): array
    {
        return [
            'amount' => $company->contractedAmount(),
            'plan' => $this->planLabel($company),
        ];
    }

    /** @param array{amount: float, plan: string} $before */
    public function record(
        SecurityCompany $company,
        array $before,
        string $reason,
        ?User $actor = null,
        ?string $note = null,
        ?CarbonImmutable $effectiveAt = null,
    ): ?CompanyPriceChange {
        $company = $company->fresh() ?? $company;
        $after = $this->snapshot($company);

        if (abs($after['amount'] - $before['amount']) < 0.01 && $after['plan'] === $before['plan']) {
            return null;
        }

        return CompanyPriceChange::query()->create([
            'security_company_id' => $company->id,
            'reason' => $reason,
            'billing_cycle' => $company->billing_cycle?->value,
            'previous_amount' => $before['amount'],
            'new_amount' => $after['amount'],
            'previous_plan' => $before['plan'] !== '' ? mb_substr($before['plan'], 0, 120) : null,
            'new_plan' => mb_substr($after['plan'], 0, 120),
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'effective_at' => $effectiveAt ?? CarbonImmutable::now(),
            'user_id' => $actor?->id ?? auth()->id(),
        ]);
    }

    public function planLabel(SecurityCompany $company): string
    {
        if ($company->package_sku === null && $company->supervision_package_sku === null) {
            return '';
        }

        $parts = [];
        if ($company->hasAccessPackage()) {
            $parts[] = 'Accesos '.$company->packageLabel();
        }
        if ($company->supervision_package_sku !== null) {
            $parts[] = $company->supervision_package_sku->label();
        }

        return implode(' + ', $parts);
    }
}
