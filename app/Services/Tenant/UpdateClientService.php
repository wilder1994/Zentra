<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\PartyType;
use App\Models\Client;
use App\Services\Company\ManageArchiveService;
use App\Support\Catalog\ServiceLine;
use Illuminate\Validation\ValidationException;

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

        $modes = [];
        foreach (ServiceLine::all() as $line) {
            $modeKey = 'line_deactivate_mode_'.$line;
            if (array_key_exists($modeKey, $attributes)) {
                $modes[$line] = (string) $attributes[$modeKey];
            }
            unset($attributes[$modeKey]);
        }

        $desired = [];
        foreach (ServiceLine::all() as $line) {
            $key = ServiceLine::flag($line);
            $desired[$line] = array_key_exists($key, $attributes)
                ? $this->toBool($attributes[$key])
                : (bool) $client->{$key};
            unset($attributes[$key]);
        }

        $archives = app(ManageArchiveService::class);
        $linesService = app(DeactivateClientServiceLineService::class);
        $nextActive = array_key_exists('is_active', $attributes) ? $this->toBool($attributes['is_active']) : (bool) $client->is_active;
        unset($attributes['is_active']);

        if (! $desired[ServiceLine::ACCESS]) {
            $attributes['show_personnel_folders'] = false;
        } elseif (array_key_exists('show_personnel_folders', $attributes)) {
            $attributes['show_personnel_folders'] = $this->toBool($attributes['show_personnel_folders']);
        }

        if ($nextActive && ! $client->is_active) {
            $client->fill($attributes);
            $archives->restoreClient($client);
            $client->save();
            $this->applyLineChanges($client->fresh(), $desired, $modes, $archives, $linesService);

            return $client->fresh();
        }

        if ($nextActive) {
            $toAssert = [];
            foreach (ServiceLine::all() as $line) {
                $toAssert[$line] = $desired[$line];
            }
            $archives->assertLines($client, $toAssert);
        }

        $client->update($attributes);
        $client = $client->fresh();

        $this->applyLineChanges($client, $desired, $modes, $archives, $linesService);

        if (! $nextActive && $client->is_active) {
            $archives->archiveClient($client);
        }

        return $client->fresh();
    }

    /**
     * @param  array<string, bool>  $desired
     * @param  array<string, string>  $modes
     */
    private function applyLineChanges(
        Client $client,
        array $desired,
        array $modes,
        ManageArchiveService $archives,
        DeactivateClientServiceLineService $linesService,
    ): void {
        foreach (ServiceLine::all() as $line) {
            $flag = ServiceLine::flag($line);
            $was = (bool) $client->{$flag};
            $want = $desired[$line];

            if ($was && ! $want) {
                $mode = $modes[$line] ?? '';
                if (! ServiceLine::isValidMode($mode)) {
                    throw ValidationException::withMessages([
                        $flag => 'Elige si desactivas '.ServiceLine::label($line).' temporalmente o archivas su información operativa.',
                    ]);
                }
                $linesService->deactivate($client, $line, $mode);
                $client->refresh();

                continue;
            }

            if (! $was && $want) {
                $archives->assertLines($client, [$line => true]);
                $linesService->reactivate($client, $line);
                $client->refresh();
            }
        }
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
