<?php

namespace App\Http\Controllers\Access;

use App\Enums\InstallationKind;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GuardLog;
use App\Models\Location;
use App\Models\SupervisionCode;
use App\Models\User;
use App\Services\Access\AuditLogger;
use App\Services\Access\PorteriaDoorService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\SubmitObservatoryReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuardLogController extends Controller
{
    public function __construct(private readonly PorteriaDoorService $doors) {}

    public function index(Request $request)
    {
        $doors = $this->doors->activeDoors();
        $door = $doors->firstWhere('id', (int) $request->query('door'))
            ?? $this->doors->operatingOrFirst($request);
        $door?->loadMissing('installation.client');

        $date = today();
        if ($request->filled('date')) {
            try {
                $date = Carbon::parse((string) $request->query('date'))->startOfDay();
            } catch (\Throwable) {
            }
        }
        if ($date->isFuture()) {
            $date = today();
        }

        $entries = $door === null ? collect() : GuardLog::query()
            ->with(['user:id,name', 'corrects:id,log_time', 'corrections:id,corrects_guard_log_id,log_time'])
            ->where('location_id', $door->id)
            ->whereDate('log_time', $date)
            ->orderBy('log_time')
            ->orderBy('id')
            ->get();

        $observatoryKinds = [];
        if ($door !== null && $this->observatoryEligible($door)) {
            $clientId = (int) $door->installation->client_id;
            $observatoryKinds = app(EnsureObservatoryReportTypesService::class)
                ->optionsByClient(collect([$clientId]))[$clientId] ?? [];
        }

        return view('modules.access.guard_logs.index', [
            'doors' => $doors,
            'door' => $door,
            'date' => $date,
            'entries' => $entries,
            'observatoryKinds' => $observatoryKinds,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(GuardLog::LABELS))],
            'description' => ['required', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'supervision_code' => [Rule::requiredIf($request->input('type') === 'revista'), 'nullable', 'string', 'max:50'],
            'corrects_guard_log_id' => [Rule::requiredIf($request->input('type') === 'correccion'), 'nullable', 'integer'],
            'to_observatory' => ['sometimes', 'boolean'],
            'observatory_kind' => [
                $request->boolean('to_observatory') && $request->input('type') === 'novedad' ? 'required' : 'nullable',
                'string',
                'max:80',
            ],
            'observatory_anonymous' => ['sometimes', 'boolean'],
        ], [
            'description.required' => 'Escribe la anotación.',
            'supervision_code.required' => 'El supervisor debe ingresar su código.',
        ]);

        $location = Location::query()->where('is_active', true)->find($validated['location_id']);
        if ($location === null) {
            throw ValidationException::withMessages(['location_id' => 'La puerta no está disponible.']);
        }

        $correctsId = null;
        if ($validated['type'] === 'correccion') {
            $original = GuardLog::query()
                ->where('location_id', $location->id)
                ->where('type', '!=', 'correccion')
                ->find($validated['corrects_guard_log_id']);
            if ($original === null) {
                throw ValidationException::withMessages(['corrects_guard_log_id' => 'La anotación a corregir no existe en este libro.']);
            }
            $correctsId = $original->id;
        }

        $code = null;
        $supervisorName = null;
        if ($validated['type'] === 'revista') {
            $resolved = $this->resolveSupervisorSignature((string) $validated['supervision_code']);
            if ($resolved === null) {
                throw ValidationException::withMessages(['supervision_code' => 'El código de supervisor no es válido o está inactivo.']);
            }
            $code = $resolved['code'];
            $supervisorName = $resolved['name'];
        }

        $now = now();
        $log = GuardLog::create([
            'user_id' => auth()->id(),
            'location_id' => $location->id,
            'log_time' => $now,
            'type' => $validated['type'],
            'shift_type' => $now->hour >= 6 && $now->hour < 18 ? 'diurno' : 'nocturno',
            'description' => $validated['description'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'signed_at' => $now,
            'supervision_code_id' => $code?->id,
            'supervisor_name' => $supervisorName,
            'corrects_guard_log_id' => $correctsId,
        ]);

        app(AuditLogger::class)->record($log, 'guard_log.create', null, [
            'type' => $log->type,
            'location_id' => $log->location_id,
            'log_time' => $log->log_time->toDateTimeString(),
            'supervisor_name' => $log->supervisor_name,
            'corrects_guard_log_id' => $correctsId,
        ]);

        $message = $log->type === 'revista' ? 'Revista registrada en el libro.' : 'Anotación registrada.';
        if ($request->boolean('to_observatory') && $log->type === 'novedad') {
            $message = $this->sendToObservatory($request, $location, $log, (string) ($validated['observatory_kind'] ?? ''));
        }

        return redirect()->route('access.guard_logs.index', ['door' => $location->id])
            ->with('success', $message);
    }

    private function sendToObservatory(Request $request, Location $location, GuardLog $log, string $kind): string
    {
        $location->loadMissing('installation.client');
        if (! $this->observatoryEligible($location) || ! $location->installation?->client) {
            return 'Anotación registrada.';
        }

        $anonymous = $request->boolean('observatory_anonymous');
        $user = $request->user();

        try {
            app(SubmitObservatoryReportService::class)->execute($location->installation->client, [
                'installation_id' => (int) $location->installation_id,
                'kind' => $kind,
                'body' => (string) $log->description,
                'is_anonymous' => $anonymous,
                'source' => ObservatoryReportSource::Porteria,
                'reporter_role' => ObservatoryReporterRole::Vigilante,
                'reporter_name' => $anonymous ? null : $user?->name,
                'reported_by' => $user,
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
            ], $request->ip());
        } catch (ValidationException) {
            return 'Anotación registrada. No se pudo enviar al Observatorio.';
        }

        return 'Anotación registrada y enviada al Observatorio.';
    }

    /** @return array{code: ?SupervisionCode, name: string}|null */
    private function resolveSupervisorSignature(string $raw): ?array
    {
        $code = SupervisionCode::query()
            ->where('code', $raw)
            ->where('is_active', true)
            ->first();

        if ($code !== null) {
            return ['code' => $code, 'name' => $code->name];
        }

        $supervisor = User::query()
            ->where('supervisor_code', $raw)
            ->where('is_active', true)
            ->role('supervisor')
            ->when($this->porteriaCompanyId(), function ($query, int $companyId): void {
                $query->where('security_company_id', $companyId);
            })
            ->first();

        if ($supervisor === null) {
            return null;
        }

        return ['code' => null, 'name' => $supervisor->name];
    }

    private function observatoryEligible(?Location $location): bool
    {
        $installation = $location?->installation;
        if ($installation === null || ! $installation->is_active) {
            return false;
        }

        return (string) $installation->kind === InstallationKind::Colegio->value;
    }

    private function porteriaCompanyId(): ?int
    {
        $clientId = app(TenantContext::class)->clientId();

        if ($clientId === null) {
            $companyId = auth()->user()?->security_company_id;

            return $companyId !== null ? (int) $companyId : null;
        }

        $companyId = Client::query()->whereKey($clientId)->value('security_company_id');

        return $companyId !== null ? (int) $companyId : null;
    }
}
