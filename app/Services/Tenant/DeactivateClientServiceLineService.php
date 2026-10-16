<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Client;
use App\Models\Location;
use App\Models\ObservatoryEvent;
use App\Models\SupervisorPost;
use App\Support\Catalog\ServiceLine;
use App\Support\Client\ClientPanelModules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apaga una línea comercial del cliente: temporal (solo cupo/UI) o con archivo
 * operativo (entra en retención/purga según config/retention.php).
 */
final class DeactivateClientServiceLineService
{
    public function deactivate(Client $client, string $line, string $mode): void
    {
        if (! ServiceLine::isValid($line) || ! ServiceLine::isValidMode($mode)) {
            throw ValidationException::withMessages([
                ServiceLine::flag($line) => 'Opción de desactivación no válida.',
            ]);
        }

        $flag = ServiceLine::flag($line);
        if (! (bool) $client->{$flag}) {
            return;
        }

        DB::transaction(function () use ($client, $line, $mode, $flag): void {
            $payload = [
                $flag => false,
                ServiceLine::archivedAtColumn($line) => $mode === ServiceLine::MODE_ARCHIVE ? now() : null,
            ];

            if ($line === ServiceLine::ACCESS) {
                $payload['show_personnel_folders'] = false;
            }

            $client->fill($payload);
            $this->syncPanelModule($client, $line, enabled: false);
            $client->save();

            if ($mode === ServiceLine::MODE_ARCHIVE) {
                $this->archiveOperationalData($client, $line);
            }
        });
    }

    public function reactivate(Client $client, string $line): void
    {
        if (! ServiceLine::isValid($line)) {
            return;
        }

        $flag = ServiceLine::flag($line);
        if ((bool) $client->{$flag}) {
            return;
        }

        DB::transaction(function () use ($client, $line, $flag): void {
            $wasArchived = $client->{ServiceLine::archivedAtColumn($line)} !== null;

            $client->fill([
                $flag => true,
                ServiceLine::archivedAtColumn($line) => null,
            ]);
            $this->syncPanelModule($client, $line, enabled: true);
            $client->save();

            if ($wasArchived) {
                $this->restoreOperationalData($client, $line);
            }
        });
    }

    /** @return array{installations: int, doors: int, posts: int, events: int} */
    public function impactCounts(Client $client): array
    {
        return [
            'installations' => $client->installations()->where('is_active', true)->count(),
            'doors' => Location::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->count(),
            'posts' => SupervisorPost::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count(),
            'events' => ObservatoryEvent::query()
                ->where('client_id', $client->id)
                ->whereNull('archived_at')
                ->count(),
        ];
    }

    private function archiveOperationalData(Client $client, string $line): void
    {
        $now = now();

        match ($line) {
            ServiceLine::ACCESS => Location::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'archived_at' => $now,
                    'archived_by_service_line' => true,
                ]),
            ServiceLine::SUPERVISION => SupervisorPost::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->update([
                    'is_active' => false,
                    'archived_at' => $now,
                    'archived_with_parent' => false,
                    'archived_by_service_line' => true,
                ]),
            ServiceLine::OBSERVATORY => ObservatoryEvent::query()
                ->where('client_id', $client->id)
                ->whereNull('archived_at')
                ->update([
                    'archived_at' => $now,
                    'archived_by_service_line' => true,
                ]),
            default => null,
        };
    }

    private function restoreOperationalData(Client $client, string $line): void
    {
        match ($line) {
            ServiceLine::ACCESS => Location::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('archived_by_service_line', true)
                ->update([
                    'is_active' => true,
                    'archived_at' => null,
                    'archived_by_service_line' => false,
                ]),
            ServiceLine::SUPERVISION => SupervisorPost::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('archived_by_service_line', true)
                ->whereNull('deleted_at')
                ->whereIn('installation_id', $client->installations()->where('is_active', true)->pluck('id'))
                ->update([
                    'is_active' => true,
                    'archived_at' => null,
                    'archived_by_service_line' => false,
                ]),
            ServiceLine::OBSERVATORY => ObservatoryEvent::query()
                ->where('client_id', $client->id)
                ->where('archived_by_service_line', true)
                ->update([
                    'archived_at' => null,
                    'archived_by_service_line' => false,
                ]),
            default => null,
        };
    }

    private function syncPanelModule(Client $client, string $line, bool $enabled): void
    {
        $key = match ($line) {
            ServiceLine::OBSERVATORY => ClientPanelModules::OBSERVATORY,
            ServiceLine::ACCESS => null, // Accesos base del panel no es opcional
            ServiceLine::SUPERVISION => null,
            default => null,
        };

        if ($key === null) {
            return;
        }

        $modules = ClientPanelModules::normalized($client->panel_modules);
        $modules[$key] = $enabled;
        $client->panel_modules = $modules;
    }
}
