<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\LegalCorpusType;
use App\Enums\ManualPaymentIntent;
use App\Enums\PlatformDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PublishLegalCorpusVersionRequest;
use App\Http\Requests\Platform\StoreManualPaymentRequest;
use App\Http\Requests\Platform\StoreSubscriptionAcceptanceRequest;
use App\Models\DocumentRetentionSeries;
use App\Models\IdentityDocumentType;
use App\Models\LegalCorpusVersion;
use App\Models\SecurityCompany;
use App\Services\Platform\PlatformDocumentsHubService;
use App\Services\Platform\PublishLegalCorpusVersionService;
use App\Services\Platform\RecordSubscriptionAcceptanceService;
use App\Services\Platform\RegisterCommercialPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly PlatformDocumentsHubService $hubService,
        private readonly RecordSubscriptionAcceptanceService $acceptanceService,
        private readonly RegisterCommercialPaymentService $paymentService,
        private readonly PublishLegalCorpusVersionService $publishCorpusService,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('platform.documents.view'), 403);

        $kpis = $this->hubService->hubKpis();

        return view('modules.admin.documents.index', compact('kpis'));
    }

    public function normativa(): View
    {
        abort_unless(auth()->user()?->can('platform.documents.view'), 403);

        $all = LegalCorpusVersion::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        $globals = $all
            ->filter(static fn (LegalCorpusVersion $v): bool => $v->isGlobal() && $v->type !== LegalCorpusType::Contract)
            ->groupBy(fn (LegalCorpusVersion $v) => $v->type->value);

        $contracts = $all
            ->filter(static fn (LegalCorpusVersion $v): bool => $v->type === LegalCorpusType::Contract)
            ->groupBy(fn (LegalCorpusVersion $v) => (string) $v->package_sku)
            ->filter(static fn ($versions): bool => $versions->contains(static fn (LegalCorpusVersion $v): bool => $v->isCurrent()));

        return view('modules.admin.documents.normativa', compact('globals', 'contracts'));
    }

    public function editNormativa(LegalCorpusVersion $corpus): View
    {
        abort_unless(auth()->user()?->can('platform.documents.manage'), 403);
        abort_unless($corpus->isCurrent(), 404);

        return view('modules.admin.documents.normativa-edit', ['document' => $corpus]);
    }

    public function publishNormativa(
        PublishLegalCorpusVersionRequest $request,
        LegalCorpusVersion $corpus,
    ): RedirectResponse {
        abort_unless($corpus->isCurrent(), 404);

        $effectiveFrom = $request->validated('effective_from');

        $published = $this->publishCorpusService->execute(
            $corpus,
            $request->validated('title'),
            $request->validated('content'),
            $effectiveFrom !== null ? CarbonImmutable::parse($effectiveFrom) : null,
        );

        return redirect()
            ->route('admin.documents.normativa.edit', $published)
            ->with('success', 'Nueva versión '.$published->version.' publicada. Expedientes ya aceptados no se modifican.');
    }

    public function trd(): View
    {
        abort_unless(auth()->user()?->can('platform.documents.view'), 403);

        $series = DocumentRetentionSeries::query()
            ->orderBy('sort_order')
            ->orderBy('series')
            ->get();

        return view('modules.admin.documents.trd', compact('series'));
    }

    public function expedientes(): View
    {
        abort_unless(auth()->user()?->can('platform.documents.view'), 403);

        $companies = $this->hubService->expedientesListing();

        return view('modules.admin.documents.expedientes', compact('companies'));
    }

    public function showExpediente(SecurityCompany $company): View
    {
        abort_unless(auth()->user()?->can('platform.documents.view'), 403);

        $detail = $this->hubService->expedienteDetail($company);
        $corpus = LegalCorpusVersion::currentForPackage($company->package_sku);
        $documentTypes = IdentityDocumentType::optionsForSelect();

        return view('modules.admin.documents.expediente', [
            'company' => $company,
            'legalDocuments' => $detail['documents']
                ->filter(fn ($doc) => $doc->type !== PlatformDocumentType::Invoice)
                ->values(),
            'acceptance' => $detail['acceptance'],
            'corpus' => $corpus,
            'documentTypes' => $documentTypes,
            'frozenCorpus' => $detail['acceptance']?->corpus_snapshot ?? null,
        ]);
    }

    public function storeAcceptance(
        StoreSubscriptionAcceptanceRequest $request,
        SecurityCompany $company,
    ): RedirectResponse {
        if ($company->hasCompletedAcceptance()) {
            return redirect()
                ->route('admin.documents.expedientes.show', $company)
                ->with('warning', 'Este suscriptor ya tiene una aceptación registrada.');
        }

        $this->acceptanceService->execute(
            $company,
            $request->user(),
            $request->validated('representative_name'),
            $request->validated('representative_role'),
            $request->validated('representative_document_type'),
            $request->validated('representative_document_number'),
            $request,
        );

        return redirect()
            ->route('admin.documents.expedientes.show', $company)
            ->with('success', 'Aceptación contractual registrada con evidencia.');
    }

    public function storeManualPayment(
        StoreManualPaymentRequest $request,
        SecurityCompany $company,
    ): RedirectResponse {
        try {
            $this->paymentService->executeManual(
                $company,
                $request->user(),
                $request->validated('reference'),
                $request->file('proof'),
                ManualPaymentIntent::from($request->validated('intent')),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.companies.historial', $company)
                ->withInput()
                ->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('admin.companies.historial', $company)
            ->with('success', 'Pago manual registrado. Factura demo generada.');
    }

    public function storeLocalCheckout(
        SecurityCompany $company,
    ): RedirectResponse {
        abort_unless(auth()->user()?->can('platform.documents.manage'), 403);

        try {
            $payment = $this->paymentService->initiateLocalCheckout($company, auth()->user());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()
                ->route('admin.companies.historial', $company)
                ->with('warning', $e->getMessage());
        }

        return redirect()->route('billing.checkout.show', $payment);
    }
}
