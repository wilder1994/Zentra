<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Enums\CompanyPackageSku;
use App\Models\PricingSettings;

final class CatalogSettings
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly array $data = [],
        private readonly string $currency = 'COP',
    ) {}

    public static function current(): self
    {
        $settings = PricingSettings::current();
        $stored = is_array($settings->catalog) ? $settings->catalog : [];

        return new self($stored, (string) ($settings->currency ?: 'COP'));
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $fromConfig = config('catalog');
        if (is_array($fromConfig) && isset($fromConfig['default_units'])) {
            return $fromConfig;
        }

        return require base_path('config/catalog.php');
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function unit(string $key): float
    {
        $defaults = self::defaults()['default_units'] ?? [];

        return (float) ($this->data['units'][$key] ?? $defaults[$key] ?? 0);
    }

    public function discount(CommercialProduct $product, CommercialMetal $metal): float
    {
        $defaults = self::defaults()['default_discounts'][$product->value] ?? [];
        $value = $this->data['discounts'][$product->value][$metal->value] ?? $defaults[$metal->value] ?? 0;

        return max(0, min(0.9, (float) $value));
    }

    public function observatoryPrice(CommercialMetal $metal): float
    {
        $defaults = self::defaults()['default_observatory'] ?? [];

        return (float) ($this->data['observatory'][$metal->value] ?? $defaults[$metal->value] ?? 0);
    }

    /** `bronce|plata|oro|platino|hardware` */
    public function isAvailable(string $key): bool
    {
        $defaults = self::defaults()['default_availability'] ?? [];

        return (bool) ($this->data['availability'][$key] ?? $defaults[$key] ?? true);
    }

    public function isMetalAvailable(CommercialMetal $metal): bool
    {
        return $this->isAvailable($metal->value);
    }

    public function hardwareAvailable(): bool
    {
        return $this->isAvailable('hardware');
    }

    /** Mayor cupo vendible al público: hasta el último plan disponible consecutivo. */
    public function maxPublicSeats(): int
    {
        $max = 0;
        foreach (CommercialMetal::cases() as $metal) {
            if (! $this->isMetalAvailable($metal)) {
                break;
            }
            $max = $metal === CommercialMetal::Platino ? CompanyPackageSku::MAX_SEATS : $metal->packSize();
        }

        return $max;
    }

    /** @return list<string> */
    public function modules(CommercialProduct $product): array
    {
        $defaults = self::defaults()['default_modules'][$product->value] ?? [];
        $stored = $this->data['modules'][$product->value] ?? null;
        if (! is_array($stored) || $stored === []) {
            return $defaults;
        }

        $allowed = array_keys(self::defaults()['module_options'] ?? []);

        return array_values(array_intersect($stored, $allowed));
    }

    /** Copia con unidades y Observatorio subidos un %; descuentos y módulos iguales. */
    public function withIncrease(float $pct): self
    {
        $factor = 1 + ($pct / 100);
        $form = $this->toForm();
        $data = $this->data;

        foreach ($form['units'] as $key => $value) {
            $data['units'][$key] = round($value * $factor, 0);
        }
        foreach ($form['observatory'] as $metal => $value) {
            $data['observatory'][$metal] = round($value * $factor, 0);
        }

        return new self($data, $this->currency);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->toForm();
    }

    /**
     * @return array{
     *   units: array<string, float>,
     *   discounts: array<string, array<string, float>>,
     *   observatory: array<string, float>,
     *   modules: array<string, list<string>>,
     *   availability: array<string, bool>
     * }
     */
    public function toForm(): array
    {
        $units = [];
        foreach (array_keys(self::defaults()['default_units'] ?? []) as $key) {
            $units[$key] = $this->unit($key);
        }

        $discounts = [];
        foreach ([CommercialProduct::Access, CommercialProduct::Supervision, CommercialProduct::Indexing] as $product) {
            foreach (CommercialMetal::cases() as $metal) {
                $discounts[$product->value][$metal->value] = $this->discount($product, $metal);
            }
        }

        $observatory = [];
        foreach (CommercialMetal::cases() as $metal) {
            $observatory[$metal->value] = $this->observatoryPrice($metal);
        }

        $modules = [];
        foreach (CommercialProduct::cases() as $product) {
            $modules[$product->value] = $this->modules($product);
        }

        $availability = [];
        foreach (array_keys(self::defaults()['default_availability'] ?? []) as $key) {
            $availability[$key] = $this->isAvailable($key);
        }

        return compact('units', 'discounts', 'observatory', 'modules', 'availability');
    }

    /** Datos que usa el selector de instalaciones en el navegador. */
    public function forClient(bool $includeUnavailable = false): array
    {
        $metals = [];
        foreach (CommercialMetal::cases() as $metal) {
            $metals[] = [
                'key' => $metal->value,
                'label' => $metal->label(),
                'min' => $metal->rangeMin(),
                'max' => $metal->packSize(),
                'employees' => $metal->employeeCap(),
                'available' => $includeUnavailable || $this->isMetalAvailable($metal),
                'discounts' => [
                    'access' => $this->discount(CommercialProduct::Access, $metal),
                    'supervision' => $this->discount(CommercialProduct::Supervision, $metal),
                ],
            ];
        }

        return [
            'units' => [
                'access' => $this->unit('access'),
                'access_hardware' => $this->unit('access_hardware'),
                'supervision' => $this->unit('supervision'),
            ],
            'metals' => $metals,
            'hardware' => $includeUnavailable || $this->hardwareAvailable(),
            'annual_discount' => (float) config('tenancy.pricing.annual_discount', 0.17),
            'max_seats' => $includeUnavailable ? CompanyPackageSku::MAX_SEATS : $this->maxPublicSeats(),
        ];
    }
}
