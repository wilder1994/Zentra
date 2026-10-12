<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Enums\CompanyPackageSku;
use App\Enums\ManualPaymentIntent;
use App\Enums\PaymentStatus;
use App\Enums\PlatformDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CancelMembershipRequest;
use App\Http\Requests\Company\SchedulePackageChangeRequest;
use App\Http\Requests\Company\UpdateSupervisionPackageRequest;
use App\Models\CommercialPayment;
use App\Models\PlatformDocument;
use App\Models\QuotaRequest;
use App\Models\SecurityCompany;
use App\Services\Platform\CancelCompanyMembershipService;
use App\Services\Platform\ScheduleCompanyPackageChangeService;
use App\Services\Platform\UndoCompanyMembershipCancellationService;
use App\Services\Pricing\CompanyRepricingService;
use App\Services\Pricing\PlanQuoter;
use App\Services\Tenant\PurchaseCompanySupervisionPackageService;
use App\Support\Catalog\CatalogSettings;
use App\Support\Catalog\QuotaOverview;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BillingController extends Controller
{
    public function __construct(
        private readonly CancelCompanyMembershipService $cancelCompanyMembershipService,
        private readonly UndoCompanyMembershipCancellationService $undoCompanyMembershipCancellationService,
        private readonly ScheduleCompanyPackageChangeService $scheduleCompanyPackageChangeService,
        private readonly PurchaseCompanySupervisionPackageService $purchaseCompanySupervisionPackageService,
        private readonly PlanQuoter $planQuoter,
        private readonly CompanyRepricingService $repricingService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('company.billing.manage'), 403);

        $company = $this->resolveCompany($request);
        $company->loadMissing('subscriptionAcceptances');

        $payments = CommercialPayment::query()
            ->where('security_company_id', $company->id)
            ->latest('created_at')
            ->get();

        $invoices = PlatformDocument::query()
            ->where('security_company_id', $company->id)
            ->where('type', PlatformDocumentType::Invoice)
            ->latest('issued_at')
            ->latest('id')
            ->get();

        $invoiceByPaymentId = $invoices
            ->filter(fn (PlatformDocument $doc) => ! empty($doc->metadata['payment_id']))
            ->keyBy(fn (PlatformDocument $doc) => (int) $doc->metadata['payment_id']);

        $timeline = $company->lifecycleEvidenceEvents()
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $pendingPayment = $payments
            ->first(fn (CommercialPayment $p) => $p->status === PaymentStatus::Pending && $p->gateway_driver === 'local');

        $selection = PlanSelection::fromCompany($company);

        $isUpToDate = $company->isUpToDate();
        $defaultCheckoutIntent = match (true) {
            $company->needsPaidReactivation() => ManualPaymentIntent::Reactivate->value,
            $isUpToDate => ManualPaymentIntent::Anticipate->value,
            default => ManualPaymentIntent::Renew->value,
        };

        return view('modules.company.billing.index', [
            'company' => $company,
            'hasAcceptance' => $company->hasCompletedAcceptance(),
            'payments' => $payments,
            'invoices' => $invoices,
            'invoiceByPaymentId' => $invoiceByPaymentId,
            'timeline' => $timeline,
            'completedCount' => $payments->where('status', PaymentStatus::Completed)->count(),
            'pendingCount' => $payments->where('status', PaymentStatus::Pending)->count(),
            'invoicesCount' => $invoices->count(),
            'pendingPayment' => $pendingPayment,
            'isUpToDate' => $isUpToDate,
            'planQuote' => $this->planQuoter->quote($selection, $company->billing_cycle ?? BillingCycle::Monthly),
            'planQuoteAnnual' => $this->planQuoter->quote($selection, BillingCycle::Annual),
            'planCatalog' => $this->companyCatalog($company),
            'pendingRepriceAmount' => $company->hasPendingReprice()
                ? $this->repricingService->quoteAmount($company)
                : null,
            'cycleOptions' => BillingCycle::options(),
            'defaultCheckoutIntent' => $defaultCheckoutIntent,
            'gatewayDriver' => config('billing.gateway.driver'),
            'quotaOverview' => QuotaOverview::for($company),
            'quotaRequests' => QuotaRequest::query()
                ->where('security_company_id', $company->id)
                ->with(['installation', 'post'])
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function cancelMembership(CancelMembershipRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        try {
            $this->cancelCompanyMembershipService->execute(
                $company,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('company.billing.index')
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('company.billing.index')
            ->with('success', 'Membresía cancelada. El acceso continúa hasta el fin del periodo contratado.');
    }

    public function undoCancellation(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('company.billing.manage'), 403);

        $company = $this->resolveCompany($request);

        try {
            $this->undoCompanyMembershipCancellationService->execute($company, $request->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('company.billing.index')
                ->with('warning', $e->getMessage());
        }

        $ends = $company->fresh()->package_ends_at?->format('d/m/Y');

        return redirect()
            ->route('company.billing.index')
            ->with(
                'success',
                $ends
                    ? "Cancelación deshecha. La membresía sigue activa hasta el {$ends}."
                    : 'Cancelación deshecha. La membresía quedó activa.',
            );
    }

    public function schedulePackageChange(SchedulePackageChangeRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        try {
            $payment = $this->scheduleCompanyPackageChangeService->scheduleWithLocalCheckout(
                $company,
                $request->user(),
                BillingCycle::from($request->validated('billing_cycle')),
                $request->planSelection($company),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()
                ->route('company.billing.index')
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        return redirect()->route('billing.checkout.show', $payment);
    }

    public function updateSupervisionPackage(UpdateSupervisionPackageRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);
        $sku = $request->supervisionSku();

        try {
            $result = $this->purchaseCompanySupervisionPackageService->execute(
                $company,
                $request->user(),
                $sku,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()
                ->route('company.billing.index')
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        if ($result instanceof CommercialPayment) {
            return redirect()->route('billing.checkout.show', $result);
        }

        $label = $sku?->label() ?? 'sin Supervisión';

        return redirect()
            ->route('company.billing.index')
            ->with('success', "Supervisión programada al corte: {$label}.");
    }

    /** Catálogo visible para la empresa: planes disponibles + el que ya tiene (aunque no esté a la venta). */
    private function companyCatalog(SecurityCompany $company): array
    {
        $data = CatalogSettings::current()->forClient();
        $owned = PlanSelection::fromCompany($company);
        $keep = array_filter(array_map(
            fn (CommercialProduct $product) => $owned->metal($product)?->value,
            CommercialProduct::cases(),
        ));

        foreach ($data['metals'] as $i => $metal) {
            if (in_array($metal['key'], $keep, true)) {
                $data['metals'][$i]['available'] = true;
            }
        }
        if ((int) ($company->package_hardware_seats ?? 0) > 0) {
            $data['hardware'] = true;
        }

        $max = 0;
        foreach ($data['metals'] as $metal) {
            if ($metal['available']) {
                $max = $metal['key'] === 'platino' ? CompanyPackageSku::MAX_SEATS : $metal['max'];
            }
        }
        $data['max_seats'] = $max;

        return $data;
    }

    private function resolveCompany(Request $request): SecurityCompany
    {
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());

        return SecurityCompany::query()->findOrFail($companyId);
    }
}
