<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Domain\Structure\Data\CreateStructureData;
use App\Models\Client;
use App\Models\Installation;
use App\Models\Structure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateStructureService
{
    public function execute(CreateStructureData $data): Structure
    {
        return DB::transaction(function () use ($data): Structure {
            $client = Client::query()->findOrFail($data->clientId);
            $structureTypeId = $client->structure_type_id ?? $data->structureTypeId;

            if ($structureTypeId === null) {
                throw ValidationException::withMessages([
                    'name' => 'Este cliente no tiene tipo de estructura asignado. Configúralo en la ficha del cliente.',
                ]);
            }

            $installation = Installation::query()
                ->whereKey($data->installationId)
                ->where('client_id', $data->clientId)
                ->first();

            if ($installation === null) {
                throw ValidationException::withMessages([
                    'installation_id' => 'Selecciona una instalación de este cliente.',
                ]);
            }

            $parent = null;
            if ($data->parentId !== null) {
                $parent = Structure::query()->find($data->parentId);

                if ($parent === null || (int) $parent->installation_id !== $data->installationId) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'La unidad superior debe pertenecer a la misma sede.',
                    ]);
                }
            }

            return Structure::query()->create([
                'client_id' => $data->clientId,
                'installation_id' => $data->installationId,
                'parent_id' => $data->parentId,
                'name' => $data->name,
                'code' => $this->uniqueCode($data->installationId, $data->name, $parent),
                'structure_type_id' => (int) $structureTypeId,
                'max_occupancy' => $data->maxOccupancy,
                'is_active' => $data->isActive,
                'metadata' => $data->metadata,
            ]);
        });
    }

    private function uniqueCode(int $installationId, string $name, ?Structure $parent): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'nodo';
        }

        $base = filled($parent?->code)
            ? $parent->code.'-'.$slug
            : $slug;
        $base = Str::limit($base, 45, '');

        $code = $base;
        $suffix = 2;

        while ($this->codeTaken($installationId, $code)) {
            $code = Str::limit($base, 40, '').'-'.$suffix;
            $suffix++;

            if ($suffix > 500) {
                throw ValidationException::withMessages([
                    'name' => 'No se pudo generar un código interno único para este nodo.',
                ]);
            }
        }

        return $code;
    }

    private function codeTaken(int $installationId, string $code): bool
    {
        return Structure::query()
            ->where('installation_id', $installationId)
            ->where('code', $code)
            ->exists();
    }
}
