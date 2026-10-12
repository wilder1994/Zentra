<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\BloodGroup;
use App\Enums\ClientPlanTier;
use App\Enums\CompanyPackageSku;
use App\Enums\Sex;
use App\Enums\SupervisionPackageSku;
use App\Models\AccessLog;
use App\Models\Building;
use App\Models\Client;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\Correspondence;
use App\Models\Employee;
use App\Models\GuardLog;
use App\Models\HousingUnit;
use App\Models\Installation;
use App\Models\Location;
use App\Models\PreAuthorization;
use App\Models\PricingSettings;
use App\Models\Resident;
use App\Models\SecurityCompany;
use App\Models\StructureType;
use App\Models\SupervisorPost;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use App\Services\Tenant\AssignCompanyPackageService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use App\Support\Client\ClientPanelModules;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TenantSeeder extends Seeder
{
    public function run(): void
    {
        PricingSettings::current();

        $company = SecurityCompany::query()->firstOrCreate(
            ['tax_id' => '900123456-1'],
            [
                'legal_name' => 'SJ Seguridad Privada S.A.S.',
                'trade_name' => 'SJ Seguridad',
                'email' => 'contacto@sj-seguridad.test',
                'phone' => '+57 300 000 0000',
                'is_active' => true,
            ]
        );
        if ($company->trade_name !== 'SJ Seguridad') {
            $company->update(['trade_name' => 'SJ Seguridad']);
        }

        app(AssignCompanyPackageService::class)->execute(
            $company,
            CompanyPackageSku::from('pack_50_manual'),
            BillingCycle::Monthly,
        );

        app(AssignCompanySupervisionPackageService::class)->execute(
            $company,
            SupervisionPackageSku::fromSize(10),
        );

        $company->update([
            'indexing_seats' => 50,
            'observatory_seats' => 50,
            'party_type' => 'legal_entity',
            'address' => 'Av. 6N # 28-90, Cali',
            'latitude' => 3.4516,
            'longitude' => -76.5320,
        ]);

        app(SeedSupervisorIntakeDefaultsService::class)->execute((int) $company->id);
        $this->seedPilotVigilante((int) $company->id);
        $this->ensurePilotStructureTypes((int) $company->id);

        $palmas = Client::query()->updateOrCreate(
            ['security_company_id' => $company->id, 'slug' => 'palmas-del-ingenio'],
            [
                'name' => 'Palmas del Ingenio',
                'party_type' => 'legal_entity',
                'legal_name' => 'Conjunto Residencial Palmas del Ingenio PH',
                'document_type' => 'NIT',
                'tax_id' => '900111222-1',
                'email' => 'admin@palmas.test',
                'representative_name' => 'Ana Admin',
                'representative_email' => 'ana@palmas.test',
                'structure_type_id' => StructureType::idByCode((int) $company->id, 'ph'),
                'login_suffix' => 'palmasdelingenio',
                'address' => 'Cra 100 # 14-25, Cali',
                'latitude' => 3.3678,
                'longitude' => -76.5275,
                'plan_tier' => ClientPlanTier::Economic,
                'max_structures' => ClientPlanTier::Economic->maxStructures(),
                'access_url' => 'https://zentra.test',
                'is_active' => true,
                'has_access' => true,
                'has_supervision' => true,
                'has_observatory' => true,
                'panel_modules' => array_merge(ClientPanelModules::defaultOptional(), [
                    ClientPanelModules::OBSERVATORY => true,
                ]),
                'service_started_at' => now()->subMonths(6)->toDateString(),
            ]
        );

        $torres = Client::query()->updateOrCreate(
            ['security_company_id' => $company->id, 'slug' => 'torres-loma'],
            [
                'name' => 'Torres de la Loma',
                'party_type' => 'legal_entity',
                'legal_name' => 'Torres de la Loma PH',
                'document_type' => 'NIT',
                'tax_id' => '900333444-2',
                'email' => 'admin@torres.test',
                'representative_name' => 'Luis Torres',
                'representative_email' => 'luis@torres.test',
                'structure_type_id' => StructureType::idByCode((int) $company->id, 'ph'),
                'login_suffix' => 'torresloma',
                'address' => 'Av 6N # 28-90, Cali',
                'latitude' => 3.3742,
                'longitude' => -76.5198,
                'plan_tier' => ClientPlanTier::Economic,
                'max_structures' => ClientPlanTier::Economic->maxStructures(),
                'access_url' => 'https://zentra.test',
                'is_active' => true,
                'has_access' => true,
                'has_observatory' => true,
                'panel_modules' => array_merge(ClientPanelModules::defaultOptional(), [
                    ClientPanelModules::OBSERVATORY => true,
                ]),
                'service_started_at' => now()->subMonths(3)->toDateString(),
            ]
        );

        $this->seedClientSiteTree($palmas, [
            ['code' => 'PA-01', 'name' => 'Puerta principal', 'address' => 'Av. Principal #1-1'],
            ['code' => 'PA-02', 'name' => 'Puerta de vidrio', 'address' => 'Calle 2 #3-4'],
            ['code' => 'PA-03', 'name' => 'Acceso vehicular', 'address' => 'Entrada parqueaderos'],
            ['code' => 'PA-04', 'name' => 'Portería peatonal', 'address' => 'Calle lateral'],
        ], ['Portería principal', 'Parqueadero']);

        $this->seedClientSiteTree($torres, [
            ['code' => 'TL-01', 'name' => 'Puerta principal', 'address' => 'Av 6N # 28-90'],
        ], ['Portería principal']);

        $this->backfillOperationalData($palmas->id);
    }

    /**
     * @param  list<array{code: string, name: string, address: string}>  $accessPoints
     * @param  list<string>  $postNames
     */
    private function seedClientSiteTree(Client $client, array $accessPoints, array $postNames): void
    {
        $site = Installation::query()->updateOrCreate(
            ['client_id' => $client->id, 'name' => $client->name],
            [
                'is_client_site' => true,
                'is_active' => true,
                'address' => $client->address,
                'city' => $client->city,
                'department' => $client->department,
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
            ]
        );

        if ($client->has_access) {
            foreach ($accessPoints as $point) {
                Location::query()->firstOrCreate(
                    ['code' => $point['code'], 'client_id' => $client->id],
                    [
                        'installation_id' => $site->id,
                        'name' => $point['name'],
                        'address' => $point['address'],
                        'type' => 'access_point',
                        'is_active' => true,
                    ]
                );
            }
        }

        foreach ($postNames as $name) {
            SupervisorPost::query()->firstOrCreate(
                ['installation_id' => $site->id, 'name' => $name],
                [
                    'client_id' => $client->id,
                    'modality' => 12,
                    'is_active' => true,
                ]
            );
        }
    }

    private function backfillOperationalData(int $clientId): void
    {
        $tables = [
            Location::class,
            Building::class,
            HousingUnit::class,
            Resident::class,
            Visitor::class,
            Vehicle::class,
            AccessLog::class,
            PreAuthorization::class,
            Correspondence::class,
            GuardLog::class,
        ];

        foreach ($tables as $modelClass) {
            DB::table((new $modelClass)->getTable())
                ->whereNull('client_id')
                ->update(['client_id' => $clientId]);
        }
    }

    private function seedPilotVigilante(int $companyId): void
    {
        $title = CompanyJobTitle::query()->firstOrCreate(
            ['security_company_id' => $companyId, 'name' => 'Vigilante'],
            ['is_active' => true, 'sort_order' => 20],
        );
        $type = CompanyCollaboratorType::query()->firstOrCreate(
            ['security_company_id' => $companyId, 'name' => 'OPERATIVO'],
            ['is_active' => true, 'sort_order' => 10],
        );

        Employee::query()->firstOrCreate(
            [
                'security_company_id' => $companyId,
                'document_number' => '1144001122',
            ],
            [
                'job_title_id' => $title->id,
                'collaborator_type_id' => $type->id,
                'document_type' => 'CC',
                'last_name_paternal' => 'Rojas',
                'last_name_maternal' => 'Castaño',
                'first_names' => 'Carlos',
                'sex' => Sex::Male,
                'birth_date' => '1988-03-15',
                'email' => 'vigilante.campo@sj-seguridad.test',
                'nationality' => 'COLOMBIANA',
                'blood_group' => BloodGroup::OPositive,
                'is_active' => true,
            ],
        );
    }

    private function ensurePilotStructureTypes(int $companyId): void
    {
        $types = [
            ['code' => 'general_area', 'name' => 'Conjunto / Zona', 'is_unit' => false, 'sort_order' => 10],
            ['code' => 'ph', 'name' => 'Propiedad horizontal', 'is_unit' => false, 'sort_order' => 20],
            ['code' => 'block', 'name' => 'Torre / Bloque', 'is_unit' => false, 'sort_order' => 40],
            ['code' => 'apartment', 'name' => 'Apartamento', 'is_unit' => true, 'sort_order' => 50],
        ];

        foreach ($types as $type) {
            StructureType::query()->firstOrCreate(
                [
                    'security_company_id' => $companyId,
                    'code' => $type['code'],
                ],
                [
                    'name' => $type['name'],
                    'is_unit' => $type['is_unit'],
                    'is_active' => true,
                    'sort_order' => $type['sort_order'],
                ],
            );
        }
    }
}
