<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Support\Catalog\CatalogSettings;

/**
 * Cotiza el plan completo:
 * 1. cada producto = unidad × instalaciones × (1 − descuento de su plan);
 * 2. el de mayor valor va completo y cada producto adicional lleva el descuento por combinar;
 * 3. en pago anual: × 12 × (1 − descuento anual), sobre el valor ya descontado.
 * Misma regla en resources/js/plan-picker.js.
 */
final class PlanQuoter
{
    /**
     * @return array{
     *   cycle: string, currency: string, annual_discount: float, combo_discount: float,
     *   lines: array<string, array{product: string, label: string, seats: int, metal: ?string, metal_label: ?string,
     *     unit: float, plan_discount: float, list_monthly: float, plan_monthly: float, combo: bool,
     *     combo_amount: float, monthly: float, annual: float, amount: float}>,
     *   list_monthly: float, plan_savings_monthly: float, combo_savings_monthly: float, monthly: float,
     *   list_amount: float, plan_savings: float, combo_savings: float, annual_savings: float,
     *   amount: float, savings: float
     * }
     */
    public function quote(PlanSelection $selection, BillingCycle $cycle, ?CatalogSettings $catalog = null): array
    {
        $catalog ??= CatalogSettings::current();
        $annualPct = (float) config('tenancy.pricing.annual_discount', 0.17);
        $comboPct = $catalog->comboDiscount();

        $lines = [];
        foreach (CommercialProduct::cases() as $product) {
            $seats = $selection->seats($product);
            if ($seats < 1) {
                continue;
            }

            $metal = CommercialMetal::fromSeats($seats);
            $discount = $catalog->discount($product, $metal);
            $list = $product === CommercialProduct::Access
                ? ($selection->access->manual * $catalog->unit('access')) + ($selection->access->hardware * $catalog->unit('access_hardware'))
                : $seats * $catalog->unit($product->value);

            $lines[$product->value] = [
                'product' => $product->value,
                'label' => $product->label(),
                'seats' => $seats,
                'metal' => $metal->value,
                'metal_label' => $metal->label(),
                'unit' => round($list / $seats, 2),
                'plan_discount' => $discount,
                'list_monthly' => round($list, 2),
                'plan_monthly' => round($list * (1 - $discount), 2),
                'combo' => false,
                'combo_amount' => 0.0,
                'monthly' => 0.0,
                'annual' => 0.0,
                'amount' => 0.0,
            ];
        }

        $order = array_keys($lines);
        usort($order, fn (string $a, string $b) => $lines[$b]['plan_monthly'] <=> $lines[$a]['plan_monthly']);

        foreach ($order as $i => $key) {
            $planMonthly = $lines[$key]['plan_monthly'];
            $monthly = $i === 0 ? $planMonthly : round($planMonthly * (1 - $comboPct), 2);
            $annual = round($monthly * 12 * (1 - $annualPct), 2);
            $lines[$key]['combo'] = $i > 0 && $comboPct > 0;
            $lines[$key]['combo_amount'] = round($planMonthly - $monthly, 2);
            $lines[$key]['monthly'] = $monthly;
            $lines[$key]['annual'] = $annual;
            $lines[$key]['amount'] = $cycle === BillingCycle::Annual ? $annual : $monthly;
        }

        $sum = fn (string $field): float => round(array_sum(array_column($lines, $field)), 2);
        $listMonthly = $sum('list_monthly');
        $planMonthly = $sum('plan_monthly');
        $monthly = $sum('monthly');
        $months = $cycle === BillingCycle::Annual ? 12 : 1;
        $amount = $sum('amount');
        $listAmount = round($listMonthly * $months, 2);

        return [
            'cycle' => $cycle->value,
            'currency' => $catalog->currency(),
            'annual_discount' => $annualPct,
            'combo_discount' => $comboPct,
            'lines' => $lines,
            'list_monthly' => $listMonthly,
            'plan_savings_monthly' => round($listMonthly - $planMonthly, 2),
            'combo_savings_monthly' => round($planMonthly - $monthly, 2),
            'monthly' => $monthly,
            'list_amount' => $listAmount,
            'plan_savings' => round(($listMonthly - $planMonthly) * $months, 2),
            'combo_savings' => round(($planMonthly - $monthly) * $months, 2),
            'annual_savings' => $cycle === BillingCycle::Annual ? round($monthly * 12 - $amount, 2) : 0.0,
            'amount' => $amount,
            'savings' => round($listAmount - $amount, 2),
        ];
    }

    public function amount(PlanSelection $selection, BillingCycle $cycle, ?CatalogSettings $catalog = null): float
    {
        return $this->quote($selection, $cycle, $catalog)['amount'];
    }
}
