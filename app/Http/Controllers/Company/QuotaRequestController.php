<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Enums\QuotaRequestKind;
use App\Http\Controllers\Controller;
use App\Models\SecurityCompany;
use App\Services\Platform\ManageQuotaService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class QuotaRequestController extends Controller
{
    public function store(Request $request, ManageQuotaService $quotas): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::enum(QuotaRequestKind::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'installation_id' => ['nullable', 'integer'],
            'supervisor_post_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $company = SecurityCompany::query()->findOrFail(app(ActingCompanyResolver::class)->requireId($request->user()));

        try {
            $quotas->request(
                $company,
                $request->user(),
                QuotaRequestKind::from($validated['kind']),
                (int) $validated['quantity'],
                isset($validated['installation_id']) ? (int) $validated['installation_id'] : null,
                isset($validated['supervisor_post_id']) ? (int) $validated['supervisor_post_id'] : null,
                $validated['message'] ?? null,
            );
        } catch (ValidationException $e) {
            return redirect()->route('company.billing.index')->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('company.billing.index')
            ->with('success', 'Solicitud enviada. El equipo Zentra la revisará y te contactará para el cobro.');
    }
}
