<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use App\Models\Client;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftZone;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Qué clientes ve un supervisor en turno: los de la zona del turno y las zonas añadidas en vivo,
 * propios (zona principal) o compartidos. Mientras la empresa no tenga ningún cliente con zona,
 * ve todos (transición).
 */
final class SupervisorClientScope
{
    public const NOT_IN_ZONE = 'Este cliente no está en tus zonas. Pide a la central que te habilite la zona.';

    public function companyUsesZones(int $companyId): bool
    {
        return Client::query()
            ->where('security_company_id', $companyId)
            ->whereNotNull('supervisor_zone_id')
            ->exists();
    }

    /** @return list<int> zonas que valen para lo capturado a esa hora (null = ahora). */
    public function zoneIds(SupervisorShift $shift, ?CarbonInterface $at = null): array
    {
        $extra = SupervisorShiftZone::query()
            ->where('supervisor_shift_id', $shift->id)
            ->where(function ($q) use ($at): void {
                $q->whereNull('removed_at');
                if ($at !== null) {
                    $q->orWhere('removed_at', '>', $at);
                }
            })
            ->pluck('supervisor_zone_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($shift->supervisor_zone_id !== null) {
            array_unshift($extra, (int) $shift->supervisor_zone_id);
        }

        return array_values(array_unique($extra));
    }

    /**
     * @param  list<int>  $zoneIds
     * @return list<int>
     */
    public function clientIdsForZones(int $companyId, array $zoneIds): array
    {
        if ($zoneIds === []) {
            return [];
        }

        $own = Client::query()
            ->where('security_company_id', $companyId)
            ->whereIn('supervisor_zone_id', $zoneIds)
            ->pluck('id');

        $shared = DB::table('supervisor_zone_shared_clients')
            ->whereIn('supervisor_zone_id', $zoneIds)
            ->pluck('client_id');

        return $own->concat($shared)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** @return list<int>|null null = sin restricción (transición). */
    public function visibleClientIds(SupervisorShift $shift, ?CarbonInterface $at = null): ?array
    {
        $companyId = (int) $shift->security_company_id;
        if (! $this->companyUsesZones($companyId)) {
            return null;
        }

        return $this->clientIdsForZones($companyId, $this->zoneIds($shift, $at));
    }

    public function assertVisible(SupervisorShift $shift, Client $client, ?CarbonInterface $at = null, string $field = 'client_id'): void
    {
        $visible = $this->visibleClientIds($shift, $at);
        if ($visible === null || in_array((int) $client->id, $visible, true)) {
            return;
        }

        throw ValidationException::withMessages([$field => self::NOT_IN_ZONE]);
    }

    /** Zona a la que cuenta un registro: la principal del cliente; sin cliente o sin zona, la del turno. */
    public function recordZoneId(SupervisorShift $shift, ?Client $client): ?int
    {
        if ($client?->supervisor_zone_id !== null) {
            return (int) $client->supervisor_zone_id;
        }

        return $shift->supervisor_zone_id !== null ? (int) $shift->supervisor_zone_id : null;
    }
}
