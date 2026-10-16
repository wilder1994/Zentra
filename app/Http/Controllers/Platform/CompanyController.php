<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Geo\GeoAddressData;
use App\Enums\ArchiveReason;
use App\Enums\BillingCycle;
use App\Enums\ClientLifecycle;
use App\Enums\CommercialMetal;
use App\Enums\ManualPaymentIntent;
use App\Enums\PaymentStatus;
use App\Enums\PlatformDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ApplyAdminPlanChangeRequest;
use App\Http\Requests\Platform\ArchiveCompanyRequest;
use App\Http\Requests\Platform\CancelCompanyMembershipRequest;
use App\Http\Requests\Platform\ExpandCompanyProductRequest;
use App\Http\Requests\Platform\SchedulePackageChangeRequest;
use App\Http\Requests\Platform\StoreCompanyFirstAdminRequest;
use App\Http\Requests\Platform\StoreCompanyRequest;
use App\Http\Requests\Platform\StoreManualPaymentRequest;
use App\Http\Requests\Platform\UpdateCompanyPackageRequest;
use App\Http\Requests\Platform\UpdateCompanyPriceLockRequest;
use App\Http\Requests\Platform\UpdateCompanyProfileRequest;
use App\Http\Requests\Platform\UpdateCompanySupervisionPackageRequest;
use App\Models\CommercialPayment;
use App\Models\PlatformDocument;
use App\Models\QuotaRequest;
use App\Models\SecurityCompany;
use App\Repositories\SecurityCompanyRepository;
use App\Services\Company\CreateEmployeeCatalogItemsService;
use App\Services\Company\StoreEmployeePhotoService;
use App\Services\Platform\ApplyAdminPlanChangeService;
use App\Services\Platform\ArchiveCompanyService;
use App\Services\Platform\CancelCompanyMembershipService;
use App\Services\Platform\EnterCompanyAsSupportService;
use App\Services\Platform\ReactivateCompanyService;
use App\Services\Platform\RegisterCommercialPaymentService;
use App\Services\Platform\ScheduleCompanyPackageChangeService;
use App\Services\Platform\SuspendCompanyService;
use App\Services\Platform\UndoCompanyMembershipCancellationService;
use App\Services\Pricing\CompanyPriceHistory;
use App\Services\Pricing\CompanyRepricingService;
use App\Services\Pricing\PriceCalculator;
use App\Services\Tenant\AssignCompanyPackageService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use App\Services\Tenant\CreateCompanyFirstAdminService;
use App\Services\Tenant\CreateCompanyService;
use App\Services\Tenant\UpdateCompanyProfileService;
use App\Support\Catalog\CatalogSettings;
use App\Support\Catalog\QuotaOverview;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly SecurityCompanyRepository $securityCompanyRepository,
        private readonly AssignCompanyPackageService $assignCompanyPackageService,
        private readonly AssignCompanySupervisionPackageService $assignCompanySupervisionPackageService,
        private readonly PriceCalculator $priceCalculator,
        private readonly UpdateCompanyProfileService $updateCompanyProfileService,
        private readonly CreateCompanyService $createCompanyService,
        private readonly CreateCompanyFirstAdminService $createCompanyFirstAdminService,
        private readonly CreateEmployeeCatalogItemsService $createEmployeeCatalogItemsService,
        private readonly StoreEmployeePhotoService $storeEmployeePhotoService,
        private readonly EnterCompanyAsSupportService $enterCompanyAsSupportService,
        private readonly RegisterCommercialPaymentService $paymentService,
        private readonly CancelCompanyMembershipService $cancelCompanyMembershipService,
        private readonly UndoCompanyMembershipCancellationService $undoCompanyMembershipCancellationService,
        private readonly ScheduleCompanyPackageChangeService $scheduleCompanyPackageChangeService,
        private readonly ApplyAdminPlanChangeService $applyAdminPlanChangeService,
        private readonly SuspendCompanyService $suspendCompanyService,
        private readonly ReactivateCompanyService $reactivateCompanyService,
        private readonly ArchiveCompanyService $archiveCompanyService,
        private readonly CompanyPriceHistory $priceHistory,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('platform.companies.view'), 403);

        $companies = $this->securityCompanyRepository->paginate();
        $kpis = $this->securityCompanyRepository->companiesIndexKpis();

        return view('modules.admin.companies.index', compact('companies', 'kpis'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        return view('modules.admin.companies.create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $company = $this->createCompanyService->execute(
            $request->safe()->except(GeoAddressData::formKeys()),
            GeoAddressData::fromValidated($request->validated()),
        );

        return redirect()
            ->route('admin.companies.first-admin.create', $company)
            ->with('success', "Empresa «{$company->displayName()}» creada. Ahora crea el primer administrador.");
    }

    public function createFirstAdmin(SecurityCompany $company): View|RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        if ($company->hasCompanyAdmin()) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('warning', 'Esta empresa ya tiene un administrador.');
        }

        return view('modules.admin.companies.first-admin', array_merge(
            $this->createCompanyFirstAdminService->formOptions($company),
            [
                'company' => $company,
                'employee' => null,
                'catalogStarterUrl' => route('admin.companies.first-admin.catalog', $company),
            ],
        ));
    }

    public function previewFirstAdminCredentials(Request $request, SecurityCompany $company): JsonResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);
        abort_if($company->hasCompanyAdmin(), 404);

        $first = trim((string) $request->input('first_names', ''));
        $paternal = trim((string) $request->input('last_name_paternal', ''));
        $maternal = trim((string) $request->input('last_name_maternal', ''));
        abort_if($first === '' || ($paternal === '' && $maternal === ''), 422);

        return response()->json($this->createCompanyFirstAdminService->preview($first, $paternal, $maternal));
    }

    public function storeFirstAdminCatalog(Request $request, SecurityCompany $company): JsonResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);
        abort_if($company->hasCompanyAdmin(), 404);

        $result = $this->createEmployeeCatalogItemsService->execute((int) $company->id, $request->all());

        return response()->json([
            'collaborator_type' => $result['collaborator_type'] === null ? null : [
                'id' => $result['collaborator_type']->id,
                'name' => $result['collaborator_type']->name,
            ],
            'job_title' => $result['job_title'] === null ? null : [
                'id' => $result['job_title']->id,
                'name' => $result['job_title']->name,
            ],
            'message' => 'Ya puedes seleccionar el tipo y el cargo.',
        ]);
    }

    public function storeFirstAdmin(StoreCompanyFirstAdminRequest $request, SecurityCompany $company): RedirectResponse
    {
        $result = $this->createCompanyFirstAdminService->execute(
            $company,
            $request->user(),
            $request->validated(),
        );

        if ($request->file('photo') !== null) {
            $this->storeEmployeePhotoService->store($result['employee'], $request->file('photo'));
        }

        return redirect()
            ->route('admin.companies.show', $company)
            ->with([
                'success' => 'Primer administrador creado.',
                'issued_login' => $result['user']->username,
                'issued_password' => $result['password'],
            ]);
    }

    public function show(Request $request, SecurityCompany $company): View|RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.view'), 403);

        if (! $company->hasCompanyAdmin() && auth()->user()?->can('platform.companies.manage')) {
            return redirect()->route('admin.companies.first-admin.create', $company);
        }

        $company->loadCount('clients')
            ->loadCount([
                'clients as operational_clients_count' => fn ($q) => $q->where('lifecycle', ClientLifecycle::Active),
            ]);

        $cycleOptions = BillingCycle::options();

        $catalogAmount = app(CompanyRepricingService::class)->quoteAmount($company);

        $pendingPaymentsCount = CommercialPayment::query()
            ->where('security_company_id', $company->id)
            ->where('status', PaymentStatus::Pending)
            ->count();

        $maxClients = max(1, (int) $company->max_clients);
        $operational = (int) ($company->operational_clients_count ?? 0);
        $quotaPct = (int) min(100, round(($operational / $maxClients) * 100));

        $daysToRenewal = null;
        if ($company->package_ends_at !== null) {
            $daysToRenewal = (int) CarbonImmutable::now()->startOfDay()
                ->diffInDays(CarbonImmutable::parse($company->package_ends_at)->startOfDay(), false);
        }

        $catalog = CatalogSettings::current();

        return view('modules.admin.companies.show', [
            'company' => $company,
            'cycleOptions' => $cycleOptions,
            'planCatalog' => $catalog->forClient(true),
            'metals' => CommercialMetal::cases(),
            'modules' => $catalog->moduleLabels(),
            'movements' => $this->recentMovements($company),
            'quotaOverview' => QuotaOverview::for($company),
            'quotaRequests' => QuotaRequest::query()
                ->where('security_company_id', $company->id)
                ->with(['requester:id,name', 'installation', 'post'])
                ->orderByRaw("status = 'pending' desc")
                ->latest('id')
                ->limit(20)
                ->get(),
            'pendingRepriceAmount' => $company->hasPendingReprice() ? $catalogAmount : null,
            'catalogAmount' => $catalogAmount,
            'pendingPaymentsCount' => $pendingPaymentsCount,
            'quotaPct' => $quotaPct,
            'daysToRenewal' => $daysToRenewal,
            'hasAcceptance' => $company->hasCompletedAcceptance(),
            'isUpToDate' => $company->isUpToDate(),
        ]);
    }

    /**
     * Últimos pagos y cambios de precio, mezclados por fecha.
     *
     * @return Collection<int, array{date: CarbonInterface, kind: string, title: string, detail: ?string, amount: float, previous: ?float, tone: string}>
     */
    private function recentMovements(SecurityCompany $company, int $limit = 6): Collection
    {
        $payments = CommercialPayment::query()
            ->where('security_company_id', $company->id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CommercialPayment $payment) => [
                'date' => $payment->paid_at ?? $payment->created_at,
                'kind' => 'payment',
                'title' => 'Pago '.mb_strtolower($payment->status?->label() ?? ''),
                'detail' => $payment->method?->label(),
                'amount' => (float) $payment->amount,
                'previous' => null,
                'tone' => match ($payment->status) {
                    PaymentStatus::Completed => 'success',
                    PaymentStatus::Pending => 'warning',
                    default => 'danger',
                },
            ]);

        $prices = $company->priceChanges()
            ->latest('effective_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($change) => [
                'date' => $change->effective_at,
                'kind' => 'price',
                'title' => $change->reasonLabel(),
                'detail' => trim(($change->new_plan ?? '').($change->note ? ' · '.$change->note : ''), ' ·') ?: null,
                'amount' => (float) $change->new_amount,
                'previous' => (float) $change->previous_amount,
                'tone' => 'info',
            ]);

        return $payments->concat($prices)
            ->sortByDesc(fn (array $row) => $row['date']?->getTimestamp() ?? 0)
            ->take($limit)
            ->values();
    }

    public function historial(SecurityCompany $company): View
    {
        abort_unless(auth()->user()?->can('platform.companies.view'), 403);

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

        $completedCount = $payments->where('status', PaymentStatus::Completed)->count();
        $pendingCount = $payments->where('status', PaymentStatus::Pending)->count();
        $invoicesCount = $invoices->count();

        return view('modules.admin.companies.historial', [
            'company' => $company,
            'payments' => $payments,
            'invoices' => $invoices,
            'invoiceByPaymentId' => $invoiceByPaymentId,
            'timeline' => $timeline,
            'completedCount' => $completedCount,
            'pendingCount' => $pendingCount,
            'invoicesCount' => $invoicesCount,
        ]);
    }

    public function enterAsSupport(SecurityCompany $company): RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        $this->enterCompanyAsSupportService->enter(auth()->user(), $company);

        return redirect()
            ->route('company.dashboard')
            ->with('success', "Entraste como «{$company->displayName()}».");
    }

    public function exitSupport(): RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        $companyId = $this->enterCompanyAsSupportService->exit(auth()->user());

        if ($companyId !== null) {
            return redirect()
                ->route('admin.companies.show', $companyId)
                ->with('success', 'Saliste del modo soporte.');
        }

        return redirect()
            ->route('admin.companies.index')
            ->with('success', 'Saliste del modo soporte.');
    }

    public function storeManualPayment(
        StoreManualPaymentRequest $request,
        SecurityCompany $company,
    ): RedirectResponse {
        try {
            $intent = ManualPaymentIntent::from($request->validated('intent'));
            $this->paymentService->executeManual(
                $company,
                $request->user(),
                $request->validated('reference'),
                $request->file('proof'),
                $intent,
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('admin.companies.historial', $company)
            ->with('success', 'Pago manual registrado. Factura demo generada.');
    }

    public function cancelMembership(
        CancelCompanyMembershipRequest $request,
        SecurityCompany $company,
    ): RedirectResponse {
        try {
            $this->cancelCompanyMembershipService->execute(
                $company,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', 'Membresía cancelada. El acceso continúa hasta el fin del periodo contratado.');
    }

    public function undoMembershipCancellation(SecurityCompany $company): RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        try {
            $this->undoCompanyMembershipCancellationService->execute($company, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('warning', $e->getMessage());
        }

        $ends = $company->fresh()->package_ends_at?->format('d/m/Y');

        return redirect()
            ->route('admin.companies.show', $company)
            ->with(
                'success',
                $ends
                    ? "Cancelación deshecha. La membresía sigue activa hasta el {$ends}."
                    : 'Cancelación deshecha. La membresía quedó activa.',
            );
    }

    public function applyPlan(ApplyAdminPlanChangeRequest $request, SecurityCompany $company): RedirectResponse
    {
        try {
            $onDate = $request->filled('effective_on')
                ? CarbonImmutable::parse($request->validated('effective_on'))
                : null;

            $result = $this->applyAdminPlanChangeService->execute(
                $company,
                BillingCycle::from($request->validated('billing_cycle')),
                $request->planSelection($company),
                (string) $request->validated('apply_when'),
                $onDate,
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        $when = $result['effective_at']->format('d/m/Y');

        return redirect()
            ->route('admin.companies.show', $company)
            ->with(
                'success',
                $result['applied']
                    ? "Plan aplicado. El cupo rige desde el {$when}."
                    : "Plan programado. Aplica el {$when}.",
            );
    }

    public function expandProduct(ExpandCompanyProductRequest $request, SecurityCompany $company): RedirectResponse
    {
        $product = $request->product();

        try {
            $result = $this->applyAdminPlanChangeService->execute(
                $company,
                $company->billing_cycle ?? BillingCycle::Monthly,
                $request->expandedSelection($company),
                (string) $request->validated('apply_when'),
                $request->filled('effective_on') ? CarbonImmutable::parse($request->validated('effective_on')) : null,
                (float) $request->validated('agreed_amount'),
                $product,
                (string) $request->validated('expand_note'),
                'quota_expansion',
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        $qty = number_format((int) $request->validated('quantity'), 0, ',', '.');
        $when = $result['effective_at']->format('d/m/Y');

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', $result['applied']
                ? "{$product->label()} ampliado en {$qty}. Rige desde el {$when}."
                : "Ampliación de {$product->label()} (+{$qty}) programada para el {$when}.");
    }

    public function schedulePackageChange(
        SchedulePackageChangeRequest $request,
        SecurityCompany $company,
    ): RedirectResponse {
        try {
            $this->scheduleCompanyPackageChangeService->scheduleWithManualPayment(
                $company,
                $request->user(),
                BillingCycle::from($request->validated('billing_cycle')),
                $request->validated('reference'),
                $request->file('proof'),
                $request->planSelection($company),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', 'Cambio de plan programado. Se aplicará al finalizar el periodo actual.');
    }

    public function updatePackage(UpdateCompanyPackageRequest $request, SecurityCompany $company): RedirectResponse
    {
        $cycle = BillingCycle::from($request->validated('billing_cycle'));
        $seats = $request->accessSeats();
        $before = $this->priceHistory->snapshot($company);
        $this->assignCompanyPackageService->execute($company, $seats->sku(), $cycle, null, $seats);
        $this->priceHistory->record($company, $before, 'admin');

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', "Paquete actualizado a «{$seats->label()}» ({$cycle->label()}).");
    }

    public function updateSupervisionPackage(
        UpdateCompanySupervisionPackageRequest $request,
        SecurityCompany $company,
    ): RedirectResponse {
        $sku = $request->supervisionSku();
        $before = $this->priceHistory->snapshot($company);
        $this->assignCompanySupervisionPackageService->execute($company, $sku);
        $this->priceHistory->record($company, $before, 'admin');

        $label = $sku?->label() ?? 'sin Supervisión';

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', "Paquete de Supervisión actualizado: {$label}.");
    }

    public function updatePriceLock(UpdateCompanyPriceLockRequest $request, SecurityCompany $company): RedirectResponse
    {
        $locked = $request->boolean('price_locked');
        $company->update([
            'price_locked' => $locked,
            'price_locked_until' => $locked ? $request->validated('price_locked_until') : null,
            'price_lock_note' => $locked ? $request->validated('price_lock_note') : null,
        ]);

        if ($locked && $company->pending_reprice_at !== null) {
            $company->update([
                'pending_reprice_at' => null,
                'pending_reprice_reason' => null,
                'pending_reprice_note' => null,
            ]);
        }

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', $locked
                ? 'Precio pactado activo: la empresa queda fuera de cambios de catálogo e incrementos.'
                : 'Precio pactado retirado.');
    }

    public function cutService(SecurityCompany $company): RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        if (! $company->is_active || $company->archived_at !== null) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('warning', 'El servicio de esta empresa ya está cortado o archivado.');
        }

        $this->suspendCompanyService->execute($company, null, 'Corte inmediato de servicio');

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', "Servicio cortado para «{$company->displayName()}». Solo el admin empresa puede entrar, en solo lectura.");
    }

    public function reactivateService(SecurityCompany $company): RedirectResponse
    {
        abort_unless(auth()->user()?->can('platform.companies.manage'), 403);

        if ($company->is_active && $company->archived_at === null) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('warning', 'El servicio de esta empresa ya está activo.');
        }

        $this->reactivateCompanyService->execute($company);

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', "Servicio reactivado para «{$company->displayName()}».");
    }

    public function archiveCompany(ArchiveCompanyRequest $request, SecurityCompany $company): RedirectResponse
    {
        if ($company->archived_at !== null) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('warning', 'Esta empresa ya está archivada.');
        }

        $reason = ArchiveReason::from($request->validated('archive_reason'));
        $this->archiveCompanyService->execute($company, $reason);

        return redirect()
            ->route('admin.companies.show', $company)
            ->with('success', "Empresa «{$company->displayName()}» archivada ({$reason->label()}).");
    }

    public function editProfile(SecurityCompany $company): View
    {
        abort_unless(auth()->user()?->can('updateProfile', $company), 403);

        return view('modules.admin.companies.profile', [
            'company' => $company,
            'logoPreviewUrl' => $company->logo_path
                ? route('admin.companies.logo', $company).'?v='.$company->updated_at?->timestamp
                : null,
        ]);
    }

    public function logo(SecurityCompany $company): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('updateProfile', $company), 403);

        return $company->logoFileResponse() ?? abort(404);
    }

    public function updateProfile(UpdateCompanyProfileRequest $request, SecurityCompany $company): RedirectResponse
    {
        $this->updateCompanyProfileService->assertTaxIdImmutable($company, $request->input('tax_id'));

        $this->updateCompanyProfileService->execute(
            $company,
            $request->safe()->except([...GeoAddressData::formKeys(), 'logo', 'remove_logo']),
            GeoAddressData::fromValidated($request->validated()),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return redirect()
            ->route('admin.companies.profile.edit', $company)
            ->with('success', 'Perfil de empresa actualizado.');
    }
}
