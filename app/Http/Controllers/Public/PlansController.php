<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\CommercialMetal;
use App\Enums\CommercialProduct;
use App\Http\Controllers\Controller;
use App\Support\Catalog\CatalogSettings;
use Illuminate\View\View;

final class PlansController extends Controller
{
    public function index(): View
    {
        $catalog = CatalogSettings::current();
        $moduleOptions = CatalogSettings::defaults()['module_options'] ?? [];

        $modules = [];
        foreach (CommercialProduct::cases() as $product) {
            $modules[$product->value] = array_values(array_map(
                fn (string $key) => $moduleOptions[$key] ?? $key,
                $catalog->modules($product),
            ));
        }

        return view('modules.public.plans.index', [
            'planCatalog' => $catalog->forClient(),
            'metals' => array_values(array_filter(
                CommercialMetal::cases(),
                fn (CommercialMetal $metal) => $catalog->isMetalAvailable($metal),
            )),
            'modules' => $modules,
            'annualDiscount' => (float) config('tenancy.pricing.annual_discount', 0.17),
            'comboDiscount' => $catalog->comboDiscount(),
        ]);
    }

    public function cart(): View
    {
        $catalog = CatalogSettings::current();

        return view('modules.public.plans.cart', [
            'planCatalog' => $catalog->forClient(),
            'annualDiscount' => (float) config('tenancy.pricing.annual_discount', 0.17),
            'comboDiscount' => $catalog->comboDiscount(),
        ]);
    }
}
