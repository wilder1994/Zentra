<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\SupervisorShift;
use App\Models\SupervisorShiftZone;
use App\Models\SupervisorZone;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Zonas que la central habilita a un supervisor en pleno turno. Se van con el turno. */
final class ManageShiftZonesService
{
    /**
     * @return array{base: ?array{id: int, name: string}, extra: list<array{id: int, name: string, added_by: ?string, added_at: ?string}>, zones: list<array{id: int, name: string}>}
     */
    public function state(SupervisorShift $shift): array
    {
        $shift->loadMissing('zone');
        $extra = SupervisorShiftZone::query()
            ->where('supervisor_shift_id', $shift->id)
            ->active()
            ->with(['zone:id,name', 'addedBy:id,name'])
            ->orderBy('added_at')
            ->get();

        $zones = SupervisorZone::query()
            ->where('security_company_id', $shift->security_company_id)
            ->active()
            ->get(['id', 'name']);

        return [
            'base' => $shift->zone !== null ? ['id' => (int) $shift->zone->id, 'name' => (string) $shift->zone->name] : null,
            'extra' => $extra->map(fn (SupervisorShiftZone $row) => [
                'id' => (int) $row->supervisor_zone_id,
                'name' => (string) ($row->zone?->name ?? 'Zona'),
                'added_by' => $row->addedBy?->name,
                'added_at' => $row->added_at?->timezone((string) config('app.timezone'))->format('H:i'),
            ])->values()->all(),
            'zones' => $zones->map(fn (SupervisorZone $zone) => ['id' => (int) $zone->id, 'name' => (string) $zone->name])->values()->all(),
        ];
    }

    /** @param list<int> $zoneIds zonas añadidas que deben quedar vigentes (sin la del turno). */
    public function sync(SupervisorShift $shift, array $zoneIds, User $actor): void
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['zones' => 'El turno ya cerró.']);
        }

        $valid = SupervisorZone::query()
            ->where('security_company_id', $shift->security_company_id)
            ->where('is_active', true)
            ->whereIn('id', $zoneIds ?: [0])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $wanted = array_values(array_diff(array_unique(array_map('intval', $zoneIds)), [(int) $shift->supervisor_zone_id]));
        if (array_diff($wanted, $valid) !== []) {
            throw ValidationException::withMessages(['zones' => 'Zona no válida para esta empresa.']);
        }

        DB::transaction(function () use ($shift, $wanted, $actor): void {
            $active = SupervisorShiftZone::query()
                ->where('supervisor_shift_id', $shift->id)
                ->active()
                ->get()
                ->keyBy('supervisor_zone_id');

            foreach ($active as $zoneId => $row) {
                if (! in_array((int) $zoneId, $wanted, true)) {
                    $row->update(['removed_at' => now(), 'removed_by_user_id' => $actor->id]);
                }
            }

            foreach ($wanted as $zoneId) {
                if (! $active->has($zoneId)) {
                    SupervisorShiftZone::query()->create([
                        'supervisor_shift_id' => $shift->id,
                        'supervisor_zone_id' => $zoneId,
                        'added_by_user_id' => $actor->id,
                        'added_at' => now(),
                    ]);
                }
            }
        });
    }
}
