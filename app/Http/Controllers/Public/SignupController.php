<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Pricing\Data\AccessSeatSplit;
use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Enums\CommercialProduct;
use App\Enums\CompanyPackageSku;
use App\Enums\SignupIntentStatus;
use App\Enums\SupervisionPackageSku;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreSignupDataRequest;
use App\Http\Requests\Public\StoreSignupLegalRequest;
use App\Models\CommercialSignupIntent;
use App\Models\IdentityDocumentType;
use App\Models\LegalCorpusVersion;
use App\Services\Platform\BuildLegalCorpusSnapshotService;
use App\Services\Public\StartSignupIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SignupController extends Controller
{
    public function __construct(
        private readonly StartSignupIntentService $startSignupIntentService,
        private readonly BuildLegalCorpusSnapshotService $corpusSnapshotService,
    ) {}

    public function create(Request $request): RedirectResponse
    {
        $cycle = BillingCycle::tryFrom((string) $request->query('cycle', 'monthly')) ?? BillingCycle::Monthly;
        $legacySku = CompanyPackageSku::tryFrom((string) $request->query('sku', ''));
        $supSeats = (int) $request->query('supervision', 0);
        $sup = $supSeats > 0
            ? SupervisionPackageSku::fromSize(min($supSeats, SupervisionPackageSku::MAX_SEATS))
            : SupervisionPackageSku::tryFrom((string) $request->query('sup', ''));

        try {
            $manual = $request->query('access', $request->query('manual'));
            $hardware = $request->query('hardware');
            $seats = $legacySku !== null
                ? AccessSeatSplit::resolve($legacySku, $manual !== null ? (int) $manual : null, $hardware !== null ? (int) $hardware : null)
                : new AccessSeatSplit(max(0, (int) $manual), max(0, (int) $hardware));

            $selection = new PlanSelection(
                $seats,
                (int) ($sup?->size() ?? 0),
                min(CommercialProduct::MAX_EMPLOYEES, max(0, (int) $request->query('indexing', 0))),
                min(SupervisionPackageSku::MAX_SEATS, max(0, (int) $request->query('observatory', 0))),
            );

            if ($selection->isEmpty()) {
                return redirect()
                    ->route('planes.index')
                    ->with('warning', 'Selecciona un plan para continuar.');
            }

            $intent = $this->startSignupIntentService->execute($selection, $cycle);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('planes.index')->with('warning', $e->getMessage());
        }

        return redirect()->route('signup.data', $intent);
    }

    public function showData(CommercialSignupIntent $intent): View|RedirectResponse
    {
        if ($redirect = $this->guardIntent($intent, SignupIntentStatus::Draft)) {
            return $redirect;
        }

        return view('modules.public.signup.data', compact('intent'));
    }

    public function storeData(StoreSignupDataRequest $request, CommercialSignupIntent $intent): RedirectResponse
    {
        if ($redirect = $this->guardIntent($intent, SignupIntentStatus::Draft)) {
            return $redirect;
        }

        $intent->update([
            'party_type' => $request->validated('party_type'),
            'legal_name' => $request->validated('legal_name'),
            'trade_name' => $request->validated('trade_name'),
            'tax_id' => $request->validated('tax_id'),
            'admin_name' => $request->validated('admin_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'address' => $request->validated('address'),
            'city' => $request->validated('city'),
            'department' => $request->validated('department'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('signup.legal', $intent);
    }

    public function showLegal(CommercialSignupIntent $intent): View|RedirectResponse
    {
        if ($redirect = $this->guardIntent($intent, SignupIntentStatus::Draft)) {
            return $redirect;
        }

        if ($intent->email === null) {
            return redirect()->route('signup.data', $intent);
        }

        $corpus = LegalCorpusVersion::currentForPackage($intent->package_sku);
        $documentTypes = IdentityDocumentType::optionsForSelect();

        return view('modules.public.signup.legal', compact('intent', 'corpus', 'documentTypes'));
    }

    public function storeLegal(StoreSignupLegalRequest $request, CommercialSignupIntent $intent): RedirectResponse
    {
        if ($redirect = $this->guardIntent($intent, SignupIntentStatus::Draft)) {
            return $redirect;
        }

        $snapshot = $this->corpusSnapshotService->forPackage($intent->package_sku);
        $contentHash = $this->corpusSnapshotService->hash(
            $snapshot,
            $request->validated('representative_name'),
            $request->validated('representative_document_number'),
        );

        $intent->update([
            'representative_name' => $request->validated('representative_name'),
            'representative_role' => $request->validated('representative_role'),
            'representative_document_type' => $request->validated('representative_document_type'),
            'representative_document_number' => $request->validated('representative_document_number'),
            'corpus_snapshot' => $snapshot,
            'content_hash' => $contentHash,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);

        return redirect()->route('signup.summary', $intent);
    }

    public function showSummary(CommercialSignupIntent $intent): View|RedirectResponse
    {
        if ($redirect = $this->guardIntent($intent, SignupIntentStatus::Draft)) {
            return $redirect;
        }

        if ($intent->corpus_snapshot === null) {
            return redirect()->route('signup.legal', $intent);
        }

        return view('modules.public.signup.summary', compact('intent'));
    }

    public function pay(CommercialSignupIntent $intent): RedirectResponse
    {
        if ($redirect = $this->guardIntent($intent, SignupIntentStatus::Draft)) {
            return $redirect;
        }

        if (! $intent->corpus_snapshot) {
            return redirect()->route('signup.legal', $intent)->with('warning', 'Complete la aceptación legal.');
        }

        $intent->update(['status' => SignupIntentStatus::AwaitingPayment]);

        return redirect()->route('signup.checkout.show', $intent);
    }

    private function guardIntent(CommercialSignupIntent $intent, SignupIntentStatus $expected): ?RedirectResponse
    {
        if ($intent->isExpired()) {
            $intent->update(['status' => SignupIntentStatus::Expired]);

            return redirect()
                ->route('planes.index')
                ->with('warning', 'El proceso expiró. Selecciona un plan e intenta de nuevo.');
        }

        if ($intent->status === SignupIntentStatus::Completed) {
            return redirect()->route('login')->with('success', 'Esta contratación ya fue completada. Inicia sesión.');
        }

        if ($intent->status === SignupIntentStatus::Rejected) {
            return redirect()
                ->route('planes.index')
                ->with('warning', 'El pago no se completó. Selecciona un plan e intenta de nuevo.');
        }

        if ($intent->status !== $expected && $intent->status !== SignupIntentStatus::AwaitingPayment) {
            return redirect()->route('planes.index');
        }

        return null;
    }
}
