<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ApplyPriceIncreaseRequest;
use App\Http\Requests\Platform\UpdatePlatformPricingRequest;
use App\Models\SecurityCompany;
use App\Services\Pricing\CompanyRepricingService;
use App\Services\Pricing\UpdatePlatformPricingService;
use App\Support\Catalog\CatalogPricer;
use App\Support\Catalog\CatalogSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PricingController extends Controller
{
    public function __construct(
        private readonly UpdatePlatformPricingService $updatePlatformPricingService,
        private readonly CompanyRepricingService $repricingService,
    ) {}

    public function edit(Request $request): View
    {
        abort_unless(auth()->user()?->can('platform.companies.view'), 403);

        $cycle = BillingCycle::tryFrom((string) $request->query('cycle', 'monthly'))
            ?? BillingCycle::Monthly;
        $pricer = CatalogPricer::make();
        $catalog = $pricer->catalog()->toForm();
        $accessMatrix = $pricer->matrix(CommercialProduct::Access, $cycle);
        $supervisionMatrix = $pricer->matrix(CommercialProduct::Supervision, $cycle);
        $indexingMatrix = $pricer->matrix(CommercialProduct::Indexing, $cycle);
        $indexingAddonMatrix = $pricer->matrix(CommercialProduct::Indexing, $cycle, true);
        $observatoryMatrix = $pricer->observatoryMatrix($cycle);
        $annualDiscount = (float) config('tenancy.pricing.annual_discount', 0.17);
        $moduleOptions = CatalogSettings::defaults()['module_options'] ?? [];
        $lockedCount = SecurityCompany::query()->where('price_locked', true)->count();
        $pendingCount = SecurityCompany::query()->whereNotNull('pending_reprice_at')->count();

        return view('modules.admin.pricing.edit', compact(
            'cycle',
            'catalog',
            'accessMatrix',
            'supervisionMatrix',
            'indexingMatrix',
            'indexingAddonMatrix',
            'observatoryMatrix',
            'annualDiscount',
            'moduleOptions',
            'lockedCount',
            'pendingCount',
        ));
    }

    public function update(UpdatePlatformPricingRequest $request): RedirectResponse
    {
        $this->updatePlatformPricingService->execute($request->validated(), $request->user());

        $message = 'Planes actualizados para nuevas empresas.';
        if ($request->validated('apply_scope') === UpdatePlatformPricingRequest::SCOPE_CURRENT) {
            $result = $this->repricingService->scheduleAll(
                CarbonImmutable::now(),
                CompanyRepricingService::REASON_CATALOG,
                'Cambio de catálogo',
            );
            $message = "Planes actualizados. {$result['scheduled']} empresa(s) pasan al nuevo valor desde su próxima facturación";
            $message .= $result['locked'] > 0 ? "; {$result['locked']} con precio pactado se mantienen." : '.';
        }

        return redirect()
            ->route('admin.pricing.edit')
            ->with('success', $message);
    }

    public function previewIncrease(ApplyPriceIncreaseRequest $request): JsonResponse
    {
        $pct = (float) $request->validated('percent');
        $from = CarbonImmutable::parse($request->validated('effective_on'));
        $rows = $this->repricingService->preview(CatalogSettings::current()->withIncrease($pct), $from);

        return response()->json([
            'rows' => $rows,
            'affected' => count(array_filter($rows, static fn (array $r) => ! $r['locked'])),
            'locked' => count(array_filter($rows, static fn (array $r) => $r['locked'])),
            'current_total' => round(array_sum(array_column($rows, 'current')), 2),
            'new_total' => round(array_sum(array_column($rows, 'new')), 2),
        ]);
    }

    public function applyIncrease(ApplyPriceIncreaseRequest $request): RedirectResponse
    {
        $pct = (float) $request->validated('percent');
        $from = CarbonImmutable::parse($request->validated('effective_on'));

        $this->updatePlatformPricingService->increase($pct, $request->user());
        $result = $this->repricingService->scheduleAll(
            $from,
            CompanyRepricingService::REASON_INCREASE,
            'Incremento anual '.rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',').'%',
        );

        $message = "Incremento del {$pct}% aplicado al catálogo. {$result['scheduled']} empresa(s) lo verán desde su primera facturación a partir del {$from->format('d/m/Y')}";
        $message .= $result['locked'] > 0 ? "; {$result['locked']} con precio pactado quedan igual." : '.';

        return redirect()->route('admin.pricing.edit')->with('success', $message);
    }
}
