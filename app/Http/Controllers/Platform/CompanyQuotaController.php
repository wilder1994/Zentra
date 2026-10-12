<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\QuotaRequest;
use App\Models\SecurityCompany;
use App\Services\Platform\ManageQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CompanyQuotaController extends Controller
{
    public function __construct(private readonly ManageQuotaService $quotas) {}

    public function updateCompany(Request $request, SecurityCompany $company): RedirectResponse
    {
        $validated = $request->validate([
            'extra_employees' => ['required', 'integer', 'min:0', 'max:100000'],
            'posts_per_installation' => ['nullable', 'integer', 'min:0', 'max:500'],
            'employees_per_post' => ['nullable', 'integer', 'min:0', 'max:500'],
            'quota_note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->quotas->updateCompany($company, $validated);

        return $this->back($company, 'Cupos de la empresa actualizados.');
    }

    public function updateInstallation(Request $request, SecurityCompany $company): RedirectResponse
    {
        $validated = $request->validate([
            'installation_id' => ['required', 'integer'],
            'max_posts' => ['nullable', 'integer', 'min:0', 'max:500'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt($company, fn () => $this->quotas->updateInstallation(
            $company,
            (int) $validated['installation_id'],
            isset($validated['max_posts']) ? (int) $validated['max_posts'] : null,
            $validated['note'] ?? null,
        ), 'Excepción de puestos guardada.');
    }

    public function updatePost(Request $request, SecurityCompany $company): RedirectResponse
    {
        $validated = $request->validate([
            'supervisor_post_id' => ['required', 'integer'],
            'max_employees' => ['nullable', 'integer', 'min:0', 'max:500'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->attempt($company, fn () => $this->quotas->updatePost(
            $company,
            (int) $validated['supervisor_post_id'],
            isset($validated['max_employees']) ? (int) $validated['max_employees'] : null,
            $validated['note'] ?? null,
        ), 'Excepción de empleados por puesto guardada.');
    }

    public function approve(Request $request, SecurityCompany $company, QuotaRequest $quotaRequest): RedirectResponse
    {
        abort_unless((int) $quotaRequest->security_company_id === (int) $company->id, 404);
        $note = $request->validate(['resolution_note' => ['nullable', 'string', 'max:255']])['resolution_note'] ?? null;

        return $this->attempt($company, fn () => $this->quotas->approve($quotaRequest, $request->user(), $note), 'Solicitud aprobada: el cupo ya está disponible.');
    }

    public function reject(Request $request, SecurityCompany $company, QuotaRequest $quotaRequest): RedirectResponse
    {
        abort_unless((int) $quotaRequest->security_company_id === (int) $company->id, 404);
        $note = $request->validate(['resolution_note' => ['nullable', 'string', 'max:255']])['resolution_note'] ?? null;

        return $this->attempt($company, fn () => $this->quotas->reject($quotaRequest, $request->user(), $note), 'Solicitud rechazada.');
    }

    private function attempt(SecurityCompany $company, \Closure $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return redirect()
                ->to(route('admin.companies.show', $company).'#cupos')
                ->with('error', $e->validator->errors()->first());
        }

        return $this->back($company, $message);
    }

    private function back(SecurityCompany $company, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('admin.companies.show', $company).'#cupos')
            ->with('success', $message);
    }
}
