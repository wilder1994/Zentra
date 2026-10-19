<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorRecommendationStatus;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorRecommendation;
use App\Models\SupervisorShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdvanceSupervisorRecommendationService
{
    public function execute(
        SupervisorRecommendation $recommendation,
        SupervisorShift $shift,
        SupervisorRecommendationStatus $next,
        ?string $notes = null,
        ?float $lat = null,
        ?float $lng = null,
    ): SupervisorRecommendation {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'El turno está cerrado.',
            ]);
        }

        if ((int) $recommendation->security_company_id !== (int) $shift->security_company_id) {
            throw ValidationException::withMessages([
                'recommendation' => 'La recomendación no pertenece a esta empresa.',
            ]);
        }

        if (! $recommendation->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => 'No se puede pasar de '.$recommendation->status->label().' a '.$next->label().'.',
            ]);
        }

        return DB::transaction(function () use ($recommendation, $shift, $next, $notes, $lat, $lng) {
            $from = $recommendation->status;
            $payload = [
                'action' => 'status_change',
                'from' => $from->value,
                'to' => $next->value,
                'risk' => $recommendation->risk,
                'risk_type' => $recommendation->risk_type,
            ];

            $recommendation->status = $next;
            if ($next === SupervisorRecommendationStatus::Closed) {
                $recommendation->closed_by_user_id = $shift->user_id;
                $recommendation->closed_shift_id = $shift->id;
                $recommendation->closed_at = now();
            }
            $recommendation->save();

            $outcome = $next === SupervisorRecommendationStatus::Closed
                ? SupervisorFieldOutcome::Ok
                : SupervisorFieldOutcome::Attention;

            SupervisorFieldLog::query()->create([
                'supervisor_shift_id' => $shift->id,
                'supervisor_zone_id' => $recommendation->supervisor_zone_id,
                'security_company_id' => $shift->security_company_id,
                'user_id' => $shift->user_id,
                'client_id' => $recommendation->client_id,
                'supervisor_recommendation_id' => $recommendation->id,
                'module' => SupervisorFieldModule::Recommendations,
                'outcome' => $outcome,
                'payload' => $payload,
                'notes' => $notes !== null && $notes !== '' ? $notes : null,
                'latitude' => $lat,
                'longitude' => $lng,
                'recorded_at' => now(),
            ]);

            return $recommendation->fresh(['client']);
        });
    }
}
