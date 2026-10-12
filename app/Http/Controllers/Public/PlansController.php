<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Http\Controllers\Controller;
use App\Support\Catalog\CatalogPricer;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlansController extends Controller
{
    public function index(Request $request): View
    {
        $cycle = BillingCycle::tryFrom((string) $request->query('cycle', 'monthly'))
            ?? BillingCycle::Monthly;
        $pricer = CatalogPricer::make();
        $accessMatrix = $pricer->matrix(CommercialProduct::Access, $cycle, false, true);
        $supervisionMatrix = $pricer->matrix(CommercialProduct::Supervision, $cycle, false, true);
        $indexingMatrix = $pricer->matrix(CommercialProduct::Indexing, $cycle, false, true);
        $indexingAddonMatrix = $pricer->matrix(CommercialProduct::Indexing, $cycle, true, true);
        $observatoryMatrix = $pricer->observatoryMatrix($cycle, true);
        $annualDiscount = (float) config('tenancy.pricing.annual_discount', 0.17);
        $planCatalog = $pricer->catalog()->forClient();

        return view('modules.public.plans.index', compact(
            'cycle',
            'accessMatrix',
            'supervisionMatrix',
            'indexingMatrix',
            'indexingAddonMatrix',
            'observatoryMatrix',
            'annualDiscount',
            'planCatalog',
        ));
    }
}
