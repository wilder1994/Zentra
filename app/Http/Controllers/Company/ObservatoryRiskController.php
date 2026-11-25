<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ObservatoryEvent;
use App\Models\RiskModelParameter;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Parámetros amigables del motor de riesgo. Beta y lambda se calculan. */
final class ObservatoryRiskController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        abort_unless($request->user()?->can('observatory.events.update'), 403);

        $data = $request->validate([
            'alpha_percent' => ['required', 'integer', 'between:0,100'],
            'gamma_percent' => ['required', 'integer', 'between:0,100'],
            'half_life_days' => ['required', 'integer', 'between:1,3650'],
            'report_points_for_full' => ['required', 'integer', 'between:1,10000'],
            'threshold_low' => ['required', 'integer', 'between:0,100'],
            'threshold_medium' => ['required', 'integer', 'between:0,100'],
            'threshold_high' => ['required', 'integer', 'between:0,100'],
            'threshold_critical' => ['required', 'integer', 'between:0,100'],
            'color_low' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_medium' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_high' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_critical' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $request->validate([
            'threshold_medium' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($data): void {
                    if ((int) $data['threshold_low'] > (int) $data['threshold_medium']
                        || (int) $data['threshold_medium'] > (int) $data['threshold_high']
                        || (int) $data['threshold_high'] > (int) $data['threshold_critical']) {
                        $fail('Los umbrales van de menor a mayor: bajo, medio, alto y crítico.');
                    }
                },
            ],
        ]);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        RiskModelParameter::forCompany($companyId)->update($data);

        return back()->with('success', 'Parámetros del riesgo guardados.');
    }
}
