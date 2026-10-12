<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Models\PricingSettings;
use App\Models\User;
use App\Support\Catalog\CatalogSettings;

final class UpdatePlatformPricingService
{
    /** @param array<string, mixed> $payload */
    public function execute(array $payload, ?User $actor = null): PricingSettings
    {
        $settings = PricingSettings::current();
        $units = $payload['units'] ?? [];
        $defaults = CatalogSettings::defaults()['default_units'] ?? [];

        $discounts = [];
        foreach (CommercialProduct::cases() as $product) {
            foreach (CommercialMetal::cases() as $metal) {
                $pct = (float) data_get($payload, "discounts.{$product->value}.{$metal->value}", 0);
                $discounts[$product->value][$metal->value] = round($pct / 100, 4);
            }
        }

        $modules = [];
        foreach (CommercialProduct::cases() as $product) {
            $mods = $payload['modules'][$product->value] ?? [];
            $modules[$product->value] = array_values(array_unique(array_filter($mods)));
        }

        $availability = [];
        foreach ([...array_column(CommercialMetal::cases(), 'value'), 'hardware'] as $key) {
            $availability[$key] = (bool) data_get($payload, "availability.{$key}", false);
        }

        $unitValues = [];
        foreach (array_keys($defaults) as $key) {
            $unitValues[$key] = (float) ($units[$key] ?? $defaults[$key]);
        }

        $settings->update([
            'unit_price_manual' => $unitValues['access'],
            'unit_price_hardware' => $unitValues['access_hardware'] ?? $settings->unit_price_hardware,
            'unit_price_supervision' => $unitValues['supervision'],
            'catalog' => [
                'units' => $unitValues,
                'discounts' => $discounts,
                'combo_discount' => round((float) ($payload['combo_discount'] ?? 0) / 100, 4),
                'modules' => $modules,
                'availability' => $availability,
            ],
            'updated_by' => $actor?->id,
        ]);

        return $settings->fresh();
    }

    /** Sube las unidades un % para nuevas empresas (descuentos y disponibilidad iguales). */
    public function increase(float $pct, ?User $actor = null): PricingSettings
    {
        $settings = PricingSettings::current();
        $raised = CatalogSettings::current()->withIncrease($pct)->toForm();

        $settings->update([
            'unit_price_manual' => $raised['units']['access'],
            'unit_price_hardware' => $raised['units']['access_hardware'] ?? $settings->unit_price_hardware,
            'unit_price_supervision' => $raised['units']['supervision'],
            'catalog' => array_merge(is_array($settings->catalog) ? $settings->catalog : [], [
                'units' => $raised['units'],
                'discounts' => $raised['discounts'],
                'combo_discount' => $raised['combo_discount'],
                'availability' => $raised['availability'],
                'modules' => $raised['modules'],
            ]),
            'updated_by' => $actor?->id,
        ]);

        return $settings->fresh();
    }
}
