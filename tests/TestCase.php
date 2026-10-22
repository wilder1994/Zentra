<?php

namespace Tests;

use App\Enums\BloodGroup;
use App\Enums\CompanyPackageSku;
use App\Enums\SupervisorChecklistKind;
use App\Models\Client;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\Employee;
use App\Models\Location;
use App\Models\SecurityCompany;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorPost;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorZone;
use App\Models\User;
use App\Services\Access\PorteriaDoorService;
use App\Services\Company\GrantEmployeeAccessService;
use App\Services\Company\SeedSupervisorIntakeDefaultsService;
use App\Services\User\EnsureCompanyAdminCatalog;
use App\Support\Legal\CorpusAcceptanceRules;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PilotDemoSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected const COMPANY_SUPERVISOR_PASSWORD = 'Super123!';

    protected const TEST_DEVICE_ID = 'test-device-0001';

    /** Seed mínimo + datos piloto (empresa, conjuntos, censo, usuarios demo). */
    protected function seedWithPilot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(PilotDemoSeeder::class);
    }

    protected function grantCompanyAdminCatalog(User $user): void
    {
        app(EnsureCompanyAdminCatalog::class)->execute($user);
        $user->refresh();
        $user->load('roles', 'permissions');
    }

    protected function pilotCompany(): SecurityCompany
    {
        return SecurityCompany::query()->where('tax_id', '900123456-1')->firstOrFail();
    }

    /** Deja todos los productos con la misma cantidad de instalaciones (bolsa = $seats). */
    protected function capInstallations(SecurityCompany $company, int $seats): void
    {
        $company->update([
            'max_clients' => $seats,
            'max_supervision_clients' => $company->hasSupervisionPackage() ? $seats : $company->max_supervision_clients,
            'indexing_seats' => $company->indexing_seats > 0 ? $seats : 0,
            'observatory_seats' => $company->observatory_seats > 0 ? $seats : 0,
        ]);
    }

    protected function pilotCompanyId(): int
    {
        return (int) $this->pilotCompany()->id;
    }

    protected function companySupervisor(
        string $firstNames = 'Luis',
        string $lastName = 'Rojas',
        string $documentNumber = '1199004400',
        string $username = 'luis.rojas.4400',
    ): User {
        $existing = User::query()->where('username', $username)->first();
        if ($existing !== null) {
            return $existing;
        }

        $companyId = $this->pilotCompanyId();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $title = CompanyJobTitle::query()->firstOrCreate(
            ['security_company_id' => $companyId, 'name' => 'Supervisor de vigilancia'],
            ['is_active' => true, 'sort_order' => 10],
        );
        $type = CompanyCollaboratorType::query()->firstOrCreate(
            ['security_company_id' => $companyId, 'name' => 'OPERATIVO'],
            ['is_active' => true, 'sort_order' => 10],
        );
        $employee = Employee::query()->create([
            'security_company_id' => $companyId,
            'job_title_id' => $title->id,
            'collaborator_type_id' => $type->id,
            'document_type' => 'CC',
            'document_number' => $documentNumber,
            'last_name_paternal' => $lastName,
            'last_name_maternal' => '',
            'first_names' => $firstNames,
            'sex' => 'hombre',
            'birth_date' => '1988-03-15',
            'email' => str_replace('.', '', $username).'@sj-seguridad.test',
            'nationality' => 'COLOMBIANA',
            'blood_group' => BloodGroup::OPositive,
            'is_active' => true,
        ]);

        return app(GrantEmployeeAccessService::class)->execute(
            $employee,
            $admin,
            'supervisor',
            self::COMPANY_SUPERVISOR_PASSWORD,
            [],
            $username,
            $title->name,
        );
    }

    protected function loginCompanySupervisor(): string
    {
        $user = $this->companySupervisor();
        $login = $this->postJson('/api/supervision/login', [
            'login' => $user->username,
            'password' => self::COMPANY_SUPERVISOR_PASSWORD,
            'device_id' => self::TEST_DEVICE_ID,
        ]);
        $login->assertOk();

        return (string) $login->json('token');
    }

    /** @return array<string, array<string, string>> */
    protected function acceptAllCorpusDocs(?CompanyPackageSku $sku = null): array
    {
        $docs = [];
        foreach (CorpusAcceptanceRules::requiredTypeValues($sku) as $type) {
            $docs[$type] = '1';
        }

        return ['accept_docs' => $docs];
    }

    /** @return array<string, mixed> */
    protected function supervisorShiftOpenPayload(array $overrides = []): array
    {
        $companyId = $this->pilotCompanyId();

        if ($companyId > 0) {
            app(SeedSupervisorIntakeDefaultsService::class)->execute($companyId);
        }

        $zone = SupervisorZone::query()
            ->where('security_company_id', $companyId)
            ->orderBy('sort_order')
            ->first();
        $templates = SupervisorShiftTemplate::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $deadlines = app(\App\Services\Company\ResolveSupervisorShiftDeadlineService::class);
        $template = $templates->first(fn (SupervisorShiftTemplate $row) => $row->starts_at && $row->ends_at
            && $deadlines->window(\Carbon\CarbonImmutable::now(), (string) $row->starts_at, (string) $row->ends_at) !== null)
            ?? $templates->first();

        $ppe = [];
        foreach (array_keys(SupervisorChecklistItem::keyedLabels($companyId, SupervisorChecklistKind::Ppe)) as $key) {
            $ppe[$key] = true;
        }
        $vehicleCheck = [];
        foreach (array_keys(SupervisorChecklistItem::keyedLabels($companyId, SupervisorChecklistKind::Vehicle)) as $key) {
            $vehicleCheck[$key] = true;
        }

        return array_merge([
            'shift_template_id' => $template?->id,
            'zone_id' => $zone?->id,
            'km_start' => 1000,
            'vehicle' => [
                'plate' => 'ABC12D',
                'brand' => 'Yamaha',
                'line' => 'FZ',
                'model' => '2022',
            ],
            'ppe_checklist' => $ppe,
            'vehicle_checklist' => $vehicleCheck,
            'odometer_photo' => UploadedFile::fake()->image('odometer.jpg'),
            'selfie_photo' => UploadedFile::fake()->image('selfie.jpg'),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    protected function supervisorShiftClosePayload(array $overrides = []): array
    {
        return array_merge([
            'km_end' => 1012,
            'odometer_photo' => UploadedFile::fake()->image('odometer-end.jpg'),
            'selfie_photo' => UploadedFile::fake()->image('selfie-end.jpg'),
        ], $overrides);
    }

    protected function supervisorVigilante(): Employee
    {
        return Employee::query()->where('document_number', '1144001122')->firstOrFail();
    }

    protected function supervisionPostFor(Client $client): SupervisorPost
    {
        $post = SupervisorPost::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($post, 'El cliente piloto debe tener puestos de Supervisión.');

        return $post;
    }

    protected function assignEmployeeToClientPost(Employee $employee, Client $client): SupervisorPost
    {
        $post = $this->supervisionPostFor($client);
        $employee->supervisorPosts()->syncWithoutDetaching([$post->id]);

        return $post;
    }

    /** @return array<string, mixed> */
    protected function supervisorReviewPayload(Client $client, array $overrides = []): array
    {
        $post = $this->supervisionPostFor($client);
        $post->loadMissing('installation');
        $lat = $post->latitude !== null
            ? (float) $post->latitude
            : (float) ($post->installation?->latitude ?? 3.4516);
        $lng = $post->longitude !== null
            ? (float) $post->longitude
            : (float) ($post->installation?->longitude ?? -76.5320);

        return array_merge([
            'client_id' => $client->id,
            'supervisor_post_id' => $post->id,
            'employee_id' => $this->supervisorVigilante()->id,
            'notes' => 'Revista de puesto',
            'has_novelty' => 0,
            'latitude' => $lat,
            'longitude' => $lng,
            'guard_photo' => UploadedFile::fake()->image('guard.jpg'),
        ], $overrides);
    }

    /** @return array<string, int> */
    protected function porteriaSession(Client $client, ?int $doorId = null): array
    {
        $doorId ??= (int) Location::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        return [
            config('tenancy.session.active_client_key') => $client->id,
            PorteriaDoorService::SESSION_KEY => $doorId,
        ];
    }

    /** Payload para abrir turno de guarda real (plantilla del catálogo de empresa). */
    protected function guardShiftOpenPayload(Location|int $door, array $overrides = []): array
    {
        $doorId = $door instanceof Location ? (int) $door->id : $door;
        $location = $door instanceof Location
            ? $door
            : Location::query()->withoutGlobalScopes()->findOrFail($doorId);
        $companyId = (int) (Client::query()->whereKey($location->client_id)->value('security_company_id') ?? 0);
        if ($companyId > 0) {
            app(SeedSupervisorIntakeDefaultsService::class)->execute($companyId);
        }

        $templateId = (int) (SupervisorShiftTemplate::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id') ?? 0);

        return array_merge([
            'location_id' => $doorId,
            'shift_template_id' => $templateId,
        ], $overrides);
    }
}
