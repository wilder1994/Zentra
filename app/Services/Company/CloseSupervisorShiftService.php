<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CloseSupervisorShiftService
{
    public function __construct(
        private readonly BuildSupervisorShiftSheetService $shiftSheets,
    ) {}

    public function execute(
        SupervisorShift $shift,
        int $kmEnd,
        UploadedFile $odometerPhoto,
        UploadedFile $selfiePhoto,
        ?string $clientEventId = null,
    ): SupervisorShift {
        if ($clientEventId !== null && $clientEventId !== '') {
            $replay = SupervisorShift::query()
                ->where('close_client_event_id', $clientEventId)
                ->where('user_id', $shift->user_id)
                ->first();
            if ($replay !== null) {
                return $replay->load('fleetVehicle');
            }
        }

        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'El turno ya está cerrado.',
            ]);
        }

        if ($shift->km_start !== null && $kmEnd < (int) $shift->km_start) {
            throw ValidationException::withMessages([
                'km_end' => 'El kilometraje de cierre debe ser mayor o igual al de inicio ('.$shift->km_start.' km).',
            ]);
        }

        return DB::transaction(function () use ($shift, $kmEnd, $odometerPhoto, $selfiePhoto, $clientEventId) {
            $shift->load('fleetVehicle');
            $dir = 'supervision/'.$shift->security_company_id.'/'.$shift->id;
            $odoPath = $odometerPhoto->storeAs($dir, 'end_odometer.jpg', 'local');
            $selfiePath = $selfiePhoto->storeAs($dir, 'end_selfie.jpg', 'local');

            $traveled = $shift->km_start !== null ? $kmEnd - (int) $shift->km_start : null;

            $shift->update([
                'status' => SupervisorShiftStatus::Closed,
                'km_end' => $kmEnd,
                'km_traveled' => $traveled,
                'km_end_photo_path' => $odoPath,
                'km_end_selfie_path' => $selfiePath,
                'ended_at' => now(),
                'close_client_event_id' => $clientEventId,
            ]);

            if ($shift->fleetVehicle !== null) {
                $shift->fleetVehicle->update(['last_km' => $kmEnd]);
            }

            $closed = $shift->fresh(['fleetVehicle', 'user', 'zone', 'shiftTemplate', 'securityCompany', 'reviews.client', 'fieldLogs.client', 'locations']);
            if ($closed instanceof SupervisorShift) {
                $this->shiftSheets->freeze($closed);
            }

            return $closed ?? $shift;
        });
    }

    /**
     * El supervisor cerró sin red y el sistema ya había cerrado el turno: se guardan su km y fotos
     * sin mover la hora de cierre.
     */
    public function attachToSystemClosed(
        SupervisorShift $shift,
        int $kmEnd,
        UploadedFile $odometerPhoto,
        UploadedFile $selfiePhoto,
        ?string $clientEventId = null,
    ): SupervisorShift {
        if ($shift->km_start !== null && $kmEnd < (int) $shift->km_start) {
            throw ValidationException::withMessages([
                'km_end' => 'El kilometraje de cierre debe ser mayor o igual al de inicio ('.$shift->km_start.' km).',
            ]);
        }

        return DB::transaction(function () use ($shift, $kmEnd, $odometerPhoto, $selfiePhoto, $clientEventId) {
            $shift->load('fleetVehicle');
            $dir = 'supervision/'.$shift->security_company_id.'/'.$shift->id;

            $shift->update([
                'km_end' => $kmEnd,
                'km_traveled' => $shift->km_start !== null ? $kmEnd - (int) $shift->km_start : $shift->km_traveled,
                'km_end_photo_path' => $odometerPhoto->storeAs($dir, 'end_odometer.jpg', 'local'),
                'km_end_selfie_path' => $selfiePhoto->storeAs($dir, 'end_selfie.jpg', 'local'),
                'close_client_event_id' => $clientEventId,
                'notes' => trim((string) $shift->notes."\nCierre del supervisor recibido sin red después del cierre automático."),
            ]);

            if ($shift->fleetVehicle !== null) {
                $shift->fleetVehicle->update(['last_km' => $kmEnd]);
            }

            $closed = $shift->fresh(['fleetVehicle', 'user', 'zone', 'shiftTemplate', 'securityCompany', 'reviews.client', 'fieldLogs.client', 'locations']);
            if ($closed instanceof SupervisorShift) {
                $this->shiftSheets->freeze($closed);
            }

            return $closed ?? $shift;
        });
    }
}
