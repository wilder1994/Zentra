<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\RecordSupervisorShiftReviewInput;
use App\Enums\SupervisorFieldModule;
use App\Models\Employee;
use App\Models\SupervisorPost;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Support\Supervision\RecommendationEvidencePhotos;
use App\Support\Supervision\SupervisorCaptureTime;
use App\Support\Supervision\SupervisorFieldSheetIntro;
use App\Support\Supervision\SupervisorReviewGeofence;
use App\Support\Supervision\WeaponInspectionPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordSupervisorProReviewService
{
    public function __construct(
        private readonly LookupSupervisorVisitService $lookup,
        private readonly RecordSupervisorFieldLogService $logService,
    ) {}

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

        $review = DB::transaction(function () use ($shift, $input, $client, $post, $employee) {
            $company = $shift->securityCompany;
            $review = SupervisorShiftReview::query()->create([
                'supervisor_shift_id' => $shift->id,
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
                $this->logService->execute(
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
            }

            return $review->fresh(['client', 'supervisorPost.installation', 'employee']);
        });

        return $review;
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
