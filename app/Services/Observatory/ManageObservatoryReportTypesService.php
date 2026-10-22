<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Client;
use App\Models\ObservatoryReportType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Alta, cambio, baja y copia de tipos del Observatorio de un cliente (panel empresa). */
final class ManageObservatoryReportTypesService
{
    public function __construct(
        private readonly EnsureObservatoryReportTypesService $ensure,
    ) {}

    /** @param  array{name: string, level: int|string, color: string}  $data */
    public function create(Client $client, array $data, bool $active): ObservatoryReportType
    {
        $this->ensure->execute($client);
        $name = trim($data['name']);
        if ($this->nameTaken($client, $name)) {
            throw ValidationException::withMessages(['name' => 'Ese cliente ya tiene un tipo con ese nombre.']);
        }

        return ObservatoryReportType::query()->create([
            'client_id' => $client->id,
            'name' => $name,
            'slug' => $this->ensure->uniqueSlug($client, $name),
            'level' => (int) $data['level'],
            'color' => strtolower($data['color']),
            'is_active' => $active,
            'sort_order' => (int) $client->observatoryReportTypes()->max('sort_order') + 1,
        ]);
    }

    /** @param  array{name: string, level: int|string, color: string}  $data */
    public function update(ObservatoryReportType $type, array $data, bool $active): void
    {
        if (! $active && $this->othersActive($type) === 0) {
            throw ValidationException::withMessages(['is_active' => 'Deja al menos un tipo activo.']);
        }
        $name = trim($data['name']);
        $client = Client::query()->findOrFail($type->client_id);
        if ($this->nameTaken($client, $name, (int) $type->id)) {
            throw ValidationException::withMessages(['name' => 'Ese cliente ya tiene un tipo con ese nombre.']);
        }

        $type->update([
            'name' => $name,
            'level' => (int) $data['level'],
            'color' => strtolower($data['color']),
            'is_active' => $active,
        ]);
    }

    public function delete(ObservatoryReportType $type): void
    {
        if ($type->reports()->exists()) {
            throw ValidationException::withMessages(['type' => 'Tiene reportes. Desactívalo en vez de borrarlo.']);
        }
        if ($type->is_active && $this->othersActive($type) === 0) {
            throw ValidationException::withMessages(['type' => 'Deja al menos un tipo activo.']);
        }

        $type->delete();
    }

    /**
     * Crea en cada cliente destino los tipos del origen que le falten (mismo nombre sin tildes ni mayúsculas).
     *
     * @param  iterable<Client>  $targets
     * @return int Tipos creados
     */
    public function copy(Client $source, iterable $targets): int
    {
        $this->ensure->execute($source);
        $types = $source->observatoryReportTypes()->get();
        $created = 0;

        foreach ($targets as $target) {
            if ((int) $target->id === (int) $source->id) {
                continue;
            }
            $this->ensure->execute($target);
            $existing = $target->observatoryReportTypes()->pluck('name')->map(fn (string $name): string => self::fold($name))->all();
            $order = (int) $target->observatoryReportTypes()->max('sort_order');

            foreach ($types as $type) {
                if (in_array(self::fold($type->name), $existing, true)) {
                    continue;
                }
                ObservatoryReportType::query()->create([
                    'client_id' => $target->id,
                    'name' => $type->name,
                    'slug' => $this->ensure->uniqueSlug($target, $type->name),
                    'level' => (int) $type->level,
                    'color' => $type->color,
                    'is_active' => (bool) $type->is_active,
                    'sort_order' => ++$order,
                ]);
                $existing[] = self::fold($type->name);
                $created++;
            }
        }

        return $created;
    }

    private function othersActive(ObservatoryReportType $type): int
    {
        return ObservatoryReportType::query()
            ->where('client_id', $type->client_id)
            ->where('is_active', true)
            ->whereKeyNot($type->id)
            ->count();
    }

    private function nameTaken(Client $client, string $name, ?int $ignoreId = null): bool
    {
        $folded = self::fold($name);

        return $client->observatoryReportTypes()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('name')
            ->contains(fn (string $other): bool => self::fold($other) === $folded);
    }

    private static function fold(string $text): string
    {
        return Str::lower(Str::ascii(trim(preg_replace('/\s+/', ' ', $text) ?? $text)));
    }
}
