<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Installation;
use App\Models\Structure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class StructureRepository
{
    /** @return Collection<int, Structure> */
    public function treeForInstallation(int $clientId, int $installationId, ?string $search = null): Collection
    {
        $tree = Structure::query()
            ->where('client_id', $clientId)
            ->where('installation_id', $installationId)
            ->whereNull('parent_id')
            ->with([
                'structureType',
                'installation',
                'children' => fn ($q) => $q->with(['structureType', 'installation', 'children.structureType'])->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $needle = trim((string) $search);
        if ($needle === '') {
            return $tree;
        }

        return $this->filterTreeByName($tree, $needle);
    }

    /**
     * Conserva coincidencias y ancestros para no romper el árbol.
     *
     * @param  Collection<int, Structure>  $nodes
     * @return Collection<int, Structure>
     */
    private function filterTreeByName(Collection $nodes, string $needle): Collection
    {
        $filtered = $nodes->map(function (Structure $node) use ($needle): ?Structure {
            $children = $node->relationLoaded('children')
                ? $this->filterTreeByName($node->children, $needle)
                : collect();

            $matches = str_contains(mb_strtolower($node->name), mb_strtolower($needle));
            if (! $matches && $children->isEmpty()) {
                return null;
            }

            $clone = clone $node;
            $clone->setRelation('children', $children);

            return $clone;
        })->filter()->values();

        return new Collection($filtered->all());
    }

    /**
     * Nodos en orden de árbol (no alfabético plano), con profundidad para el select.
     *
     * @return list<array{id: int, name: string, depth: int}>
     */
    public function parentOptionsForInstallation(int $clientId, int $installationId): array
    {
        $nodes = Structure::query()
            ->where('client_id', $clientId)
            ->where('installation_id', $installationId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $byParent = $nodes->groupBy(fn (Structure $node): int => $node->parent_id === null ? 0 : (int) $node->parent_id);
        $rows = [];

        $walk = function (int $parentKey, int $depth) use (&$walk, &$rows, $byParent): void {
            foreach ($byParent->get($parentKey, collect()) as $node) {
                $rows[] = [
                    'id' => (int) $node->id,
                    'name' => (string) $node->name,
                    'depth' => $depth,
                ];
                $walk((int) $node->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    /**
     * @param  list<int>|null  $installationIds
     * @return array{installations: Collection<int, Installation>, nodeOptions: array<string, list<array{id: int, name: string, depth: int}>>}
     */
    public function censusPickerData(int $clientId, ?array $installationIds = null): array
    {
        $query = Installation::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->orderByDesc('is_client_site')
            ->orderBy('name');

        if ($installationIds !== null) {
            $query->whereIn('id', $installationIds);
        }

        $installations = $query->get();

        $nodeOptions = [];
        foreach ($installations as $installation) {
            $nodeOptions[(string) $installation->id] = $this->parentOptionsForInstallation(
                $clientId,
                (int) $installation->id,
            );
        }

        return [
            'installations' => $installations,
            'nodeOptions' => $nodeOptions,
        ];
    }

    /** @return Collection<int, Structure> */
    public function treeForClient(int $clientId): Collection
    {
        return Structure::query()
            ->where('client_id', $clientId)
            ->whereNull('parent_id')
            ->with([
                'structureType',
                'installation',
                'children' => fn ($q) => $q->with(['structureType', 'installation', 'children.structureType'])->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array{members: int, vehicles: int, pets: int}> */
    public function censusCounts(int $clientId): array
    {
        $memberCounts = DB::table('structure_members')
            ->select('structure_id', DB::raw('COUNT(*) as total'))
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->groupBy('structure_id')
            ->pluck('total', 'structure_id');

        $vehicleCounts = DB::table('vehicles')
            ->select('structure_id', DB::raw('COUNT(*) as total'))
            ->where('client_id', $clientId)
            ->whereNotNull('structure_id')
            ->whereNull('deleted_at')
            ->groupBy('structure_id')
            ->pluck('total', 'structure_id');

        $petCounts = DB::table('structure_pets')
            ->select('structure_id', DB::raw('COUNT(*) as total'))
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->groupBy('structure_id')
            ->pluck('total', 'structure_id');

        $structureIds = Structure::query()
            ->where('client_id', $clientId)
            ->pluck('id');

        $counts = [];
        foreach ($structureIds as $id) {
            $counts[$id] = [
                'members' => (int) ($memberCounts[$id] ?? 0),
                'vehicles' => (int) ($vehicleCounts[$id] ?? 0),
                'pets' => (int) ($petCounts[$id] ?? 0),
            ];
        }

        return $counts;
    }
}
