<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Pricing\Data\PlanSelection;
use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Services\Platform\BuildCommercialBrochurePdfService;
use App\Services\Platform\BuildCommercialDeckPptxService;
use App\Services\Platform\BuildCommercialQuotePdfService;
use App\Services\Pricing\PlanQuoter;
use App\Support\Catalog\CatalogSettings;
use App\Support\Commercial\CommercialCopyDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CommercialController extends Controller
{
    public function index(): View
    {
        return view('modules.admin.commercial.index');
    }

    public function brochureForm(Request $request): View
    {
        $user = $request->user();
        $copy = CommercialCopyDefaults::for('brochure');

        return view('modules.admin.commercial.brochure', [
            'defaults' => [
                'date' => now()->toDateString(),
                'city' => CommercialCopyDefaults::city(),
                'directed_to' => '',
                'subject' => $copy['subject'],
                'introduction' => $copy['introduction'],
                'highlight' => $copy['highlight'],
                'conclusions' => $copy['conclusions'],
                'presenter_name' => (string) ($user?->name ?? ''),
                'presenter_role' => 'Ejecutivo comercial',
                'presenter_phone' => '',
                'presenter_email' => (string) ($user?->email ?? ''),
            ],
        ]);
    }

    public function brochurePdf(Request $request, BuildCommercialBrochurePdfService $builder): Response
    {
        $data = $this->validateDocument($request);
        $binary = $builder->build($data);
        $filename = 'propuesta-comercial-zentra-'.now()->format('Ymd-His').'.pdf';

        return $this->pdfDownload($binary, $filename);
    }

    public function deckForm(Request $request): View
    {
        $user = $request->user();
        $copy = CommercialCopyDefaults::for('deck');

        return view('modules.admin.commercial.deck', [
            'defaults' => [
                'date' => now()->toDateString(),
                'city' => CommercialCopyDefaults::city(),
                'directed_to' => '',
                'subject' => $copy['subject'],
                'context' => $copy['context'],
                'solution' => $copy['solution'],
                'why' => $copy['why'],
                'next_steps' => $copy['next_steps'],
                'closing' => $copy['closing'],
                'presenter_name' => (string) ($user?->name ?? ''),
                'presenter_role' => 'Ejecutivo comercial',
                'presenter_phone' => '',
                'presenter_email' => (string) ($user?->email ?? ''),
            ],
        ]);
    }

    public function deckPptx(Request $request, BuildCommercialDeckPptxService $builder): Response
    {
        $data = $this->validateDeck($request);
        $binary = $builder->build($data);
        $filename = 'presentacion-comercial-zentra-'.now()->format('Ymd-His').'.pptx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }

    public function quoteForm(Request $request): View
    {
        $user = $request->user();
        $copy = CommercialCopyDefaults::for('quote');

        return view('modules.admin.commercial.quote', [
            'defaults' => [
                'date' => now()->toDateString(),
                'city' => CommercialCopyDefaults::city(),
                'directed_to' => '',
                'subject' => $copy['subject'],
                'introduction' => $copy['introduction'],
                'highlight' => $copy['highlight'],
                'conclusions' => $copy['conclusions'],
                'presenter_name' => (string) ($user?->name ?? ''),
                'presenter_role' => 'Ejecutivo comercial',
                'presenter_phone' => '',
                'presenter_email' => (string) ($user?->email ?? ''),
                'billing_cycle' => BillingCycle::Monthly->value,
                'manual_seats' => 5,
                'hardware_seats' => 0,
                'supervision_seats' => 0,
                'indexing_seats' => 0,
                'observatory_seats' => 0,
                'commercial_amount' => null,
                'commercial_note' => '',
            ],
            'catalog' => CatalogSettings::current()->forClient(true),
            'cycleOptions' => BillingCycle::options(),
        ]);
    }

    public function quotePdf(Request $request, BuildCommercialQuotePdfService $builder, PlanQuoter $quoter): Response
    {
        $data = $this->validateDocument($request, withPlan: true);
        $selection = PlanSelection::of(
            (int) $data['manual_seats'],
            (int) $data['hardware_seats'],
            (int) $data['supervision_seats'],
            (int) $data['indexing_seats'],
            (int) $data['observatory_seats'],
        );

        abort_if($selection->isEmpty(), 422, 'Indica al menos un producto con cantidad.');

        $cycle = BillingCycle::from($data['billing_cycle']);
        $catalogAmount = $quoter->amount($selection, $cycle);
        $commercial = $data['commercial_amount'] !== null
            ? (float) $data['commercial_amount']
            : $catalogAmount;

        $binary = $builder->build([
            ...$data,
            'cycle' => $cycle->value,
            'selection' => $selection,
            'commercial_amount' => $commercial,
            'commercial_note' => $data['commercial_note'] ?: null,
        ]);

        $filename = 'cotizacion-zentra-'.now()->format('Ymd-His').'.pdf';

        return $this->pdfDownload($binary, $filename);
    }

    /** @return array<string, mixed> */
    private function validateDeck(Request $request): array
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'city' => ['required', 'string', 'max:80'],
            'directed_to' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:200'],
            'context' => ['required', 'string', 'max:4000'],
            'solution' => ['required', 'string', 'max:4000'],
            'why' => ['required', 'string', 'max:4000'],
            'next_steps' => ['required', 'string', 'max:4000'],
            'closing' => ['required', 'string', 'max:2000'],
            'presenter_name' => ['required', 'string', 'max:120'],
            'presenter_role' => ['nullable', 'string', 'max:120'],
            'presenter_phone' => ['nullable', 'string', 'max:40'],
            'presenter_email' => ['nullable', 'email', 'max:120'],
        ]);

        $validated['presenter_role'] = (string) ($validated['presenter_role'] ?? '');
        $validated['presenter_phone'] = (string) ($validated['presenter_phone'] ?? '');
        $validated['presenter_email'] = (string) ($validated['presenter_email'] ?? '');

        return $validated;
    }

    /** @return array<string, mixed> */
    private function validateDocument(Request $request, bool $withPlan = false): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'city' => ['required', 'string', 'max:80'],
            'directed_to' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:200'],
            'introduction' => ['required', 'string', 'max:4000'],
            'highlight' => ['required', 'string', 'max:2000'],
            'conclusions' => ['required', 'string', 'max:4000'],
            'presenter_name' => ['required', 'string', 'max:120'],
            'presenter_role' => ['nullable', 'string', 'max:120'],
            'presenter_phone' => ['nullable', 'string', 'max:40'],
            'presenter_email' => ['nullable', 'email', 'max:120'],
        ];

        if ($withPlan) {
            $rules = array_merge($rules, [
                'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])],
                'manual_seats' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'hardware_seats' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'supervision_seats' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'indexing_seats' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'observatory_seats' => ['nullable', 'integer', 'min:0', 'max:10000'],
                'commercial_amount' => ['nullable', 'numeric', 'min:0'],
                'commercial_note' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        $validated = $request->validate($rules);

        $validated['presenter_role'] = (string) ($validated['presenter_role'] ?? '');
        $validated['presenter_phone'] = (string) ($validated['presenter_phone'] ?? '');
        $validated['presenter_email'] = (string) ($validated['presenter_email'] ?? '');

        if ($withPlan) {
            $validated['manual_seats'] = (int) ($validated['manual_seats'] ?? 0);
            $validated['hardware_seats'] = (int) ($validated['hardware_seats'] ?? 0);
            $validated['supervision_seats'] = (int) ($validated['supervision_seats'] ?? 0);
            $validated['indexing_seats'] = (int) ($validated['indexing_seats'] ?? 0);
            $validated['observatory_seats'] = (int) ($validated['observatory_seats'] ?? 0);
            $validated['commercial_note'] = $validated['commercial_note'] ?? null;
            $validated['commercial_amount'] = array_key_exists('commercial_amount', $validated)
                && $validated['commercial_amount'] !== null
                && $validated['commercial_amount'] !== ''
                ? (float) $validated['commercial_amount']
                : null;
        }

        return $validated;
    }

    private function pdfDownload(string $binary, string $filename): Response
    {
        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }
}
