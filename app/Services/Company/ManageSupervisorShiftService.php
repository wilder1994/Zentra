<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftLocation;
use App\Models\User;
use App\Support\Supervision\SupervisorCaptureTime;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ManageSupervisorShiftService
{
    public function assertCanOperate(User $user): void
    {
        if (! $user->hasRole('supervisor') || $user->security_company_id === null) {
            throw ValidationException::withMessages([
                'shift' => 'Solo un supervisor de la empresa puede abrir turno de Supervisión.',
            ]);
        }

        $company = $user->securityCompany;
        if ($company === null || ! $company->hasSupervisionPackage()) {
            throw ValidationException::withMessages([
                'shift' => 'La empresa no tiene Supervisión contratada.',
            ]);
        }
    }

    public function open(User $user, ?int $kmStart = null, ?string $photoPath = null): SupervisorShift
    {
        $this->assertCanOperate($user);

        $open = SupervisorShift::query()
            ->where('user_id', $user->id)
            ->where('status', SupervisorShiftStatus::Open)
            ->first();

        if ($open !== null) {
            throw ValidationException::withMessages([
                'shift' => 'Ya tiene un turno abierto. Ciérrelo antes de iniciar otro.',
            ]);
        }

        return SupervisorShift::query()->create([
            'security_company_id' => (int) $user->security_company_id,
            'user_id' => $user->id,
            'status' => SupervisorShiftStatus::Open,
            'km_start' => $kmStart,
            'km_start_photo_path' => $photoPath,
            'started_at' => CarbonImmutable::now(),
        ]);
    }

    public function ping(
        SupervisorShift $shift,
        float $lat,
        float $lng,
        ?float $accuracy = null,
        string $source = 'app',
        ?string $clientEventId = null,
        ?int $pendingOutbox = null,
        ?bool $screenOn = null,
        ?CarbonImmutable $recordedAt = null,
    ): SupervisorShiftLocation {
        SupervisorCaptureTime::assertAccepts($shift, $recordedAt);

        if ($pendingOutbox !== null && $shift->isOpen()) {
            $shift->update(['pending_outbox_count' => max(0, $pendingOutbox)]);
        }

        if ($clientEventId !== null && $clientEventId !== '') {
            $replay = SupervisorShiftLocation::query()
                ->where('client_event_id', $clientEventId)
                ->where('supervisor_shift_id', $shift->id)
                ->first();
            if ($replay !== null) {
                return $replay;
            }
        }

        return $shift->locations()->create([
            'recorded_at' => SupervisorCaptureTime::resolve($shift, $recordedAt),
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'source' => $source,
            'client_event_id' => $clientEventId,
            'screen_on' => $screenOn,
        ]);
    }

    public function close(SupervisorShift $shift, ?int $kmEnd = null, ?string $photoPath = null): SupervisorShift
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'El turno ya está cerrado.',
            ]);
        }

        $traveled = null;
        if ($kmEnd !== null && $shift->km_start !== null && $kmEnd >= $shift->km_start) {
            $traveled = $kmEnd - $shift->km_start;
        }

        $shift->update([
            'status' => SupervisorShiftStatus::Closed,
            'km_end' => $kmEnd,
            'km_traveled' => $traveled,
            'km_end_photo_path' => $photoPath,
            'ended_at' => CarbonImmutable::now(),
        ]);

        return $shift->refresh();
    }

    public function currentFor(User $user): ?SupervisorShift
    {
        return SupervisorShift::query()
            ->where('user_id', $user->id)
            ->where('status', SupervisorShiftStatus::Open)
            ->first();
    }

    /**
     * Turno al que pertenece algo capturado a esa hora: el abierto o, si se subió tarde
     * (sin red), el cerrado que cubría esa hora.
     */
    public function shiftForCapture(User $user, ?CarbonImmutable $capturedAt): ?SupervisorShift
    {
        $open = $this->currentFor($user);
        if ($capturedAt === null) {
            return $open;
        }

        $skew = SupervisorCaptureTime::SKEW_SECONDS;
        if ($open !== null && ($open->started_at === null
            || $capturedAt->gte(CarbonImmutable::parse($open->started_at)->subSeconds($skew)))) {
            return $open;
        }

        $closed = SupervisorShift::query()
            ->where('user_id', $user->id)
            ->where('status', SupervisorShiftStatus::Closed)
            ->where('started_at', '<=', $capturedAt->addSeconds($skew))
            ->where('ended_at', '>=', $capturedAt->subSeconds($skew))
            ->where('ended_at', '>=', CarbonImmutable::now()->subHours(SupervisorCaptureTime::LATE_SYNC_HOURS))
            ->orderByDesc('ended_at')
            ->first();

        return $closed ?? $open;
    }
}
