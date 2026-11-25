<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\ObservatoryCrimeType;
use App\Models\ObservatoryEvent;
use App\Services\Observatory\ManageObservatoryCrimeFiguresService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Cifras de delitos por comuna. Las edita la empresa con Gestionar. */
final class ObservatoryCrimeController extends Controller
{
    public function __construct(
        private readonly ManageObservatoryCrimeFiguresService $crimes,
    ) {}

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'impacto' => ['sometimes', 'integer', 'between:1,10'],
            'probabilidad' => ['sometimes', 'integer', 'between:1,10'],
        ]);

        try {
            $this->crimes->create(
                $this->companyId($request),
                $data['name'],
                $data['color'],
                (int) ($data['impacto'] ?? 5),
                (int) ($data['probabilidad'] ?? 5),
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return $this->backToCrimes($request)
            ->with('success', 'Delito creado. Ya se puede cargar en la tabla y se ve en el mapa.');
    }

    public function updateType(Request $request, ObservatoryCrimeType $crimeType): RedirectResponse
    {
        $this->authorizeEdit($request);
        abort_unless((int) $crimeType->security_company_id === $this->companyId($request), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'impacto' => ['sometimes', 'integer', 'between:1,10'],
            'probabilidad' => ['sometimes', 'integer', 'between:1,10'],
        ]);

        try {
            $this->crimes->update(
                $crimeType,
                $data['name'],
                $data['color'],
                $request->boolean('is_active'),
                (int) ($data['impacto'] ?? $crimeType->impacto),
                (int) ($data['probabilidad'] ?? $crimeType->probabilidad),
            );
        } catch (ValidationException $e) {
            return $this->backToCrimes($request)->withErrors($e->errors());
        }

        return $this->backToCrimes($request)->with('success', 'Delito actualizado.');
    }

    public function archiveType(Request $request, ObservatoryCrimeType $crimeType): RedirectResponse
    {
        $this->authorizeEdit($request);
        abort_unless((int) $crimeType->security_company_id === $this->companyId($request), 404);
        $this->crimes->archive($crimeType);

        return $this->backToCrimes($request)->with('success', 'Delito archivado. Deja de usarse en la grilla y en el mapa.');
    }

    public function destroyType(Request $request, ObservatoryCrimeType $crimeType): RedirectResponse
    {
        $this->authorizeEdit($request);
        abort_unless((int) $crimeType->security_company_id === $this->companyId($request), 404);

        try {
            $this->crimes->delete($crimeType);
        } catch (ValidationException $e) {
            return $this->backToCrimes($request)->withErrors($e->errors());
        }

        return $this->backToCrimes($request)->with('success', 'Delito eliminado.');
    }

    public function saveFigures(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $year = $request->integer('year');
        abort_unless(in_array($year, $this->crimes->years(), true), 422);
        $cells = $request->input('figures', []);
        $cells = is_array($cells) ? $cells : [];

        $this->crimes->saveYear($this->companyId($request), $year, $cells);

        return redirect()
            ->route('company.observatory.types.index', [
                'seccion' => 'comunas',
                'year' => $year,
                'client_id' => $request->integer('client_id') ?: null,
            ])
            ->with('success', 'Cifras de '.$year.' guardadas. El mapa de riesgos ya las usa.');
    }

    private function backToCrimes(Request $request): RedirectResponse
    {
        return $request->input('return') === 'comunas'
            ? redirect()->route('company.observatory.types.index', ['seccion' => 'comunas'])
            : back();
    }

    private function authorizeEdit(Request $request): void
    {
        $this->authorize('viewAny', ObservatoryEvent::class);
        abort_unless($request->user()?->can('observatory.events.update'), 403);
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }
}
