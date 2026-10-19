<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Client;
use App\Models\SupervisorZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Zona principal (una) y zonas compartidas de cada cliente con Supervisión. */
final class AssignClientZonesService
{
    /**
     * @return list<array{id: int, name: string, zone_id: ?int, shared: list<int>}>
     */
    public function rows(int $companyId): array
    {
        $clients = Client::query()
            ->where('security_company_id', $companyId)
            ->where('has_supervision', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'supervisor_zone_id']);

        $shared = DB::table('supervisor_zone_shared_clients')
            ->whereIn('client_id', $clients->pluck('id')->all() ?: [0])
            ->get(['client_id', 'supervisor_zone_id'])
            ->groupBy('client_id');

        return $clients->map(fn (Client $client) => [
            'id' => (int) $client->id,
            'name' => (string) $client->name,
            'zone_id' => $client->supervisor_zone_id !== null ? (int) $client->supervisor_zone_id : null,
            'shared' => collect($shared->get($client->id, []))
                ->map(fn ($row) => (int) $row->supervisor_zone_id)
                ->values()
                ->all(),
        ])->values()->all();
    }

    public function unassignedCount(int $companyId): int
    {
        return Client::query()
            ->where('security_company_id', $companyId)
            ->where('has_supervision', true)
            ->where('is_active', true)
            ->whereNull('supervisor_zone_id')
            ->count();
    }

    /**
     * @param  list<array{client_id: int, zone_id: ?int, shared: list<int>}>  $changes
     */
    public function apply(int $companyId, array $changes): int
    {
        $zoneIds = SupervisorZone::query()
            ->where('security_company_id', $companyId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $clientIds = array_map(fn (array $row) => (int) $row['client_id'], $changes);
        $clients = Client::query()
            ->where('security_company_id', $companyId)
            ->whereIn('id', $clientIds ?: [0])
            ->get()
            ->keyBy('id');

        if ($clients->count() !== count(array_unique($clientIds))) {
            throw ValidationException::withMessages(['changes' => 'Uno o más clientes no pertenecen a la empresa.']);
        }

        return DB::transaction(function () use ($changes, $clients, $zoneIds): int {
            $updated = 0;
            foreach ($changes as $row) {
                $client = $clients->get((int) $row['client_id']);
                $zoneId = $row['zone_id'] !== null ? (int) $row['zone_id'] : null;
                if ($zoneId !== null && ! in_array($zoneId, $zoneIds, true)) {
                    throw ValidationException::withMessages(['changes' => 'Zona no válida para '.$client->name.'.']);
                }

                $shared = collect($row['shared'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id !== $zoneId)
                    ->unique()
                    ->values()
                    ->all();
                if (array_diff($shared, $zoneIds) !== []) {
                    throw ValidationException::withMessages(['changes' => 'Zona compartida no válida para '.$client->name.'.']);
                }
                if ($zoneId === null && $shared !== []) {
                    throw ValidationException::withMessages(['changes' => $client->name.': elige la zona principal antes de compartir.']);
                }

                $client->supervisor_zone_id = $zoneId;
                $client->save();
                $client->sharedSupervisorZones()->sync($shared);
                $updated++;
            }

            return $updated;
        });
    }
}
