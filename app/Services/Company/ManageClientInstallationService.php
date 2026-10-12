<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Geo\GeoAddressData;
use App\Enums\InstallationKind;
use App\Models\Client;
use App\Models\ClientUserInstallationAssignment;
use App\Models\Installation;
use App\Models\User;
use App\Services\Tenant\AssertInstallationSeats;
use App\Support\Auth\AssignableRoles;
use App\Support\Geo\CaliComunaLayer;
use Illuminate\Validation\ValidationException;

final class ManageClientInstallationService
{
    /**
     * @param  array{name: string, is_client_site?: bool, is_active?: bool, code?: ?string, commune?: ?string, rector_user_id?: ?int, geo?: ?GeoAddressData}  $data
     */
    public function create(Client $client, array $data): Installation
    {
        if (! $client->is_active) {
            throw ValidationException::withMessages([
                'installation' => 'El cliente «'.$client->name.'» está archivado. Restáuralo para crear instalaciones.',
            ]);
        }

        if ((bool) ($data['is_active'] ?? true)) {
            app(AssertInstallationSeats::class)->forClient($client, AssertInstallationSeats::ACTION_CREATE_INSTALLATION);
        }

        $isClientSite = (bool) ($data['is_client_site'] ?? false);
        $name = $isClientSite ? trim((string) $client->name) : trim($data['name']);

        if ($isClientSite) {
            $this->clearClientSiteFlag($client);
        }

        $geo = $this->geoAttributes($client, $isClientSite, $data['geo'] ?? null);
        $area = $this->areaAttributes(
            $data['commune'] ?? null,
            $geo['city'] ?? $client->city,
            $geo['latitude'] ?? null,
            $geo['longitude'] ?? null,
        );

        $kind = $this->resolveKind($data['kind'] ?? null);
        $dane = $this->resolveDane($kind, $data['dane_code'] ?? null);

        $installation = Installation::query()->create(array_merge([
            'client_id' => $client->id,
            'name' => $name,
            'code' => $this->resolveCode($client, $data['code'] ?? null),
            'kind' => $kind?->value,
            'dane_code' => $dane,
            'is_client_site' => $isClientSite,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'archived_at' => (bool) ($data['is_active'] ?? true) ? null : now(),
        ], $geo, $area));

        $this->syncRector($installation, isset($data['rector_user_id']) ? (int) $data['rector_user_id'] ?: null : null);

        return $installation->refresh();
    }

    /**
     * @param  array{name?: string, is_client_site?: bool, is_active?: bool, code?: ?string, commune?: ?string, rector_user_id?: ?int, geo?: ?GeoAddressData}  $data
     */
    public function update(Installation $installation, array $data): Installation
    {
        $client = $installation->client;
        abort_unless($client instanceof Client, 404);

        $isClientSite = array_key_exists('is_client_site', $data)
            ? (bool) $data['is_client_site']
            : $installation->is_client_site;

        $name = $isClientSite
            ? trim((string) $client->name)
            : (isset($data['name']) ? trim((string) $data['name']) : $installation->name);

        $installation->name = $name;

        if ($isClientSite) {
            $this->clearClientSiteFlag($client, $installation->id);
        }
        $installation->is_client_site = $isClientSite;

        $nextActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null;
        if ($nextActive === true && ! $installation->is_active) {
            app(ManageArchiveService::class)->restoreInstallation($installation);
            $installation->refresh();
        }

        if (array_key_exists('code', $data)) {
            $installation->code = $this->resolveCode($client, $data['code'] ?? null, $installation->id);
        }

        if (array_key_exists('kind', $data) || array_key_exists('dane_code', $data)) {
            $kind = $this->resolveKind($data['kind'] ?? $installation->kind);
            $installation->kind = $kind?->value;
            $installation->dane_code = $this->resolveDane($kind, $data['dane_code'] ?? $installation->dane_code, $installation->id);
        }

        $installation->fill($this->geoAttributes($client, $isClientSite, $data['geo'] ?? null));

        if (array_key_exists('commune', $data) || $installation->latitude !== null) {
            $installation->fill($this->areaAttributes(
                $data['commune'] ?? $installation->commune,
                $installation->city,
                $installation->latitude,
                $installation->longitude,
            ));
        }

        $installation->save();

        if (array_key_exists('rector_user_id', $data)) {
            $this->syncRector($installation, $data['rector_user_id'] !== null ? (int) $data['rector_user_id'] ?: null : null);
        }

        if ($nextActive === false && $installation->is_active) {
            app(ManageArchiveService::class)->archiveInstallation($installation);
        }

        return $installation->refresh();
    }

    private function geoAttributes(Client $client, bool $isClientSite, ?GeoAddressData $geo): array
    {
        if ($isClientSite) {
            if ($client->latitude === null || $client->longitude === null) {
                throw ValidationException::withMessages([
                    'is_client_site' => 'La ficha del cliente no tiene ubicación. Complétala o crea la instalación con el mapa.',
                ]);
            }

            return [
                'address' => $client->address,
                'city' => $client->city,
                'department' => $client->department,
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
            ];
        }

        if ($geo === null || $geo->latitude === null || $geo->longitude === null) {
            throw ValidationException::withMessages([
                'latitude' => 'Fija la ubicación de la instalación en el mapa.',
            ]);
        }

        return $geo->toModelAttributes();
    }

    private function resolveKind(mixed $value): ?InstallationKind
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return InstallationKind::tryFrom($value);
    }

    private function resolveDane(?InstallationKind $kind, mixed $value, ?int $ignoreId = null): ?string
    {
        $code = $this->nullableString($value);

        if ($kind?->requiresOfficialCode() && $code === null) {
            throw ValidationException::withMessages([
                'dane_code' => 'El colegio debe tener el código DANE de la instalación.',
            ]);
        }

        if (! $kind?->requiresOfficialCode()) {
            return null;
        }

        if (! preg_match('/^\d{8,12}$/', $code)) {
            throw ValidationException::withMessages([
                'dane_code' => 'El código DANE de la instalación debe tener entre 8 y 12 dígitos.',
            ]);
        }

        $exists = Installation::query()
            ->withoutGlobalScopes()
            ->where('dane_code', $code)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'dane_code' => 'Ya existe una instalación con ese código DANE.',
            ]);
        }

        return $code;
    }

    private function resolveCode(Client $client, ?string $code, ?int $ignoreId = null): string
    {
        $code = $this->nullableString($code);
        if ($code === null) {
            $code = $this->nextCode($client, $ignoreId);
        }

        $exists = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe una instalación con ese código en este cliente.',
            ]);
        }

        return $code;
    }

    private function nextCode(Client $client, ?int $ignoreId = null): string
    {
        return Installation::nextAvailableCode($client, $ignoreId);
    }

    private function syncRector(Installation $installation, ?int $rectorUserId): void
    {
        $installation->rector_user_id = $rectorUserId;

        if ($rectorUserId === null) {
            $installation->save();

            return;
        }

        $user = User::query()->find($rectorUserId);
        if ($user === null || ! $user->hasRole(AssignableRoles::CLIENT_INSTALLATION_ADMIN)) {
            throw ValidationException::withMessages([
                'rector_user_id' => 'El contacto debe ser un admin de instalaciones.',
            ]);
        }

        if (! $user->canAccessClient((int) $installation->client_id)) {
            throw ValidationException::withMessages([
                'rector_user_id' => 'El contacto no pertenece a este cliente.',
            ]);
        }

        $permission = $user->installationAssignments()->value('installation_permission') ?? 'admin';
        ClientUserInstallationAssignment::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'installation_id' => $installation->id,
            ],
            ['installation_permission' => $permission],
        );

        $installation->save();
    }

    private function areaAttributes(mixed $commune, mixed $city, mixed $lat = null, mixed $lng = null): array
    {
        return app(CaliComunaLayer::class)->areaAttributes($commune, $city, $lat, $lng);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function clearClientSiteFlag(Client $client, ?int $ignoreId = null): void
    {
        Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('is_client_site', true)
            ->update(['is_client_site' => false]);
    }
}
