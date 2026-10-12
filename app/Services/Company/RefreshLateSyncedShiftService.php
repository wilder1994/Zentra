<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;

/** Lo que llegó sin red a un turno ya cerrado: rehace la ficha congelada y los km GPS del cierre automático. */
final class RefreshLateSyncedShiftService
{
    public function __construct(
        private readonly AutoCloseExpiredSupervisorShiftsService $autoClose,
        private readonly BuildSupervisorShiftSheetService $shiftSheets,
    ) {}

    public function execute(SupervisorShift $shift): void
    {
        if ($shift->isOpen()) {
            return;
        }

        $shift->unsetRelation('locations');
        if ($shift->closed_by_system && $shift->km_end_photo_path === null) {
            $shift->update(['km_traveled' => $this->autoClose->gpsKm($shift)]);
        }

        $fresh = $shift->fresh(['user', 'zone', 'shiftTemplate', 'fleetVehicle', 'securityCompany', 'reviews.client', 'fieldLogs.client', 'locations']);
        if ($fresh instanceof SupervisorShift) {
            $this->shiftSheets->freeze($fresh);
        }
    }
}
