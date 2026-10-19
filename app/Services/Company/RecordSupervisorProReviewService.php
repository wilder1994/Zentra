<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\RecordSupervisorShiftReviewInput;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Models\Employee;
use App\Models\SupervisorPost;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Models\SupervisorTraining;
use App\Models\SupervisorUniformItem;
use App\Models\User;
use App\Services\Supervision\NotifySupervisionNoveltyService;
use App\Services\Supervision\ResolveTrainingTopicService;
use App\Support\Supervision\RecommendationEvidencePhotos;
use App\Support\Supervision\SupervisorCaptureTime;
use App\Support\Supervision\SupervisorClientScope;
use App\Support\Supervision\SupervisorFieldSheetIntro;
use App\Support\Supervision\SupervisorReviewGeofence;
use App\Support\Supervision\TrainingEvidence;
use App\Support\Supervision\UniformInspection;
use App\Support\Supervision\WeaponInspectionPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordSupervisorProReviewService
{
    public function __construct(
        private readonly LookupSupervisorVisitService $lookup,
        private readonly RecordSupervisorFieldLogService $logService,
        private readonly SupervisorClientScope $clientScope,
        private readonly NotifySupervisionNoveltyService $novelties,
        private readonly ResolveTrainingTopicService $topics,
    ) {}

    /** @var list<array{topic: string, employee: string, message: string}> */
    private array $discarded = [];

    public function execute(SupervisorShift $shift, RecordSupervisorShiftReviewInput $input): SupervisorShiftReview
    {
        SupervisorCaptureTime::assertAccepts($shift, $input->recordedAt);

        $user = $shift->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'shift' => 'Turno sin supervisor.',
            ]);
        }

        if ($input->clientEventId !== null && $input->clientEventId !== '') {
            $replay = SupervisorShiftReview::query()
                ->where('client_event_id', $input->clientEventId)
                ->where('supervisor_shift_id', $shift->id)
                ->first();
            if ($replay !== null) {
                return $replay->load(['client', 'supervisorPost.installation', 'employee']);
            }
        }

        $client = $this->lookup->companySupervisionClient($user, $input->clientId);
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente no tiene Supervisión o no pertenece a esta empresa.',
            ]);
        }

        $this->clientScope->assertVisible($shift, $client, $input->recordedAt);

        $post = SupervisorPost::query()
            ->withoutGlobalScopes()
            ->where('id', $input->supervisorPostId)
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereHas('installation', fn ($q) => $q->where('is_active', true))
            ->first();

        if ($post === null) {
            throw ValidationException::withMessages([
                'supervisor_post_id' => 'El puesto no pertenece a este cliente o no está activo.',
            ]);
        }

        app(SupervisorReviewGeofence::class)->assertWithinRadius(
            $post,
            $input->latitude,
            $input->longitude,
        );

        $employee = Employee::query()
            ->where('id', $input->employeeId)
            ->where('security_company_id', $shift->security_company_id)
            ->where('is_active', true)
            ->whereNull('ceased_at')
            ->whereHas('jobTitle', fn ($q) => $q->where('name', 'like', '%vigilante%'))
            ->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Seleccione un vigilante activo de la empresa.',
            ]);
        }

        $this->discarded = [];
        $this->assertReviewExtras($shift, $input);

        $review = DB::transaction(function () use ($shift, $input, $client, $post, $employee, $user) {
            $company = $shift->securityCompany;
            $review = SupervisorShiftReview::query()->create([
                'supervisor_shift_id' => $shift->id,
                'supervisor_zone_id' => $this->clientScope->recordZoneId($shift, $client),
                'client_id' => $client->id,
                'supervisor_post_id' => $post->id,
                'employee_id' => $employee->id,
                'notes' => $input->notes !== '' ? $input->notes : null,
                'sheet_intro' => SupervisorFieldSheetIntro::resolve($company?->field_sheet_intro),
                'has_novelty' => $input->hasNovelty,
                'latitude' => $input->latitude,
                'longitude' => $input->longitude,
                'recorded_at' => SupervisorCaptureTime::resolve($shift, $input->recordedAt),
                'client_event_id' => $input->clientEventId,
            ]);

            $dir = 'supervision/'.$shift->security_company_id.'/'.$shift->id.'/reviews/'.$review->id;
            $path = $this->storePhoto($input->guardPhoto, $dir, 'guard.jpg');
            $review->update(['guard_photo_path' => $path]);

            $recCount = 0;
            foreach ($input->logs as $index => $log) {
                $module = SupervisorFieldModule::from((string) $log['module']);
                if (! $module->hangsOffReview()) {
                    throw ValidationException::withMessages([
                        'logs' => 'Solo se adjuntan a la revista los módulos del puesto.',
                    ]);
                }
                $payload = is_array($log['payload'] ?? null) ? $log['payload'] : [];
                if ($module === SupervisorFieldModule::Recommendations) {
                    $recCount += count($payload['items'] ?? []);
                    if ($recCount > 3) {
                        throw ValidationException::withMessages([
                            'logs' => 'Máximo tres recomendaciones por puesto.',
                        ]);
                    }
                }
                if ($module === SupervisorFieldModule::Weapons) {
                    $payload['photos'] = $this->storeWeaponPhotos(
                        is_array($input->logPhotos[$index] ?? null) ? $input->logPhotos[$index] : [],
                        $dir.'/weapons/'.$index,
                        ($payload['cleaned'] ?? '') === 'yes',
                    );
                }
                if ($module === SupervisorFieldModule::Recommendations) {
                    $payload = $this->storeRecommendationPhotos(
                        $payload,
                        is_array($input->logPhotos[$index] ?? null) ? $input->logPhotos[$index] : [],
                        $dir.'/recommendations/'.$index,
                    );
                }
                $files = is_array($input->logPhotos[$index] ?? null) ? $input->logPhotos[$index] : [];
                if ($module === SupervisorFieldModule::Uniform) {
                    $statuses = array_column(is_array($payload['items'] ?? null) ? $payload['items'] : [], 'status');
                    $withNovelty = collect($statuses)->contains(fn ($status) => UniformInspection::hasNovelty((string) $status));
                    $payload['photos'] = $withNovelty
                        ? $this->storeEvidencePhotos($files, $dir.'/uniform', UniformInspection::SLOTS, 'La presentación personal con novedad requiere al menos una foto.')
                        : [];
                }
                if ($module === SupervisorFieldModule::Trainings) {
                    $this->recordTraining($shift, $review, $employee, $user, $payload, $files, $dir, $input);

                    continue;
                }
                $created = $this->logService->execute(
                    $shift,
                    $module,
                    $payload,
                    (int) $client->id,
                    (int) $review->id,
                    null,
                    $input->latitude,
                    $input->longitude,
                    recordedAt: $input->recordedAt,
                );
                if ($created->outcome !== null && $created->outcome !== SupervisorFieldOutcome::Ok && ! $review->has_novelty) {
                    $review->update(['has_novelty' => true]);
                }
            }

            return $review->fresh(['client', 'supervisorPost.installation', 'employee']);
        });

        $this->novelties->review($review);

        return $review;
    }

    /**
     * Capacitaciones repetidas (mismo vigilante, tema y mes) que se descartaron al guardar la revista.
     *
     * @return list<array{topic: string, employee: string, message: string}>
     */
    public function discardedTrainings(): array
    {
        return $this->discarded;
    }

    /** Presentación personal obligatoria (apps 2.3+, si la empresa tiene prendas) y una sola capacitación. */
    private function assertReviewExtras(SupervisorShift $shift, RecordSupervisorShiftReviewInput $input): void
    {
        $modules = array_map(fn (array $log) => (string) ($log['module'] ?? ''), $input->logs);
        $counts = array_count_values($modules);

        if (($counts[SupervisorFieldModule::Uniform->value] ?? 0) > 1) {
            throw ValidationException::withMessages(['logs' => 'Registre una sola presentación personal por revista.']);
        }
        if (($counts[SupervisorFieldModule::Trainings->value] ?? 0) > 1) {
            throw ValidationException::withMessages(['logs' => 'Registre una sola capacitación por revista.']);
        }

        $needsUniform = $input->appBuild >= SupervisorFieldModule::Uniform->needsAppBuild()
            && ! isset($counts[SupervisorFieldModule::Uniform->value])
            && SupervisorUniformItem::query()
                ->where('security_company_id', $shift->security_company_id)
                ->where('is_active', true)
                ->exists();
        if ($needsUniform) {
            throw ValidationException::withMessages(['logs' => 'Diligencie la presentación personal del vigilante antes de guardar la revista.']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     */
    private function recordTraining(
        SupervisorShift $shift,
        SupervisorShiftReview $review,
        Employee $employee,
        User $user,
        array $payload,
        array $files,
        string $dir,
        RecordSupervisorShiftReviewInput $input,
    ): void {
        $companyId = (int) $shift->security_company_id;
        $topic = $this->topics->fromPayload($companyId, $payload, $user);
        $at = CarbonImmutable::instance($review->recorded_at ?? now());
        $period = $at->timezone((string) config('app.timezone'))->format('Y-m');

        $already = SupervisorTraining::query()
            ->where('employee_id', $employee->id)
            ->where('supervisor_training_topic_id', $topic->id)
            ->where('period', $period)
            ->exists();
        if ($already) {
            $this->discarded[] = [
                'topic' => (string) $topic->name,
                'employee' => (string) $employee->fullName(),
                'message' => 'La capacitación de «'.$topic->name.'» a '.$employee->fullName().' se descartó: ya estaba capacitado en ese tema este mes.',
            ];

            return;
        }

        $payload['topic_id'] = (int) $topic->id;
        $payload['topic_name'] = (string) $topic->name;
        $payload['photos'] = $this->storeEvidencePhotos($files, $dir.'/training', TrainingEvidence::SLOTS, 'La capacitación requiere al menos una foto de evidencia.');

        $log = $this->logService->execute(
            $shift,
            SupervisorFieldModule::Trainings,
            $payload,
            (int) $review->client_id,
            (int) $review->id,
            null,
            $input->latitude,
            $input->longitude,
            recordedAt: $input->recordedAt,
        );
        $data = is_array($log->payload) ? $log->payload : [];
        if (! empty($data['started_at'])) {
            SupervisorCaptureTime::assertAccepts($shift, CarbonImmutable::parse((string) $data['started_at']));
        }

        SupervisorTraining::query()->create([
            'security_company_id' => $companyId,
            'supervisor_field_log_id' => $log->id,
            'supervisor_shift_review_id' => $review->id,
            'supervisor_shift_id' => $shift->id,
            'employee_id' => $employee->id,
            'supervisor_training_topic_id' => $topic->id,
            'user_id' => $shift->user_id,
            'client_id' => $review->client_id,
            'supervisor_zone_id' => $review->supervisor_zone_id,
            'kind' => (string) $data['kind'],
            'modality' => (string) $data['modality'],
            'started_at' => $data['started_at'] ?? null,
            'ended_at' => $data['ended_at'] ?? null,
            'minutes' => $data['minutes'] ?? null,
            'period' => $period,
            'recorded_at' => $log->recorded_at ?? $at,
        ]);
    }

    /**
     * @param  array<string, UploadedFile>  $files
     * @param  list<string>  $slots
     * @return array<string, string>
     */
    private function storeEvidencePhotos(array $files, string $directory, array $slots, string $missingMessage): array
    {
        $paths = [];
        foreach ($slots as $slot) {
            $file = $files[$slot] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->storeAs($directory, $slot.'.jpg', 'local');
            if ($path === false) {
                throw ValidationException::withMessages(['log_photos' => 'No se pudieron guardar las fotos de evidencia.']);
            }
            $paths[$slot] = $path;
        }
        if ($paths === []) {
            throw ValidationException::withMessages(['log_photos' => $missingMessage]);
        }

        return $paths;
    }

    private function storePhoto(UploadedFile $file, string $directory, string $name): string
    {
        $path = $file->storeAs($directory, $name, 'local');
        if ($path === false) {
            throw ValidationException::withMessages([
                'guard_photo' => 'No se pudo guardar la foto del vigilante.',
            ]);
        }

        return $path;
    }

    /**
     * @param  array<string, UploadedFile>  $files
     * @return array<string, string>
     */
    private function storeWeaponPhotos(array $files, string $directory, bool $cleaned): array
    {
        $paths = [];
        foreach (WeaponInspectionPhotos::requiredKeys($cleaned) as $slot) {
            $file = $files[$slot] ?? null;
            if (! $file instanceof UploadedFile) {
                throw ValidationException::withMessages([
                    'log_photos' => $cleaned
                        ? 'La revista de armamento con aseo requiere las cinco fotos de identificación y la de aseo.'
                        : 'La revista de armamento requiere las cinco fotos de identificación del arma.',
                ]);
            }
            $path = $file->storeAs($directory, $slot.'.jpg', 'local');
            if ($path === false) {
                throw ValidationException::withMessages([
                    'log_photos' => 'No se pudieron guardar las fotos del arma.',
                ]);
            }
            $paths[$slot] = $path;
        }

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     * @return array<string, mixed>
     */
    private function storeRecommendationPhotos(array $payload, array $files, string $directory): array
    {
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $paths = [];
            foreach (RecommendationEvidencePhotos::SLOTS as $slot) {
                $file = $files[$index.'_'.$slot] ?? null;
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'log_photos' => 'Cada recomendación requiere tres fotos del riesgo.',
                    ]);
                }
                $path = $file->storeAs($directory.'/'.$index, $slot.'.jpg', 'local');
                if ($path === false) {
                    throw ValidationException::withMessages([
                        'log_photos' => 'No se pudieron guardar las fotos de la recomendación.',
                    ]);
                }
                $paths[$slot] = $path;
            }
            $items[$index]['photos'] = $paths;
        }
        $payload['items'] = $items;

        return $payload;
    }
}
