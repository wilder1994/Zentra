<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Client;
use App\Models\Installation;
use App\Models\Location;
use App\Services\Ops\RecordOperationalAlertService;
use Illuminate\Validation\ValidationException;

final class ManageClientAccessPointService
{
    /**
     * @param  array{
     *     installation_id: int,
     *     code?: string|null,
     *     name: string,
     *     address?: string|null,
     *     phone?: string|null,
     *     latitude?: float|null,
     *     longitude?: float|null,
     *     geo_radius_m?: int|null,
     *     is_active?: bool
     * }  $data
     */
    public function create(Client $client, array $data): Location
    {
        $installation = $this->installationOfClient($client, (int) $data['installation_id']);
        $code = $this->resolveCode($client, $data['code'] ?? null);

        $location = Location::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'code' => $code,
            'name' => trim($data['name']),
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'geo_radius_m' => $data['geo_radius_m'] ?? 250,
            'type' => 'access_point',
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->record(
            $client,
            $installation,
            'Alta de puerta «'.$location->name.'» ('.$location->code.') · '.$installation->name,
            ['action' => 'door_created', 'location_id' => $location->id],
        );

        return $location;
    }

    /**
     * @param  array{
     *     installation_id?: int,
     *     code?: string|null,
     *     name?: string,
     *     address?: string|null,
     *     phone?: string|null,
     *     latitude?: float|null,
     *     longitude?: float|null,
     *     geo_radius_m?: int|null,
     *     is_active?: bool
     * }  $data
     */
    public function update(Location $location, array $data): Location
    {
        $client = $location->client;
        abort_unless($client instanceof Client, 404);
        $installation = $location->installation;

        if (isset($data['installation_id'])) {
            $installation = $this->installationOfClient($client, (int) $data['installation_id']);
            $location->installation_id = $installation->id;
        }

        if (array_key_exists('code', $data) && $data['code'] !== null && trim((string) $data['code']) !== '') {
            $code = strtoupper(trim((string) $data['code']));
            $this->assertUniqueCode($client, $code, $location->id);
            $location->code = $code;
        }

        if (isset($data['name'])) {
            $location->name = trim($data['name']);
        }

        foreach (['address', 'phone', 'latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data)) {
                $location->{$field} = $data[$field];
            }
        }

        if (array_key_exists('geo_radius_m', $data) && $data['geo_radius_m'] !== null) {
            $location->geo_radius_m = (int) $data['geo_radius_m'];
        }

        if (array_key_exists('is_active', $data)) {
            $location->is_active = (bool) $data['is_active'];
        }

        $location->type = 'access_point';
        $location->save();

        if ($installation instanceof Installation) {
            $this->record(
                $client,
                $installation,
                'Cambio en puerta «'.$location->name.'» ('.$location->code.') · '.$installation->name,
                ['action' => 'door_updated', 'location_id' => $location->id],
            );
        }

        return $location->refresh();
    }

    public function archive(Location $location): void
    {
        $client = $location->client;
        abort_unless($client instanceof Client, 404);
        $installation = $location->installation;

        $location->is_active = false;
        $location->save();

        if ($installation instanceof Installation) {
            $this->record(
                $client,
                $installation,
                'Archivo de puerta «'.$location->name.'» ('.$location->code.') · '.$installation->name,
                ['action' => 'door_archived', 'location_id' => $location->id],
            );
        }
    }

    public function restore(Location $location): void
    {
        $client = $location->client;
        abort_unless($client instanceof Client, 404);
        $installation = $location->installation;

        $location->is_active = true;
        $location->save();

        if ($installation instanceof Installation) {
            $this->record(
                $client,
                $installation,
                'Restauración de puerta «'.$location->name.'» ('.$location->code.') · '.$installation->name,
                ['action' => 'door_restored', 'location_id' => $location->id],
            );
        }
    }

    public function delete(Location $location): void
    {
        $client = $location->client;
        $installation = $location->installation;
        $label = 'Baja de puerta «'.$location->name.'» ('.$location->code.')'
            .($installation instanceof Installation ? ' · '.$installation->name : '');

        $location->delete();

        if ($client instanceof Client && $installation instanceof Installation) {
            $this->record(
                $client,
                $installation,
                $label,
                ['action' => 'door_deleted', 'location_id' => $location->id],
            );
        }
    }

    private function resolveCode(Client $client, ?string $code): string
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';
        if ($code === '') {
            $code = Location::nextAvailableCode($client);
        }

        $this->assertUniqueCode($client, $code);

        return $code;
    }

    private function installationOfClient(Client $client, int $installationId): Installation
    {
        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('id', $installationId)
            ->where('client_id', $client->id)
            ->first();

        if ($installation === null) {
            throw ValidationException::withMessages([
                'installation_id' => 'La instalación no pertenece a este cliente.',
            ]);
        }

        return $installation;
    }

    private function assertUniqueCode(Client $client, string $code, ?int $ignoreId = null): void
    {
        $exists = Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe un acceso con ese código en este cliente.',
            ]);
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function record(Client $client, Installation $installation, string $body, array $payload): void
    {
        app(RecordOperationalAlertService::class)->serviceChange(
            $client,
            $body,
            $installation,
            null,
            auth()->user(),
            $payload,
        );
    }
}
