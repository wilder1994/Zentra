<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorRecommendationPriority;
use App\Enums\SupervisorRecommendationStatus;
use App\Enums\SupervisorRiskImpact;
use App\Enums\SupervisorRiskLevel;
use App\Enums\SupervisorRiskLikelihood;
use App\Models\Client;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorRecommendation;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Services\Supervision\NotifySupervisionNoveltyService;
use App\Support\Supervision\SupervisorCaptureTime;
use App\Support\Supervision\SupervisorClientScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordSupervisorFieldLogService
{
    public function __construct(
        private readonly AssertSupervisorFieldPayload $assertPayload,
        private readonly SupervisorClientScope $clientScope,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        SupervisorShift $shift,
        SupervisorFieldModule $module,
        array $payload,
        ?int $clientId,
        ?int $reviewId,
        ?string $notes,
        ?float $lat,
        ?float $lng,
        ?string $clientEventId = null,
        ?CarbonImmutable $recordedAt = null,
    ): SupervisorFieldLog {
        SupervisorCaptureTime::assertAccepts($shift, $recordedAt);

        if ($clientEventId !== null && $clientEventId !== '') {
            $replay = SupervisorFieldLog::query()
                ->where('client_event_id', $clientEventId)
                ->where('supervisor_shift_id', $shift->id)
                ->first();
            if ($replay !== null) {
                return $replay;
            }
        }

        $review = $this->resolveReview($shift, $module, $reviewId);
        $client = $module === SupervisorFieldModule::Documents
            ? null
            : ($review?->client ?? $this->resolveClient($shift, $module, $clientId));
        if ($client !== null && $review === null) {
            $this->clientScope->assertVisible($shift, $client, $recordedAt);
        }
        $this->assertGps($module, $lat, $lng);
        $validated = $this->assertPayload->execute($module, $payload, (int) $shift->security_company_id);

        $zoneId = $review?->supervisor_zone_id !== null
            ? (int) $review->supervisor_zone_id
            : $this->clientScope->recordZoneId($shift, $client);

        $log = DB::transaction(function () use ($shift, $module, $validated, $client, $review, $notes, $lat, $lng, $clientEventId, $recordedAt, $zoneId) {
            $recommendationId = null;
            $payload = $validated->payload;

            if ($module === SupervisorFieldModule::Recommendations) {
                if ($client === null) {
                    throw ValidationException::withMessages([
                        'client_id' => 'La recomendación requiere un cliente.',
                    ]);
                }

                foreach ($payload['items'] as $index => $item) {
                    $level = SupervisorRiskLevel::from((string) $item['risk_level']);
                    $recommendation = SupervisorRecommendation::query()->create([
                        'security_company_id' => $shift->security_company_id,
                        'supervisor_zone_id' => $zoneId,
                        'client_id' => $client->id,
                        'opened_by_user_id' => $shift->user_id,
                        'opened_shift_id' => $shift->id,
                        'status' => SupervisorRecommendationStatus::Recorded,
                        'priority' => SupervisorRecommendationPriority::from((string) $item['priority']),
                        'due_date' => null,
                        'supervisor_risk_type_id' => (int) $item['risk_type_id'],
                        'risk_type' => $item['risk_type'] ?? null,
                        'body' => $item['treatment'],
                        'risk' => $item['risk'],
                        'likelihood' => SupervisorRiskLikelihood::from((string) $item['likelihood']),
                        'impact' => SupervisorRiskImpact::from((string) $item['impact']),
                        'consequence' => $item['consequence'],
                        'treatment' => $item['treatment'],
                        'risk_level' => $level,
                        'photos' => is_array($item['photos'] ?? null) ? $item['photos'] : null,
                    ]);
                    $payload['items'][$index]['recommendation_id'] = $recommendation->id;
                    $recommendationId ??= $recommendation->id;
                }
            }

            return SupervisorFieldLog::query()->create([
                'supervisor_shift_id' => $shift->id,
                'supervisor_shift_review_id' => $review?->id,
                'supervisor_zone_id' => $zoneId,
                'security_company_id' => $shift->security_company_id,
                'user_id' => $shift->user_id,
                'client_id' => $client?->id,
                'supervisor_recommendation_id' => $recommendationId,
                'module' => $module,
                'outcome' => $validated->outcome,
                'payload' => $payload,
                'notes' => $notes !== null && $notes !== '' ? $notes : null,
                'latitude' => $lat,
                'longitude' => $lng,
                'recorded_at' => SupervisorCaptureTime::resolve($shift, $recordedAt),
                'client_event_id' => $clientEventId,
            ]);
        });

        if ($review === null) {
            app(NotifySupervisionNoveltyService::class)->log($log);
        }

        return $log;
    }

    /**
     * @return array{reviews: int, logs: array<string, int>}
     */
    public function activityFor(SupervisorShift $shift): array
    {
        $counts = SupervisorFieldLog::query()
            ->where('supervisor_shift_id', $shift->id)
            ->selectRaw('module, count(*) as total')
            ->groupBy('module')
            ->pluck('total', 'module');

        $logs = [];
        foreach (SupervisorFieldModule::cases() as $module) {
            $logs[$module->value] = (int) ($counts[$module->value] ?? 0);
        }

        return [
            'reviews' => $shift->reviews()->count(),
            'logs' => $logs,
        ];
    }

    private function resolveClient(
        SupervisorShift $shift,
        SupervisorFieldModule $module,
        ?int $clientId,
    ): ?Client {
        if ($clientId === null) {
            if ($module->requiresClient()) {
                throw ValidationException::withMessages([
                    'client_id' => 'Este módulo requiere un sitio con Supervisión.',
                ]);
            }

            return null;
        }

        $client = Client::query()->find($clientId);
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => 'Sitio no encontrado.',
            ]);
        }

        if ((int) $client->security_company_id !== (int) $shift->security_company_id) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente no pertenece a esta empresa.',
            ]);
        }

        if (! $client->has_supervision) {
            throw ValidationException::withMessages([
                'client_id' => 'Este sitio no tiene Supervisión asignada.',
            ]);
        }

        return $client;
    }

    private function resolveReview(
        SupervisorShift $shift,
        SupervisorFieldModule $module,
        ?int $reviewId,
    ): ?SupervisorShiftReview {
        if (! $module->hangsOffReview()) {
            return null;
        }

        if ($reviewId === null) {
            throw ValidationException::withMessages([
                'supervisor_shift_review_id' => 'Guarde la revista de este puesto antes de registrar el módulo.',
            ]);
        }

        $review = SupervisorShiftReview::query()
            ->where('id', $reviewId)
            ->where('supervisor_shift_id', $shift->id)
            ->with('client')
            ->first();

        if ($review === null) {
            throw ValidationException::withMessages([
                'supervisor_shift_review_id' => 'La revista no pertenece a este turno.',
            ]);
        }

        return $review;
    }

    private function assertGps(SupervisorFieldModule $module, ?float $lat, ?float $lng): void
    {
        if (! $module->requiresGps()) {
            return;
        }

        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages([
                'latitude' => 'Active la ubicación del dispositivo para registrar este módulo.',
            ]);
        }
    }
}
