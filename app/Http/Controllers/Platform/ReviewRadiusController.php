<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\SupervisionReviewRadius;
use App\Models\SupervisionReviewSettings;
use App\Services\Platform\ManageReviewRadiusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReviewRadiusController extends Controller
{
    public function __construct(
        private readonly ManageReviewRadiusService $service,
    ) {}

    public function index(): View
    {
        $settings = SupervisionReviewSettings::current();

        $exceptions = SupervisionReviewRadius::query()
            ->with(['company', 'client', 'installation', 'updatedByUser'])
            ->latest('updated_at')
            ->get();

        $companies = SecurityCompany::query()
            ->get(['id', 'trade_name', 'legal_name'])
            ->map(fn (SecurityCompany $c) => ['id' => $c->id, 'name' => $c->displayName()])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $clients = Client::query()
            ->orderBy('name')
            ->get(['id', 'security_company_id', 'name'])
            ->map(fn (Client $c) => ['id' => $c->id, 'company_id' => $c->security_company_id, 'name' => $c->name]);

        $installations = Installation::query()
            ->orderBy('name')
            ->get(['id', 'client_id', 'name'])
            ->map(fn (Installation $i) => ['id' => $i->id, 'client_id' => $i->client_id, 'name' => $i->name]);

        return view('modules.admin.settings.index', compact(
            'settings', 'exceptions', 'companies', 'clients', 'installations',
        ));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
            'max_radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
        ]);

        $this->service->updateSettings($request->user(), (int) $data['default_radius_m'], (int) $data['max_radius_m']);

        return redirect()->route('admin.settings.index')->with('open_radius', true)->with('success', 'Radio global actualizado.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'security_company_id' => ['required', 'integer', 'exists:security_companies,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'radius_m' => ['required', 'integer', 'min:10'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Escribe el motivo de la excepción.',
        ]);

        $this->service->save($request->user(), [
            'security_company_id' => (int) $data['security_company_id'],
            'client_id' => isset($data['client_id']) ? (int) $data['client_id'] : null,
            'installation_id' => isset($data['installation_id']) ? (int) $data['installation_id'] : null,
            'radius_m' => (int) $data['radius_m'],
            'reason' => (string) $data['reason'],
        ]);

        return redirect()->route('admin.settings.index')->with('open_radius', true)->with('success', 'Radio de revista guardado.');
    }

    public function destroy(SupervisionReviewRadius $radius): RedirectResponse
    {
        $this->service->delete($radius);

        return redirect()->route('admin.settings.index')->with('open_radius', true)->with('success', 'Excepción quitada; vuelve al radio heredado.');
    }
}
