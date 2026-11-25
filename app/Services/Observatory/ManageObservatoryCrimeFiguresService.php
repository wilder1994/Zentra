<?php

declare(strict_types=1);

namespace App\Services\Observatory;

use App\Models\ObservatoryCrimeFigure;
use App\Models\ObservatoryCrimeType;
use App\Support\Geo\CaliComunaLayer;
use App\Support\Geo\CaliVeredaCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Catálogo de delitos y cifras por comuna de Cali. No son los tipos del reporte. */
final class ManageObservatoryCrimeFiguresService
{
    /** @var list<array{name: string, color: string}> */
    private const DEFAULTS = [
        ['name' => 'Hurtos', 'color' => '#2563eb'],
        ['name' => 'Lesiones personales', 'color' => '#ef4444'],
        ['name' => 'Violencia intrafamiliar', 'color' => '#ec4899'],
        ['name' => 'Delitos sexuales', 'color' => '#16a34a'],
        ['name' => 'Homicidios', 'color' => '#7c3aed'],
        ['name' => 'Secuestro', 'color' => '#64748b'],
    ];

    public function __construct(private readonly CaliComunaLayer $comunas) {}

    /** @return Collection<int, ObservatoryCrimeType> */
    public function types(int $companyId): Collection
    {
        $this->ensure($companyId);

        return ObservatoryCrimeType::query()
            ->where('security_company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function create(int $companyId, string $name, string $color, int $impacto = 5, int $probabilidad = 5): ObservatoryCrimeType
    {
        $this->ensure($companyId);
        $slug = $this->slug($name);
        if (ObservatoryCrimeType::query()->where('security_company_id', $companyId)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Ese delito ya existe.']);
        }

        return ObservatoryCrimeType::query()->create([
            'security_company_id' => $companyId,
            'name' => trim($name),
            'slug' => $slug,
            'color' => $color,
            'impacto' => max(1, min(10, $impacto)),
            'probabilidad' => max(1, min(10, $probabilidad)),
            'is_active' => true,
            'sort_order' => (int) ObservatoryCrimeType::query()->where('security_company_id', $companyId)->max('sort_order') + 1,
        ]);
    }

    public function update(ObservatoryCrimeType $type, string $name, string $color, bool $active, int $impacto = 5, int $probabilidad = 5): void
    {
        $slug = $this->slug($name);
        $taken = ObservatoryCrimeType::query()
            ->where('security_company_id', $type->security_company_id)
            ->where('slug', $slug)
            ->whereKeyNot($type->id)
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Ese delito ya existe.']);
        }

        $type->update([
            'name' => trim($name),
            'slug' => $slug,
            'color' => $color,
            'is_active' => $active,
            'impacto' => max(1, min(10, $impacto)),
            'probabilidad' => max(1, min(10, $probabilidad)),
        ]);
    }

    public function delete(ObservatoryCrimeType $type): void
    {
        $hasCases = ObservatoryCrimeFigure::query()
            ->where('observatory_crime_type_id', $type->id)
            ->where('total', '>', 0)
            ->exists();
        if ($hasCases) {
            throw ValidationException::withMessages(['name' => 'Tiene cifras guardadas. Solo se puede archivar.']);
        }

        $type->delete();
    }

    public function archive(ObservatoryCrimeType $type): void
    {
        $type->update(['is_active' => false]);
    }

    public function hasStoredFigures(ObservatoryCrimeType $type): bool
    {
        return ObservatoryCrimeFigure::query()
            ->where('observatory_crime_type_id', $type->id)
            ->where('total', '>', 0)
            ->exists();
    }

    /** @return list<int> */
    public function typeIdsWithFigures(int $companyId): array
    {
        return ObservatoryCrimeFigure::query()
            ->where('security_company_id', $companyId)
            ->where('total', '>', 0)
            ->distinct()
            ->pluck('observatory_crime_type_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $cells  comuna => typeId => total
     */
    public function saveYear(int $companyId, int $year, array $cells): void
    {
        $types = $this->types($companyId)
            ->where('is_active', true)
            ->keyBy(fn (ObservatoryCrimeType $type): int => (int) $type->id);
        $codes = array_values(array_unique(array_merge($this->comunas->codes(), CaliVeredaCatalog::codes())));

        DB::transaction(function () use ($companyId, $year, $cells, $types, $codes): void {
            foreach ($codes as $code) {
                foreach ($types as $typeId => $type) {
                    $total = max(0, (int) ($cells[$code][$typeId] ?? $cells[$code][(string) $typeId] ?? 0));
                    ObservatoryCrimeFigure::query()->updateOrCreate(
                        [
                            'observatory_crime_type_id' => $type->id,
                            'comuna_code' => $code,
                            'year' => $year,
                        ],
                        [
                            'security_company_id' => $companyId,
                            'total' => $total,
                        ],
                    );
                }
            }
        });
    }

    /**
     * Cifras del mapa para el periodo del tablero. Un año pinta ese año; si el rango cruza años, se suman.
     *
     * @return array{types: list<array{slug: string, name: string, color: string}>, rows: list<array{code: string, name: string, lat: float, lng: float, counts: array<string, int>}>}
     */
    public function mapPayload(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $types = $this->types($companyId)->where('is_active', true)->values();
        $years = $this->periodYears($from, $to);
        $figures = ObservatoryCrimeFigure::query()
            ->where('security_company_id', $companyId)
            ->whereIn('year', $years)
            ->get()
            ->groupBy(fn (ObservatoryCrimeFigure $figure): string => (string) $figure->comuna_code);

        $rows = [];
        foreach ($this->comunas->places() as $place) {
            $bucket = $figures[$place['code']] ?? collect();
            $counts = [];
            foreach ($types as $type) {
                $counts[$type->slug] = (int) $bucket
                    ->where('observatory_crime_type_id', $type->id)
                    ->sum('total');
            }
            $rows[] = [
                'code' => $place['code'],
                'name' => $place['name'],
                'lat' => $place['lat'],
                'lng' => $place['lng'],
                'counts' => $counts,
            ];
        }

        return [
            'types' => $types->map(fn (ObservatoryCrimeType $type): array => [
                'slug' => $type->slug,
                'name' => $type->name,
                'color' => $type->color,
            ])->all(),
            'rows' => $rows,
        ];
    }

    /** @return array<string, array<int, int>> */
    public function grid(int $companyId, int $year): array
    {
        $grid = [];
        $figures = ObservatoryCrimeFigure::query()
            ->where('security_company_id', $companyId)
            ->where('year', $year)
            ->get();
        foreach ($figures as $figure) {
            $grid[$figure->comuna_code][(int) $figure->observatory_crime_type_id] = (int) $figure->total;
        }

        return $grid;
    }

    /** @return list<int> */
    private function periodYears(?string $from, ?string $to): array
    {
        $start = $this->yearOf($from) ?? (int) now()->year;
        $end = $this->yearOf($to) ?? $start;
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $years = [];
        for ($year = $start; $year <= $end; $year++) {
            $years[] = $year;
        }

        return $years;
    }

    private function yearOf(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) substr($value, 0, 4);

        return $year >= 2000 ? $year : null;
    }

    /** @return list<int> */
    public function years(): array
    {
        $current = (int) now()->year;

        return [$current - 2, $current - 1, $current];
    }

    private function ensure(int $companyId): void
    {
        if (ObservatoryCrimeType::query()->where('security_company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $index => $row) {
            ObservatoryCrimeType::query()->create([
                'security_company_id' => $companyId,
                'name' => $row['name'],
                'slug' => $this->slug($row['name']),
                'color' => $row['color'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function slug(string $name): string
    {
        $slug = Str::slug(Str::ascii($name));

        return $slug !== '' ? $slug : 'delito';
    }
}
