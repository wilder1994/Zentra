<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\Client;
use App\Models\ObservatoryReportType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Alta, cambio y baja del catálogo de hechos de la empresa. */
final class ManageObservatoryReportTypesService
{
    public function __construct(
        private readonly EnsureObservatoryReportTypesService $ensure,
    ) {}

    /** @param  array{name: string, color: string, impacto: int|string, urgencia: int|string}  $data */
    public function create(Client $client, array $data, bool $active): ObservatoryReportType
    {
        $this->ensure->execute($client);
        $name = trim($data['name']);
        if ($this->nameTaken($client, $name)) {
            throw ValidationException::withMessages(['name' => 'Ya existe un registro con ese nombre.']);
        }

        return ObservatoryReportType::query()->create([
            'security_company_id' => $client->security_company_id,
            'name' => $name,
            'slug' => $this->ensure->uniqueSlug($client, $name),
            'impacto' => (int) $data['impacto'],
            'urgencia' => (int) $data['urgencia'],
            'color' => strtolower($data['color']),
            'is_active' => $active,
            'sort_order' => (int) $client->observatoryReportTypes()->max('sort_order') + 1,
        ]);
    }

    /** @param  array{name: string, color: string, impacto: int|string, urgencia: int|string}  $data */
    public function update(ObservatoryReportType $type, array $data, bool $active): void
    {
        if (! $active && $this->othersActive($type) === 0) {
            throw ValidationException::withMessages(['is_active' => 'Deja al menos un registro activo.']);
        }
        $name = trim($data['name']);
        $client = Client::query()->where('security_company_id', $type->security_company_id)->firstOrFail();
        if ($this->nameTaken($client, $name, (int) $type->id)) {
            throw ValidationException::withMessages(['name' => 'Ya existe un registro con ese nombre.']);
        }

        $type->update([
            'name' => $name,
            'impacto' => (int) $data['impacto'],
            'urgencia' => (int) $data['urgencia'],
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
            throw ValidationException::withMessages(['type' => 'Deja al menos un registro activo.']);
        }

        $type->delete();
    }

    private function othersActive(ObservatoryReportType $type): int
    {
        return ObservatoryReportType::query()
            ->where('security_company_id', $type->security_company_id)
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
