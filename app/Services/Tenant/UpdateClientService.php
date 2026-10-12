<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\PartyType;
use App\Models\Client;
use App\Services\Company\ManageArchiveService;

final class UpdateClientService
{
    /** @param array<string, mixed> $attributes */
    public function execute(Client $client, array $attributes): Client
    {
        unset(
            $attributes['plan_tier'], $attributes['max_structures'], $attributes['slug'], $attributes['login_suffix'],
            $attributes['archived_at'], $attributes['archived_with_parent'],
        );

        if (isset($attributes['party_type']) && is_string($attributes['party_type'])) {
            $attributes['party_type'] = PartyType::from($attributes['party_type']);
        }

        if (empty($attributes['legal_name']) && ! empty($attributes['name'])) {
            $attributes['legal_name'] = $attributes['name'];
        }

        $lines = [];
        foreach (['access', 'supervision', 'observatory'] as $line) {
            $key = 'has_'.$line;
            $lines[$line] = array_key_exists($key, $attributes)
                ? $this->toBool($attributes[$key])
                : (bool) $client->{$key};
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $lines[$line];
            }
        }

        $archives = app(ManageArchiveService::class);
        $nextActive = array_key_exists('is_active', $attributes) ? $this->toBool($attributes['is_active']) : (bool) $client->is_active;
        unset($attributes['is_active']);

        if (! $lines['access']) {
            $attributes['show_personnel_folders'] = false;
        } elseif (array_key_exists('show_personnel_folders', $attributes)) {
            $attributes['show_personnel_folders'] = $this->toBool($attributes['show_personnel_folders']);
        }

        if ($nextActive && ! $client->is_active) {
            $client->fill($attributes);
            $archives->restoreClient($client);
            $client->save();

            return $client->fresh();
        }

        if ($nextActive) {
            $archives->assertLines($client, $lines);
        }

        $client->update($attributes);

        if (! $nextActive && $client->is_active) {
            $archives->archiveClient($client);
        }

        return $client->fresh();
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
