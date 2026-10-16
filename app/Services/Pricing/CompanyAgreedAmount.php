<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Models\SecurityCompany;

/**
 * Fija el valor total pactado: la diferencia contra el valor calculado se carga primero
 * al producto ampliado y deja la empresa con precio pactado (fuera de catálogo e incrementos).
 */
final class CompanyAgreedAmount
{
    private const PRICE_FIELDS = [
        'access' => 'package_price',
        'supervision' => 'supervision_package_price',
        'indexing' => 'indexing_price',
        'observatory' => 'observatory_price',
    ];

    public function apply(SecurityCompany $company, float $amount, ?CommercialProduct $focus, ?string $note): SecurityCompany
    {
        $suffix = $company->billing_cycle === BillingCycle::Annual ? '_annual' : '_monthly';
        $diff = round(max(0, $amount) - $company->contractedAmount(), 2);

        $values = [];
        foreach (self::PRICE_FIELDS as $key => $prefix) {
            $value = (float) ($company->getAttribute($prefix.$suffix) ?? 0);
            if ($value > 0 || $key === $focus?->value) {
                $values[$prefix.$suffix] = $value;
            }
        }

        $order = array_keys($values);
        usort($order, function (string $a, string $b) use ($values, $focus, $suffix): int {
            $focusField = $focus !== null ? self::PRICE_FIELDS[$focus->value].$suffix : null;
            if ($a === $focusField) {
                return -1;
            }
            if ($b === $focusField) {
                return 1;
            }

            return $values[$b] <=> $values[$a];
        });

        foreach ($order as $field) {
            if (abs($diff) < 0.01) {
                break;
            }
            $next = max(0, round($values[$field] + $diff, 2));
            $diff = round($diff - ($next - $values[$field]), 2);
            $values[$field] = $next;
        }

        $company->update($values + [
            'price_locked' => true,
            'price_lock_note' => $note !== null ? mb_substr($note, 0, 255) : $company->price_lock_note,
            'pending_reprice_at' => null,
            'pending_reprice_reason' => null,
            'pending_reprice_note' => null,
        ]);

        return $company->fresh();
    }
}
