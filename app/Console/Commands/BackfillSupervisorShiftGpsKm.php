<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;
use App\Services\Company\AutoCloseExpiredSupervisorShiftsService;
use Illuminate\Console\Command;

final class BackfillSupervisorShiftGpsKm extends Command
{
    protected $signature = 'supervision:backfill-gps-km';

    protected $description = 'Asigna km por GPS a turnos cerrados por el sistema sin km de odómetro';

    public function handle(AutoCloseExpiredSupervisorShiftsService $service): int
    {
        $updated = 0;

        SupervisorShift::query()
            ->where('status', SupervisorShiftStatus::Closed)
            ->where('closed_by_system', true)
            ->where(fn ($q) => $q->whereNull('km_traveled')->orWhere('km_traveled', 0))
            ->orderBy('id')
            ->each(function (SupervisorShift $shift) use ($service, &$updated): void {
                $km = $service->gpsKm($shift);
                if ($km > 0) {
                    $shift->forceFill(['km_traveled' => $km])->save();
                    $updated++;
                }
                $shift->unsetRelation('locations');
            });

        $this->info("Turnos actualizados: {$updated}");

        return self::SUCCESS;
    }
}
