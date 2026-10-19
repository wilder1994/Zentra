<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SupervisorTraining;
use App\Models\SupervisorTrainingTopic;
use App\Services\Supervision\ResolveTrainingTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Temas y validación de capacitaciones para la app de campo. */
final class SupervisorTrainingController extends Controller
{
    public function __construct(
        private readonly ResolveTrainingTopicService $topics,
    ) {}

    public function storeTopic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
        ]);
        $companyId = (int) $request->user()->security_company_id;
        $normalized = SupervisorTrainingTopic::normalize((string) $data['name']);
        $existed = SupervisorTrainingTopic::query()
            ->where('security_company_id', $companyId)
            ->where('normalized_name', $normalized)
            ->exists();

        $topic = $this->topics->byName($companyId, (string) $data['name'], $request->user());

        return response()->json([
            'topic' => ['value' => (string) $topic->id, 'label' => (string) $topic->name],
            'created' => ! $existed,
        ], $existed ? 200 : 201);
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'topic_id' => ['required', 'integer'],
        ]);
        $companyId = (int) $request->user()->security_company_id;
        $employee = Employee::query()
            ->where('security_company_id', $companyId)
            ->whereKey((int) $data['employee_id'])
            ->first();
        abort_if($employee === null, 404);

        $training = SupervisorTraining::query()
            ->where('security_company_id', $companyId)
            ->where('employee_id', $employee->id)
            ->where('supervisor_training_topic_id', (int) $data['topic_id'])
            ->where('period', now()->format('Y-m'))
            ->with(['topic:id,name', 'user:id,name'])
            ->first();

        if ($training === null) {
            return response()->json(['trained' => false]);
        }

        $at = $training->recorded_at?->timezone((string) config('app.timezone'));

        return response()->json([
            'trained' => true,
            'message' => 'Este vigilante ya fue capacitado en «'.($training->topic?->name ?? 'este tema').'»'
                .($at ? ' el '.$at->format('d/m') : '')
                .($training->user ? ' por '.$training->user->name : '')
                .'. Elija otro tema o cancele la capacitación.',
        ]);
    }
}
