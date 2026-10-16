<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\GuardShift;
use App\Models\SupervisorShiftTemplate;
use App\Services\Access\PorteriaDoorService;
use App\Services\Access\TurnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TurnoController extends Controller
{
    public function __construct(
        private readonly TurnoService $turnoService,
        private readonly PorteriaDoorService $doors,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $currentShift = $this->turnoService->currentFor($user);
        $requiresShift = ! $this->turnoService->isShiftOptionalFor($user);

        $history = GuardShift::query()
            ->with(['location', 'shiftTemplate'])
            ->where('user_id', $user->id)
            ->latest('started_at')
            ->paginate(15);

        return view('modules.access.turnos.index', compact('currentShift', 'history', 'requiresShift'));
    }

    public function open(Request $request): View|RedirectResponse
    {
        $user = auth()->user();
        if ($this->turnoService->hasOpenShiftFor($user) && ! $this->turnoService->isShiftOptionalFor($user)) {
            return redirect()->route('access.turnos.index')
                ->with('success', 'Ya tienes un turno abierto.');
        }

        $locations = $this->doors->activeDoors();
        $this->doors->bindSingleIfOnlyOne($request);
        $selectedId = $this->doors->current($request)?->id;
        $templates = $this->turnoService->templatesForCurrentClient();
        $requiresShift = ! $this->turnoService->isShiftOptionalFor($user);
        $serverNow = now();

        return view('modules.access.turnos.open', compact(
            'locations',
            'selectedId',
            'templates',
            'requiresShift',
            'serverNow',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $doorIds = $this->doors->activeDoors()->pluck('id')->all();
        $requiresShift = ! $this->turnoService->isShiftOptionalFor($user);
        $templates = $this->turnoService->templatesForCurrentClient();
        $templateIds = $templates->pluck('id')->all();
        $mustPickTemplate = $requiresShift || $templates->isNotEmpty();

        $rules = [
            'location_id' => ['required', 'integer', Rule::in($doorIds)],
            'start_notes' => 'nullable|string|max:500',
            'shift_template_id' => $mustPickTemplate
                ? ['required', 'integer', Rule::in($templateIds)]
                : ['nullable', 'integer'],
        ];

        $validated = $request->validate($rules, [
            'location_id.required' => 'Selecciona la puerta que vas a operar.',
            'location_id.in' => 'La puerta no es válida para este cliente.',
            'shift_template_id.required' => 'Elige el turno (Día, Noche, A, B, C…).',
            'shift_template_id.in' => 'El turno elegido no es válido.',
        ]);

        $locationId = (int) $validated['location_id'];
        $this->doors->remember($request, $locationId);

        if ($this->turnoService->hasOpenShiftFor($user)) {
            $shift = $this->turnoService->currentFor($user);
            $shift?->update(['location_id' => $locationId]);

            return redirect()->route('access.turnos.index')
                ->with('success', 'Puerta de operación actualizada.');
        }

        // Sin catálogo: solo guarda puerta (p. ej. operar portería sin plantillas).
        if (! $mustPickTemplate || empty($validated['shift_template_id'])) {
            return redirect()->route('access.dashboard')
                ->with('success', 'Puerta seleccionada. Ya puedes operar y el pánico usará esa puerta.');
        }

        $template = SupervisorShiftTemplate::query()->findOrFail((int) $validated['shift_template_id']);
        $shift = $this->turnoService->open(
            $user,
            $locationId,
            $template,
            $validated['start_notes'] ?? null,
        );

        return redirect()->route('access.dashboard')
            ->with('success', 'Turno «'.$shift->shiftTemplate?->name.'» abierto a las '.$shift->started_at->format('H:i').' · '.$shift->location?->name.'. Quedó en la minuta.');
    }

    public function close(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'end_notes' => 'nullable|string|max:500',
        ]);

        $shift = $this->turnoService->close($user, $validated['end_notes'] ?? null);

        if ($shift === null) {
            return back()->with('error', 'No tienes un turno abierto.');
        }

        $request->session()->forget(PorteriaDoorService::SESSION_KEY);

        return redirect()->route('access.turnos.index')
            ->with('success', 'Turno cerrado a las '.$shift->ended_at->format('H:i').'. Quedó en la minuta.');
    }
}
