<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Http\Controllers\Controller;
use App\Support\Catalog\CatalogPricer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WelcomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('home');
        }

        $pricer = CatalogPricer::make();
        $accessMatrix = $pricer->matrix(CommercialProduct::Access, BillingCycle::Monthly, true);
        $minMonthlyAmount = (float) ($accessMatrix[0]['from_monthly'] ?? $pricer->seatsMonthly(CommercialProduct::Access, 1));
        $annualDiscount = (float) config('tenancy.pricing.annual_discount', 0.17);

        return view('welcome', compact('accessMatrix', 'minMonthlyAmount', 'annualDiscount'));
    }
}
