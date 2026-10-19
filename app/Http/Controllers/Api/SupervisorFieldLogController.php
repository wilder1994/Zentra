<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SupervisorFieldModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSupervisorFieldLogRequest;
use App\Models\SupervisorRecommendation;
use App\Services\Company\ManageSupervisorShiftService;
use App\Services\Company\RecordSupervisorFieldLogService;
use App\Services\Company\RefreshLateSyncedShiftService;
use App\Support\Supervision\FieldModuleCatalog;
use App\Support\Supervision\SupervisorCaptureTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SupervisorFieldLogController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorShiftService $shiftService,
        private readonly RecordSupervisorFieldLogService $logService,
        private readonly FieldModuleCatalog $catalog,
        private readonly RefreshLateSyncedShiftService $lateSync,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->security_company_id;

        return response()->json(['modules' => $this->catalog->modules($companyId, $request->integer('app_build'))]);
    }

    public function store(StoreSupervisorFieldLogRequest $request): JsonResponse
    {
        $module = SupervisorFieldModule::from((string) $request->validated('module'));
        if (in_array($module, [SupervisorFieldModule::Uniform, SupervisorFieldModule::Trainings], true)) {
            throw ValidationException::withMessages([
                'module' => $module->label().' se registra dentro de la revista, al guardarla.',
            ]);
        }

        $at = SupervisorCaptureTime::parse($request->validated('recorded_at'));
        $shift = $this->shiftService->shiftForCapture($request->user(), $at);
        abort_if($shift === null, 422, 'No hay turno abierto.');

        $log = $this->logService->execute(
            $shift,
            $module,
            $request->validated('payload'),
            $request->validated('client_id') !== null ? (int) $request->validated('client_id') : null,
            $request->validated('supervisor_shift_review_id') !== null
                ? (int) $request->validated('supervisor_shift_review_id')
                : null,
            $request->validated('notes'),
            $request->validated('latitude') !== null ? (float) $request->validated('latitude') : null,
            $request->validated('longitude') !== null ? (float) $request->validated('longitude') : null,
            $request->validated('client_event_id'),
            $at,
        );
        if (! $shift->isOpen()) {
            $this->lateSync->execute($shift);
        }

        return response()->json([
            'log' => $log->load(['client:id,name', 'recommendation']),
            'activity' => $this->logService->activityFor($shift),
        ], 201);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $clientId = $request->integer('client_id');

        $query = SupervisorRecommendation::query()
            ->where('security_company_id', $request->user()->security_company_id)
            ->with(['client:id,name'])
            ->orderByDesc('created_at');

        if ($clientId > 0) {
            $query->where('client_id', $clientId);
        }

        return response()->json([
            'recommendations' => $query->limit(50)->get(),
        ]);
    }
}
