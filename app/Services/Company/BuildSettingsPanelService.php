<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\SupervisorChecklistKind;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\InstallationProfile;
use App\Models\StructureType;
use App\Models\SupervisorAlarmType;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorControlBookType;
use App\Models\SupervisorDocumentType;
use App\Models\SupervisorPostModality;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorTrainingTopic;
use App\Models\SupervisorUniformItem;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use App\Models\SupervisorZone;
use App\Models\User;
use App\Support\Company\SettingsCatalog;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class BuildSettingsPanelService
{
    public function __construct(
        private readonly ManageSupervisorCompanyCatalogService $catalog,
        private readonly AssignClientZonesService $assignments,
        private readonly EnsureInstallationProfilesService $ensureProfiles,
        private readonly SeedSupervisorIntakeDefaultsService $intakeDefaults,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     group: string,
     *     label: string,
     *     blurb: string,
     *     view: string,
     *     data: array<string, mixed>
     * }
     */
    public function execute(string $key, int $companyId, User $user): array
    {
        $found = SettingsCatalog::find($key);
        if ($found === null) {
            throw new InvalidArgumentException("Unknown settings catalog [{$key}].");
        }

        $canManage = $user->can('company.settings.manage') || $user->hasRole('super-admin');

        return [
            'key' => $key,
            'group' => $found['group'],
            'label' => $found['item']['label'],
            'blurb' => $found['item']['blurb'],
            'view' => $this->viewFor($key),
            'data' => array_merge(
                [
                    'catalogKey' => $key,
                    'catalogGroup' => $found['group'],
                    'catalogLabel' => $found['item']['label'],
                    'catalogBlurb' => $found['item']['blurb'],
                    'canManage' => $canManage,
                    'hubToken' => $found['group'].':'.$key,
                ],
                $this->payload($key, $companyId, $canManage),
            ),
        ];
    }

    private function viewFor(string $key): string
    {
        return match ($key) {
            'perfiles' => 'modules.company.settings.panels.profiles',
            'zonas' => 'modules.company.settings.panels.zones',
            'preop' => 'modules.company.settings.panels.preop',
            'modalidades' => 'modules.company.settings.panels.modalities',
            'turnos' => 'modules.company.settings.panels.shifts',
            default => 'modules.company.settings.panels.named-catalog',
        };
    }

    /** @return array<string, mixed> */
    private function payload(string $key, int $companyId, bool $canManage): array
    {
        return match ($key) {
            'cargos' => $this->named(
                CompanyJobTitle::query()->where('security_company_id', $companyId)->withCount('employees')->orderBy('sort_order')->orderBy('name')->get(),
                'company.job-titles',
                'employees_count',
                'empleado',
                'empleados',
            ),
            'tipos' => $this->named(
                CompanyCollaboratorType::query()->where('security_company_id', $companyId)->withCount('employees')->orderBy('sort_order')->orderBy('name')->get(),
                'company.collaborator-types',
                'employees_count',
                'empleado',
                'empleados',
                param: 'collaboratorType',
            ),
            'estructuras' => $this->named(
                StructureType::query()->where('security_company_id', $companyId)->withCount(['clients', 'structures'])->orderBy('sort_order')->orderBy('name')->get(),
                'company.structure-types',
                'clients_count',
                'cliente',
                'clientes',
                param: 'structureType',
            ),
            'presentacion' => $this->supervisionNamed($companyId, SupervisorUniformItem::class, 'company.supervision-uniform-items', 'uniformItem'),
            'temas' => $this->trainingTopics($companyId),
            'documentos' => $this->supervisionNamed($companyId, SupervisorDocumentType::class, 'company.supervision-document-types', 'documentType'),
            'libros' => $this->supervisionNamed($companyId, SupervisorControlBookType::class, 'company.supervision-control-book-types', 'controlBookType'),
            'armas' => $this->supervisionNamed($companyId, SupervisorWeaponType::class, 'company.supervision-weapon-types', 'weaponType'),
            'marcas' => $this->supervisionNamed($companyId, SupervisorWeaponBrand::class, 'company.supervision-weapon-brands', 'weaponBrand'),
            'riesgos' => $this->supervisionNamed($companyId, SupervisorRiskType::class, 'company.supervision-risk-types', 'riskType'),
            'alarmas' => $this->supervisionNamed($companyId, SupervisorAlarmType::class, 'company.supervision-alarm-types', 'alarmType'),
            'apoyos' => $this->supervisionNamed($companyId, SupervisorSupportType::class, 'company.supervision-support-types', 'supportType'),
            'perfiles' => $this->profiles($companyId),
            'zonas' => $this->zones($companyId, $canManage),
            'turnos' => $this->shifts($companyId),
            'modalidades' => $this->modalities($companyId),
            'preop' => $this->preop($companyId),
            default => throw new InvalidArgumentException("Unhandled settings catalog [{$key}]."),
        };
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function named(
        Collection $rows,
        string $routePrefix,
        string $countField,
        string $singular,
        string $plural,
        string $param = 'jobTitle',
    ): array {
        return [
            'rows' => $rows,
            'storeRoute' => route($routePrefix.'.store'),
            'routePrefix' => $routePrefix,
            'routeParam' => $param,
            'countField' => $countField,
            'countSingular' => $singular,
            'countPlural' => $plural,
            'createLabel' => 'Crear',
            'placeholder' => 'Nombre',
        ];
    }

    /**
     * @param  class-string  $model
     * @return array<string, mixed>
     */
    private function supervisionNamed(int $companyId, string $model, string $routePrefix, string $param): array
    {
        $this->catalog->ensureDefaults($companyId);
        $this->intakeDefaults->execute($companyId);

        $rows = $model::query()
            ->where('security_company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'rows' => $rows,
            'storeRoute' => route($routePrefix.'.store'),
            'routePrefix' => $routePrefix,
            'routeParam' => $param,
            'countField' => null,
            'countSingular' => '',
            'countPlural' => '',
            'createLabel' => 'Crear',
            'placeholder' => 'Nombre',
        ];
    }

    /** @return array<string, mixed> */
    private function trainingTopics(int $companyId): array
    {
        $this->catalog->ensureDefaults($companyId);
        $this->intakeDefaults->execute($companyId);

        return $this->named(
            SupervisorTrainingTopic::query()
                ->where('security_company_id', $companyId)
                ->withCount('trainings')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'company.supervision-training-topics',
            'trainings_count',
            'capacitación',
            'capacitaciones',
            param: 'trainingTopic',
        );
    }

    /** @return array<string, mixed> */
    private function profiles(int $companyId): array
    {
        $this->ensureProfiles->execute($companyId);

        return [
            'profiles' => InstallationProfile::query()
                ->where('security_company_id', $companyId)
                ->withCount('installations')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function zones(int $companyId, bool $canManage): array
    {
        $this->catalog->ensureDefaults($companyId);
        $zones = SupervisorZone::query()
            ->where('security_company_id', $companyId)
            ->withCount(['clients', 'sharedClients'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'zones' => $zones,
            'clientRows' => $this->assignments->rows($companyId),
            'unassigned' => $this->assignments->unassignedCount($companyId),
            'canManage' => $canManage,
            'assignerConfig' => [
                'zones' => $zones->map(fn ($zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'active' => (bool) $zone->is_active,
                ])->values()->all(),
                'rows' => $this->assignments->rows($companyId),
                'unassigned' => $this->assignments->unassignedCount($companyId),
                'assignUrl' => route('company.supervision-zones.assign'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function shifts(int $companyId): array
    {
        $this->catalog->ensureDefaults($companyId);
        $this->intakeDefaults->execute($companyId);

        return [
            'templates' => SupervisorShiftTemplate::query()
                ->where('security_company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function modalities(int $companyId): array
    {
        $this->catalog->ensureDefaults($companyId);
        $this->intakeDefaults->execute($companyId);

        return [
            'modalities' => SupervisorPostModality::query()
                ->where('security_company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('hours')
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function preop(int $companyId): array
    {
        $this->catalog->ensureDefaults($companyId);
        $this->intakeDefaults->execute($companyId);

        return [
            'ppe' => SupervisorChecklistItem::query()
                ->where('security_company_id', $companyId)
                ->where('kind', SupervisorChecklistKind::Ppe)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'vehicle' => SupervisorChecklistItem::query()
                ->where('security_company_id', $companyId)
                ->where('kind', SupervisorChecklistKind::Vehicle)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }
}
