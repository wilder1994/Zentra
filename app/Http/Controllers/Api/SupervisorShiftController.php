<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Supervision\Data\OpenSupervisorShiftInput;
use App\Domain\Supervision\Data\RecordSupervisorShiftReviewInput;
use App\Enums\SupervisorChecklistKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CloseSupervisorShiftRequest;
use App\Http\Requests\Api\OpenSupervisorShiftRequest;
use App\Http\Requests\Api\StoreSupervisorShiftReviewRequest;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorFleetVehicle;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftLocation;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorZone;
use App\Services\Auth\BindSupervisorDevice;
use App\Services\Auth\FindUserByLogin;
use App\Services\Company\BuildSupervisorOfflinePackService;
use App\Services\Company\CloseSupervisorShiftService;
use App\Services\Company\LookupSupervisorVisitService;
use App\Services\Company\ManageSupervisorShiftService;
use App\Services\Company\OpenSupervisorShiftService;
use App\Services\Company\RecordSupervisorFieldLogService;
use App\Services\Company\RecordSupervisorProReviewService;
use App\Services\Company\RefreshLateSyncedShiftService;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use App\Services\Ops\TriggerPanicService;
use App\Support\Auth\LoginUsernameRules;
use App\Support\Company\CompanyServiceAccess;
use App\Support\Supervision\SupervisorCaptureTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SupervisorShiftController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorShiftService $shiftService,
        private readonly OpenSupervisorShiftService $openShift,
        private readonly CloseSupervisorShiftService $closeShift,
        private readonly RecordSupervisorProReviewService $reviewService,
        private readonly RecordSupervisorFieldLogService $logService,
        private readonly SeedSupervisorIntakeDefaultsService $intakeDefaults,
        private readonly LookupSupervisorVisitService $visitLookup,
        private readonly FindUserByLogin $findUserByLogin,
        private readonly BuildSupervisorOfflinePackService $offlinePack,
        private readonly TriggerPanicService $panic,
        private readonly BindSupervisorDevice $deviceBinding,
        private readonly RefreshLateSyncedShiftService $lateSync,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'password' => 'required',
            'device_name' => 'nullable|string|max:100',
            'device_id' => 'nullable|string|min:8|max:100',
            'device_label' => 'nullable|string|max:150',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $login = trim((string) ($request->input('login') ?: $request->input('email')));
        if ($login === '') {
            throw ValidationException::withMessages([
                'login' => ['Indique el usuario o el correo.'],
            ]);
        }

        $user = $this->findUserByLogin->execute($login)?->load('securityCompany');

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Credenciales inválidas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Esta cuenta está desactivada.'],
            ]);
        }

        $user->loadMissing('securityCompany');
        if (CompanyServiceAccess::isCut($user->securityCompany)) {
            throw ValidationException::withMessages([
                'login' => [CompanyServiceAccess::DENIED],
            ]);
        }

        if (! $user->hasRole('supervisor') || $user->security_company_id === null) {
            abort(403, 'Esta cuenta no es de supervisor.');
        }

        $company = $user->securityCompany;
        if ($company === null || ! $company->hasSupervisionPackage()) {
            abort(403, 'La empresa no tiene Supervisión.');
        }

        $deviceId = trim((string) $request->input('device_id'));
        if ($deviceId === '') {
            throw ValidationException::withMessages([
                'login' => ['Actualiza la app de campo para continuar.'],
            ]);
        }

        $blocked = $this->deviceBinding->check(
            $user,
            $deviceId,
            $request->filled('device_label') ? (string) $request->input('device_label') : null,
            $request->filled('latitude') ? (float) $request->input('latitude') : null,
            $request->filled('longitude') ? (float) $request->input('longitude') : null,
        );
        if ($blocked !== null) {
            return response()->json($blocked, 423);
        }

        $user->tokens()->delete();
        $token = $user->createToken($request->string('device_name')->toString() ?: 'supervision-pro')->plainTextToken;

        return response()->json([
            'token' => $token,
            'must_change_password' => (bool) $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'company_id' => $user->security_company_id,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'username' => LoginUsernameRules::forChange($user),
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'username.regex' => 'Use el formato nombre.apellido.1234.',
            'username.not_in' => 'El usuario debe ser distinto al temporal.',
            'username.unique' => 'Ese usuario ya existe.',
        ]);

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['La nueva contraseña debe ser distinta a la actual.'],
            ]);
        }

        $user->update([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return response()->json([
            'ok' => true,
            'username' => $user->username,
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $shift = $this->shiftService->currentFor($request->user());
        if ($shift !== null) {
            $shift->load(['fleetVehicle', 'zone', 'shiftTemplate', 'user']);
        }

        $latestReview = $shift?->reviews()->latest('id')->with(['client:id,name', 'supervisorPost.installation:id,name', 'employee'])->first();

        return response()->json([
            'shift' => $shift,
            'supervisor' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'has_selfie' => $shift?->km_start_selfie_path !== null,
            ],
            'current_review' => $latestReview !== null ? $this->reviewPayload($latestReview) : null,
            'activity' => $shift !== null ? $this->logService->activityFor($shift) : null,
        ]);
    }

    public function sites(Request $request): JsonResponse
    {
        return response()->json(['sites' => $this->visitLookup->sites($request->user())]);
    }

    public function offlinePack(Request $request): JsonResponse
    {
        return response()->json($this->offlinePack->execute($request->user(), $request->integer('app_build')));
    }

    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        return response()->json([
            'posts' => $this->visitLookup->posts(
                $request->user(),
                (int) $request->integer('client_id'),
                (string) $request->string('q'),
            ),
        ]);
    }

    public function guards(Request $request): JsonResponse
    {
        $request->validate([
            'document' => ['required', 'string', 'max:30'],
        ]);

        return response()->json([
            'guards' => $this->visitLookup->guards($request->user(), (string) $request->string('document')),
        ]);
    }

    public function startSelfie(Request $request): BinaryFileResponse
    {
        $shift = $this->shiftService->currentFor($request->user());
        abort_if($shift === null || $shift->km_start_selfie_path === null, 404);
        abort_unless(Storage::disk('local')->exists($shift->km_start_selfie_path), 404);

        return response()->file(Storage::disk('local')->path($shift->km_start_selfie_path), [
            'Cache-Control' => 'private, max-age=120',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function intake(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->security_company_id;
        $this->intakeDefaults->execute($companyId);

        $vehicles = SupervisorFleetVehicle::query()
            ->where('security_company_id', $companyId)
            ->orderBy('plate')
            ->get();

        $zones = SupervisorZone::query()
            ->where('security_company_id', $companyId)
            ->active()
            ->get(['id', 'name']);

        $templates = SupervisorShiftTemplate::query()
            ->where('security_company_id', $companyId)
            ->active()
            ->get();

        return response()->json([
            'zones' => $zones->map(fn (SupervisorZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
            ])->values()->all(),
            'shift_templates' => $templates->map(fn (SupervisorShiftTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'starts_at' => $template->starts_at,
                'ends_at' => $template->ends_at,
                'schedule' => $template->scheduleLabel(),
            ])->values()->all(),
            'ppe' => $this->checklistList($companyId, SupervisorChecklistKind::Ppe),
            'vehicle_check' => $this->checklistList($companyId, SupervisorChecklistKind::Vehicle),
            'vehicles' => $vehicles->map(fn (SupervisorFleetVehicle $vehicle) => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'label' => $vehicle->displayName(),
                'brand' => $vehicle->brand,
                'line' => $vehicle->line,
                'model' => $vehicle->model,
                'soat_expires_at' => $vehicle->soat_expires_at?->toDateString(),
                'technical_review_expires_at' => $vehicle->technical_review_expires_at?->toDateString(),
                'last_km' => $vehicle->last_km,
            ])->values()->all(),
            'first_vehicle' => $vehicles->isEmpty(),
        ]);
    }

    public function open(OpenSupervisorShiftRequest $request): JsonResponse
    {
        $vehicle = $request->input('vehicle', []);
        $shift = $this->openShift->execute(
            $request->user(),
            new OpenSupervisorShiftInput(
                shiftTemplateId: (int) $request->validated('shift_template_id'),
                zoneId: (int) $request->validated('zone_id'),
                kmStart: (int) $request->validated('km_start'),
                ppeChecklist: $request->validated('ppe_checklist'),
                vehicleChecklist: $request->validated('vehicle_checklist'),
                odometerPhoto: $request->file('odometer_photo'),
                selfiePhoto: $request->file('selfie_photo'),
                vehicleId: $request->validated('vehicle_id') !== null ? (int) $request->validated('vehicle_id') : null,
                plate: $vehicle['plate'] ?? null,
                brand: $vehicle['brand'] ?? null,
                line: $vehicle['line'] ?? null,
                model: $vehicle['model'] ?? null,
                color: $vehicle['color'] ?? null,
                type: $vehicle['type'] ?? null,
                soatExpiresAt: $vehicle['soat_expires_at'] ?? null,
                technicalReviewExpiresAt: $vehicle['technical_review_expires_at'] ?? null,
            ),
        );

        return response()->json(['shift' => $shift], 201);
    }

    public function ping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'client_event_id' => ['nullable', 'uuid'],
            'pending_outbox' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'screen_on' => ['nullable', 'boolean'],
            'source' => ['nullable', 'in:app,apk'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $at = SupervisorCaptureTime::parse($data['recorded_at'] ?? null);
        $shift = $this->shiftService->shiftForCapture($request->user(), $at);
        abort_if($shift === null, 422, 'No hay turno abierto.');

        $point = $this->storePing($shift, $data, $at);
        if (isset($data['pending_outbox']) && $shift->isOpen()) {
            $shift->update(['pending_outbox_count' => max(0, (int) $data['pending_outbox'])]);
        }
        if (! $shift->isOpen()) {
            $this->lateSync->execute($shift);
        }

        return response()->json(['location' => $point]);
    }

    /** Lote de GPS guardado en el celular sin red (APK). Cada punto va a su turno según su hora. */
    public function pings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'array', 'min:1', 'max:300'],
            'points.*.latitude' => ['required', 'numeric'],
            'points.*.longitude' => ['required', 'numeric'],
            'points.*.accuracy' => ['nullable', 'numeric'],
            'points.*.client_event_id' => ['nullable', 'uuid'],
            'points.*.screen_on' => ['nullable', 'boolean'],
            'points.*.source' => ['nullable', 'in:app,apk'],
            'points.*.recorded_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $stored = 0;
        $late = [];
        foreach ($data['points'] as $row) {
            $at = SupervisorCaptureTime::parse($row['recorded_at'] ?? null);
            $shift = $this->shiftService->shiftForCapture($user, $at);
            if ($shift === null) {
                continue;
            }
            try {
                $this->storePing($shift, $row, $at);
                $stored++;
                if (! $shift->isOpen()) {
                    $late[$shift->id] = $shift;
                }
            } catch (ValidationException) {
                continue;
            }
        }
        foreach ($late as $shift) {
            $this->lateSync->execute($shift);
        }

        $open = $this->shiftService->currentFor($user) !== null;
        abort_if($stored === 0 && ! $open, 422, 'No hay turno abierto.');

        return response()->json(['stored' => $stored, 'open' => $open]);
    }

    /** @param  array<string, mixed>  $data */
    private function storePing(SupervisorShift $shift, array $data, ?CarbonImmutable $at): SupervisorShiftLocation
    {
        return $this->shiftService->ping(
            $shift,
            (float) $data['latitude'],
            (float) $data['longitude'],
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            (string) ($data['source'] ?? 'app'),
            isset($data['client_event_id']) ? (string) $data['client_event_id'] : null,
            null,
            array_key_exists('screen_on', $data) ? (bool) $data['screen_on'] : null,
            $at,
        );
    }

    public function close(CloseSupervisorShiftRequest $request): JsonResponse
    {
        $eventId = $request->validated('client_event_id');
        if (is_string($eventId) && $eventId !== '') {
            $replay = SupervisorShift::query()
                ->where('user_id', $request->user()->id)
                ->where('close_client_event_id', $eventId)
                ->first();
            if ($replay !== null) {
                return response()->json(['shift' => $replay]);
            }
        }

        $shift = $this->shiftService->currentFor($request->user());
        if ($shift === null) {
            $systemClosed = SupervisorShift::query()
                ->where('user_id', $request->user()->id)
                ->where('closed_by_system', true)
                ->whereNull('close_client_event_id')
                ->where('ended_at', '>=', now()->subHours(SupervisorCaptureTime::LATE_SYNC_HOURS))
                ->latest('ended_at')
                ->first();
            abort_if($systemClosed === null, 422, 'No hay turno abierto.');

            return response()->json(['shift' => $this->closeShift->attachToSystemClosed(
                $systemClosed,
                (int) $request->validated('km_end'),
                $request->file('odometer_photo'),
                $request->file('selfie_photo'),
                is_string($eventId) && $eventId !== '' ? $eventId : null,
            )]);
        }

        $closed = $this->closeShift->execute(
            $shift,
            (int) $request->validated('km_end'),
            $request->file('odometer_photo'),
            $request->file('selfie_photo'),
            is_string($eventId) && $eventId !== '' ? $eventId : null,
        );

        return response()->json(['shift' => $closed]);
    }

    public function review(StoreSupervisorShiftReviewRequest $request): JsonResponse
    {
        $at = SupervisorCaptureTime::parse($request->validated('recorded_at'));
        $shift = $this->shiftService->shiftForCapture($request->user(), $at);
        abort_if($shift === null, 422, 'No hay turno abierto.');

        $review = $this->reviewService->execute(
            $shift,
            new RecordSupervisorShiftReviewInput(
                clientId: (int) $request->validated('client_id'),
                supervisorPostId: (int) $request->validated('supervisor_post_id'),
                employeeId: (int) $request->validated('employee_id'),
                notes: (string) ($request->validated('notes') ?? ''),
                hasNovelty: (bool) $request->validated('has_novelty'),
                guardPhoto: $request->file('guard_photo'),
                latitude: (float) $request->validated('latitude'),
                longitude: (float) $request->validated('longitude'),
                logs: $request->validated('logs') ?? [],
                logPhotos: is_array($request->file('log_photos')) ? $request->file('log_photos') : [],
                clientEventId: $request->validated('client_event_id'),
                recordedAt: $at,
                appBuild: (int) ($request->validated('app_build') ?? 0),
            ),
        );
        if (! $shift->isOpen()) {
            $this->lateSync->execute($shift);
        }

        return response()->json([
            'review' => $this->reviewPayload($review),
            'activity' => $this->logService->activityFor($shift),
            'discarded_trainings' => $this->reviewService->discardedTrainings(),
        ], 201);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function checklistList(int $companyId, SupervisorChecklistKind $kind): array
    {
        $rows = [];
        foreach (SupervisorChecklistItem::keyedLabels($companyId, $kind) as $key => $label) {
            $rows[] = ['key' => $key, 'label' => $label];
        }

        return $rows;
    }

    public function panic(Request $request): JsonResponse
    {
        abort_if($this->shiftService->currentFor($request->user()) === null, 422, 'No hay turno abierto.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:240'],
        ]);

        $this->panic->execute(
            $request->user(),
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            (string) ($data['note'] ?? ''),
        );

        return response()->json(['ok' => true], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewPayload(SupervisorShiftReview $review): array
    {
        $review->loadMissing(['client:id,name', 'employee', 'supervisorPost.installation:id,name']);
        $post = $review->supervisorPost;

        return [
            'id' => $review->id,
            'client_id' => $review->client_id,
            'client_name' => $review->client?->name,
            'supervisor_post_id' => $review->supervisor_post_id,
            'post_name' => $post?->name,
            'installation_name' => $post?->installation?->name,
            'employee_id' => $review->employee_id,
            'employee_name' => $review->employee?->fullName(),
            'employee_document' => $review->employee?->document_number,
            'has_novelty' => $review->has_novelty,
            'notes' => $review->notes,
            'latitude' => $review->latitude !== null ? (float) $review->latitude : null,
            'longitude' => $review->longitude !== null ? (float) $review->longitude : null,
            'recorded_at' => $review->recorded_at?->toIso8601String(),
        ];
    }
}
