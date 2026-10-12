<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Support\Catalog\CatalogSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePlatformPricingRequest extends FormRequest
{
    public const SCOPE_NEW = 'new';

    public const SCOPE_CURRENT = 'current';

    public function authorize(): bool
    {
        return $this->user()?->can('platform.companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $metals = array_column(CommercialMetal::cases(), 'value');
        $moduleKeys = array_keys(CatalogSettings::defaults()['module_options'] ?? []);

        $rules = [
            'units.access' => ['required', 'numeric', 'min:1000'],
            'units.access_hardware' => ['required', 'numeric', 'min:0'],
            'units.supervision' => ['required', 'numeric', 'min:0'],
            'units.indexing' => ['required', 'numeric', 'min:0'],
            'units.indexing_addon' => ['required', 'numeric', 'min:0'],
            'apply_scope' => ['required', Rule::in([self::SCOPE_NEW, self::SCOPE_CURRENT])],
            'availability' => ['nullable', 'array'],
        ];

        foreach ([...$metals, 'hardware'] as $key) {
            $rules["availability.{$key}"] = ['nullable', 'boolean'];
        }

        foreach ([CommercialProduct::Access, CommercialProduct::Supervision, CommercialProduct::Indexing] as $product) {
            foreach ($metals as $metal) {
                $rules["discounts.{$product->value}.{$metal}"] = ['required', 'numeric', 'min:0', 'max:90'];
            }
            $rules["modules.{$product->value}"] = ['nullable', 'array'];
            $rules["modules.{$product->value}.*"] = ['string', Rule::in($moduleKeys)];
        }

        foreach ($metals as $metal) {
            $rules["observatory.{$metal}"] = ['required', 'numeric', 'min:0'];
        }

        $rules['modules.observatory'] = ['nullable', 'array'];
        $rules['modules.observatory.*'] = ['string', Rule::in($moduleKeys)];

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'units.access' => 'unidad Accesos',
            'units.access_hardware' => 'unidad Accesos con hardware',
            'units.supervision' => 'unidad Supervisión',
            'units.indexing' => 'unidad Indexación sola',
            'units.indexing_addon' => 'unidad Indexación add-on',
            'apply_scope' => 'a quién aplica',
        ];
    }
}
