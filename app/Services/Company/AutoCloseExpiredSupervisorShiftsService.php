<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;
use Illuminate\Support\Facades\DB;

final class AutoCloseExpiredSupervisorShiftsService
{
    public function __construct(
        private readonly ResolveSupervisorShiftDeadlineService $deadlines,
        private readonly BuildSupervisorShiftSheetService $shiftSheets,
        private readonly BuildSupervisorTrailService $trail,
    ) {}

    public function gpsKm(SupervisorShift $shift): int
    {
        $shift->loadMissing(['locations' => fn ($q) => $q->orderBy('recorded_at')]);

        return (int) round((float) $this->trail->execute($shift->locations)['km']);
    }

    public function execute(?\DateTimeInterface $now = null): int
    {
        $nowAt = \Carbon\CarbonImmutable::parse(($now ?? now())->toIso8601String());
        $closed = 0;

        SupervisorShift::query()
            ->where('status', SupervisorShiftStatus::Open)
            ->with('shiftTemplate')
            ->orderBy('id')
            ->each(function (SupervisorShift $shift) use ($nowAt, &$closed): void {
                $deadline = $this->deadlines->graceDeadline($shift, $nowAt);
                if ($deadline === null || $nowAt->lt($deadline)) {
                    return;
                }

                $this->close($shift, $deadline, $nowAt);
                $closed++;
            });

        return $closed;
    }

    private function close(SupervisorShift $shift, \Carbon\CarbonImmutable $deadline, \Carbon\CarbonImmutable $nowAt): void
    {
        $template = $shift->shiftTemplate;
        $grace = ResolveSupervisorShiftDeadlineService::GRACE_MINUTES;
        $n = (int) ($shift->pending_outbox_count ?? 0);
        $queue = $n > 0
            ? ' Pendiente en cola: '.$n.' registro'.($n === 1 ? '' : 's').'.'
            : '';
        $note = $template?->ends_at
            ? 'Cierre automático: fin de turno '.substr((string) $template->ends_at, 0, 5).' + '.$grace.' min.'.$queue
            : 'Cierre automático: '.ResolveSupervisorShiftDeadlineService::FALLBACK_IDLE_HOURS.' h desde último GPS o inicio (sin plantilla de turno).'.$queue;

        DB::transaction(function () use ($shift, $note, $deadline, $nowAt): void {
            $traveled = (int) ($shift->km_traveled ?: $this->gpsKm($shift));
            $kmEnd = $shift->km_end !== null
                ? (int) $shift->km_end
                : ($shift->km_start !== null ? (int) $shift->km_start + $traveled : 0);
            $endedAt = $nowAt->gt($deadline) ? $deadline : $nowAt;
            if ($shift->started_at !== null && $endedAt->lt($shift->started_at)) {
                $endedAt = \Carbon\CarbonImmutable::parse($shift->started_at->toIso8601String());
            }
            $shift->update([
                'status' => SupervisorShiftStatus::Closed,
                'ended_at' => $endedAt,
                'km_end' => $kmEnd,
                'km_traveled' => $traveled,
                'notes' => trim((string) $shift->notes."\n".$note),
                'closed_by_system' => true,
            ]);

            if ($shift->km_start !== null && $shift->supervisor_fleet_vehicle_id !== null) {
                $shift->fleetVehicle()?->update(['last_km' => $kmEnd]);
            }

            $closed = $shift->fresh(['user', 'zone', 'shiftTemplate', 'securityCompany', 'reviews.client', 'fieldLogs.client', 'locations']);
            if ($closed instanceof SupervisorShift) {
                $this->shiftSheets->freeze($closed);
            }
        });
    }
}
