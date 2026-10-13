<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Enums\AuthorizationStatus;
use App\Enums\PetSpecies;
use App\Enums\VisitorCategory;
use App\Models\Client;
use App\Models\Installation;
use App\Models\MemberType;
use App\Models\Structure;
use App\Models\StructureAppUser;
use App\Models\StructureMember;
use App\Models\StructurePet;
use App\Models\StructureType;
use App\Models\Vehicle;
use App\Models\VisitorPreAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SeedPilotStructuresService
{
    public function execute(Client $client): void
    {
        if (Structure::query()->where('client_id', $client->id)->where('code', 'TORRE-A')->exists()) {
            return;
        }

        $companyId = (int) $client->security_company_id;
        $blockId = StructureType::idByCode($companyId, 'block');
        $apartmentId = StructureType::idByCode($companyId, 'apartment');

        DB::transaction(function () use ($client, $blockId, $apartmentId): void {
            $installation = Installation::query()
                ->where('client_id', $client->id)
                ->orderByDesc('is_client_site')
                ->orderBy('id')
                ->first();

            $tower = Structure::query()->firstOrCreate(
                ['client_id' => $client->id, 'code' => 'TORRE-A'],
                [
                    'installation_id' => $installation?->id,
                    'parent_id' => null,
                    'name' => 'Torre A',
                    'structure_type_id' => $blockId,
                    'is_active' => true,
                ]
            );

            if ($installation !== null && $tower->installation_id === null) {
                $tower->update(['installation_id' => $installation->id, 'parent_id' => null]);
            }

            $apartments = [];
            for ($i = 1; $i <= 10; $i++) {
                $code = sprintf('TORRE-A-%03d', $i);
                $apartments[] = Structure::query()->firstOrCreate(
                    ['client_id' => $client->id, 'code' => $code],
                    [
                        'installation_id' => $installation?->id,
                        'parent_id' => $tower->id,
                        'name' => "Apto {$i}01",
                        'structure_type_id' => $apartmentId,
                        'max_occupancy' => 4,
                        'is_active' => true,
                    ]
                );
            }

            $typeNames = ['Propietario', 'Arrendatario', 'Familiar', 'Invitado permanente', 'Empleado', 'Administrador'];
            $typeIds = [];
            foreach ($typeNames as $i => $name) {
                $type = MemberType::query()->firstOrCreate(
                    ['client_id' => $client->id, 'name' => $name],
                    [
                        'slug' => Str::slug($name),
                        'is_active' => true,
                        'sort_order' => ($i + 1) * 10,
                    ],
                );
                $typeIds[] = $type->id;
            }
            $memberTypeIds = [$typeIds[0], $typeIds[1], $typeIds[2]];
            $memberIndex = 0;

            foreach ($apartments as $apartment) {
                for ($j = 0; $j < 2; $j++) {
                    $memberIndex++;
                    $doc = sprintf('1000%06d', $memberIndex);

                    StructureMember::query()->firstOrCreate(
                        ['client_id' => $client->id, 'document_number' => $doc],
                        [
                            'structure_id' => $apartment->id,
                            'first_name' => "Persona{$memberIndex}",
                            'last_name' => 'Piloto',
                            'document_type' => 'CC',
                            'document_number' => $doc,
                            'birth_date' => '1990-01-15',
                            'phone_primary' => '+57300'.str_pad((string) $memberIndex, 7, '0', STR_PAD_LEFT),
                            'email' => "persona{$memberIndex}@piloto.test",
                            'member_type_id' => $memberTypeIds[$j % 3],
                            'has_app_access' => $j === 0,
                            'access_code' => strtoupper(Str::random(12)),
                            'is_active' => true,
                        ]
                    );
                }

                if ($memberIndex % 3 === 0) {
                    StructurePet::query()->firstOrCreate(
                        ['client_id' => $client->id, 'structure_id' => $apartment->id, 'name' => "Mascota{$memberIndex}"],
                        [
                            'species' => PetSpecies::Dog,
                            'breed' => 'Mestizo',
                            'is_potentially_dangerous' => false,
                        ]
                    );
                }

                Vehicle::query()->firstOrCreate(
                    ['client_id' => $client->id, 'plate' => 'ABC'.str_pad((string) $memberIndex, 3, '0', STR_PAD_LEFT)],
                    [
                        'structure_id' => $apartment->id,
                        'brand' => 'Toyota',
                        'model' => 'Corolla',
                        'color' => 'Gris',
                        'type' => 'carro',
                        'is_visitor_vehicle' => false,
                    ]
                );
            }

            $host = StructureMember::query()->where('client_id', $client->id)->first();
            $apartment = $apartments[0] ?? null;
            if ($apartment) {
                StructureMember::query()->firstOrCreate(
                    ['client_id' => $client->id, 'document_number' => '1099000001'],
                    [
                        'structure_id' => $apartment->id,
                        'first_name' => 'Menor',
                        'last_name' => 'Piloto',
                        'document_type' => 'TI',
                        'birth_date' => now()->subYears(12)->toDateString(),
                        'minor_treatment_accepted_at' => now(),
                        'member_type_id' => $memberTypeIds[2],
                        'has_app_access' => false,
                        'access_code' => strtoupper(Str::random(12)),
                        'is_active' => true,
                    ]
                );
            }
            if ($host) {
                for ($k = 1; $k <= 5; $k++) {
                    $day = now()->addDays($k);
                    $authorization = VisitorPreAuthorization::query()->firstOrCreate(
                        [
                            'client_id' => $client->id,
                            'structure_id' => $host->structure_id,
                            'visitor_name' => "Visitante Piloto {$k}",
                            'valid_for_date' => $day->toDateString(),
                        ],
                        [
                            'installation_id' => $host->structure?->installation_id,
                            'member_id' => $host->id,
                            'visitor_document' => sprintf('900%06d', $k),
                            'visitor_category' => VisitorCategory::Visitor,
                            'valid_from' => $day->copy()->startOfDay(),
                            'valid_until' => $day->copy()->endOfDay(),
                            'status' => AuthorizationStatus::Pending,
                            'qr_auth_token' => Str::random(40),
                        ]
                    );
                    $authorization->guests()->firstOrCreate(
                        ['document_number' => sprintf('900%06d', $k)],
                        ['client_id' => $client->id, 'first_name' => 'Visitante', 'last_name' => "Piloto {$k}"],
                    );
                }

                StructureAppUser::query()->firstOrCreate(
                    ['client_id' => $client->id, 'username' => 'admin.palmas'],
                    [
                        'member_id' => $host->id,
                        'email' => 'admin.palmas@'.$client->login_suffix,
                        'password' => 'AppUser123!',
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
