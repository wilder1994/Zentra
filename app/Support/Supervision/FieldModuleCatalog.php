<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use App\Enums\SupervisorAlarmKind;
use App\Enums\SupervisorAlarmResult;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorRiskImpact;
use App\Enums\SupervisorRiskLikelihood;
use App\Enums\SupervisorTrainingKind;
use App\Enums\SupervisorTrainingModality;
use App\Enums\SupervisorWeaponPermitKind;
use App\Models\SupervisorAlarmType;
use App\Models\SupervisorControlBookType;
use App\Models\SupervisorDocumentType;
use App\Models\SupervisorRiskType;
use App\Models\SupervisorSupportType;
use App\Models\SupervisorTrainingTopic;
use App\Models\SupervisorUniformItem;
use App\Models\SupervisorWeaponBrand;
use App\Models\SupervisorWeaponType;
use Illuminate\Database\Eloquent\Model;

final class FieldModuleCatalog
{
    /**
     * Contrato único app ↔ API. La PWA renderiza estos campos; no hardcodea reglas de negocio.
     *
     * @return list<array<string, mixed>>
     */
    public function modules(?int $companyId = null, int $appBuild = 0): array
    {
        return $this->uniformFirst([
            [
                'key' => 'reviews',
                'label' => 'Revista',
                'hint' => 'Visita de supervisión: cliente, puesto, vigilante y evidencia. No es la minuta de portería.',
                'capture' => 'reviews',
                'requires_client' => true,
                'hangs_off_review' => false,
                'fields' => [
                    [
                        'name' => 'notes',
                        'label' => 'Notas de la visita',
                        'type' => 'textarea',
                        'required' => false,
                        'max' => 2000,
                    ],
                ],
            ],
            $this->fieldModule(SupervisorFieldModule::Inventory, [
                [
                    'name' => 'items',
                    'label' => 'Elementos del puesto',
                    'type' => 'repeatable',
                    'required' => true,
                    'min' => 1,
                    'add_label' => 'Agregar',
                    'item_label' => 'Elemento',
                    'item_fields' => [
                        [
                            'name' => 'type',
                            'label' => 'Tipo de elemento',
                            'type' => 'text',
                            'required' => true,
                            'max' => 120,
                        ],
                        [
                            'name' => 'status',
                            'label' => 'Estado',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                ['value' => 'good', 'label' => 'Bueno'],
                                ['value' => 'regular', 'label' => 'Regular'],
                                ['value' => 'bad', 'label' => 'Malo'],
                            ],
                        ],
                        [
                            'name' => 'notes',
                            'label' => 'Observación',
                            'type' => 'textarea',
                            'required' => false,
                            'max' => 500,
                        ],
                    ],
                ],
            ]),
            $this->fieldModule(SupervisorFieldModule::ControlBooks, [
                [
                    'name' => 'items',
                    'label' => 'Libros',
                    'type' => 'repeatable',
                    'required' => true,
                    'min' => 1,
                    'add_label' => 'Agregar',
                    'item_label' => 'Libro',
                    'item_fields' => [
                        [
                            'name' => 'control_book_type_id',
                            'label' => 'Tipo de libro',
                            'type' => 'select',
                            'required' => true,
                            'options' => $this->controlBookTypeOptions($companyId),
                        ],
                        [
                            'name' => 'novelty',
                            'label' => 'Novedad',
                            'type' => 'radio',
                            'required' => true,
                            'options' => [
                                ['value' => 'no', 'label' => 'Sin novedad'],
                                ['value' => 'yes', 'label' => 'Con novedad'],
                            ],
                        ],
                        [
                            'name' => 'notes',
                            'label' => 'Observación',
                            'type' => 'textarea',
                            'required' => false,
                            'max' => 500,
                        ],
                    ],
                ],
            ]),
            $this->fieldModule(SupervisorFieldModule::Folders, [
                [
                    'name' => 'status',
                    'label' => 'Carpeta del puesto',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'complete', 'label' => 'Completa'],
                        ['value' => 'missing', 'label' => 'Con faltantes'],
                    ],
                ],
                [
                    'name' => 'missing_items',
                    'label' => 'Qué falta (si aplica)',
                    'type' => 'text',
                    'required' => false,
                    'max' => 500,
                ],
            ]),
            $this->fieldModule(SupervisorFieldModule::Weapons, [
                [
                    'name' => 'weapon_type_id',
                    'label' => 'Tipo de arma',
                    'type' => 'select',
                    'required' => true,
                    'empty_label' => 'Sin tipos. Agréguelos en Ajustes',
                    'options' => $this->namedCatalogOptions(SupervisorWeaponType::class, $companyId),
                ],
                [
                    'name' => 'weapon_brand_id',
                    'label' => 'Marca',
                    'type' => 'select',
                    'required' => true,
                    'empty_label' => 'Sin marcas. Agréguelas en Ajustes',
                    'options' => $this->namedCatalogOptions(SupervisorWeaponBrand::class, $companyId),
                ],
                [
                    'name' => 'serial',
                    'label' => 'Serial observado',
                    'type' => 'text',
                    'required' => true,
                    'max' => 80,
                ],
                [
                    'name' => 'caliber',
                    'label' => 'Calibre',
                    'type' => 'text',
                    'required' => true,
                    'max' => 40,
                ],
                [
                    'name' => 'permit_kind',
                    'label' => 'Tipo de permiso',
                    'type' => 'select',
                    'required' => true,
                    'options' => collect(SupervisorWeaponPermitKind::cases())
                        ->map(fn (SupervisorWeaponPermitKind $kind) => [
                            'value' => $kind->value,
                            'label' => $kind->label(),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'name' => 'permit_number',
                    'label' => 'Número de permiso',
                    'type' => 'text',
                    'required' => true,
                    'max' => 80,
                ],
                [
                    'name' => 'permit_expires_at',
                    'label' => 'Vencimiento del permiso',
                    'type' => 'date',
                    'required' => true,
                ],
                [
                    'type' => 'row',
                    'fields' => [
                        [
                            'name' => 'ammo_quantity',
                            'label' => 'Cantidad de munición',
                            'type' => 'number',
                            'required' => true,
                            'min' => 0,
                        ],
                        [
                            'name' => 'ammo_caliber',
                            'label' => 'Calibre de munición',
                            'type' => 'text',
                            'required' => true,
                            'max' => 40,
                        ],
                    ],
                ],
                [
                    'name' => 'novelty',
                    'label' => 'Novedad',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'no', 'label' => 'Sin novedad'],
                        ['value' => 'yes', 'label' => 'Con novedad'],
                    ],
                ],
                [
                    'name' => 'notes',
                    'label' => 'Observación (si hay novedad)',
                    'type' => 'textarea',
                    'required' => false,
                    'max' => 500,
                ],
                [
                    'name' => 'cleaned',
                    'label' => '¿Se realizó aseo?',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'no', 'label' => 'Solo revista'],
                        ['value' => 'yes', 'label' => 'Revista y aseo'],
                    ],
                ],
                [
                    'name' => 'photos',
                    'label' => 'Fotos de identificación',
                    'type' => 'photo_grid',
                    'required' => true,
                    'slots' => WeaponInspectionPhotos::identificationSlots(),
                ],
                [
                    'name' => 'cleaning_photos',
                    'label' => 'Foto de aseo',
                    'type' => 'photo_grid',
                    'required' => true,
                    'slots' => WeaponInspectionPhotos::cleaningSlots(),
                    'show_when' => ['cleaned' => 'yes'],
                ],
            ]),
            $this->fieldModule(SupervisorFieldModule::Recommendations, [
                [
                    'name' => 'items',
                    'label' => 'Recomendaciones',
                    'type' => 'repeatable',
                    'required' => true,
                    'min' => 1,
                    'max' => 3,
                    'add_label' => 'Agregar',
                    'item_label' => 'Recomendación',
                    'item_fields' => [
                        [
                            'name' => 'risk_type_id',
                            'label' => 'Tipo de riesgo',
                            'type' => 'select',
                            'required' => true,
                            'options' => $this->riskTypeOptions($companyId),
                        ],
                        [
                            'name' => 'risk',
                            'label' => 'Riesgo',
                            'type' => 'textarea',
                            'required' => true,
                            'max' => 2000,
                        ],
                        [
                            'name' => 'likelihood',
                            'label' => 'Probabilidad',
                            'type' => 'select',
                            'required' => true,
                            'options' => collect(SupervisorRiskLikelihood::cases())
                                ->map(fn (SupervisorRiskLikelihood $likelihood) => [
                                    'value' => $likelihood->value,
                                    'label' => $likelihood->label(),
                                ])
                                ->values()
                                ->all(),
                        ],
                        [
                            'name' => 'impact',
                            'label' => 'Impacto',
                            'type' => 'select',
                            'required' => true,
                            'options' => collect(SupervisorRiskImpact::cases())
                                ->map(fn (SupervisorRiskImpact $impact) => [
                                    'value' => $impact->value,
                                    'label' => $impact->label(),
                                ])
                                ->values()
                                ->all(),
                        ],
                        [
                            'name' => 'risk_level',
                            'label' => 'Nivel de riesgo',
                            'type' => 'computed',
                            'from' => ['likelihood', 'impact'],
                        ],
                        [
                            'name' => 'consequence',
                            'label' => 'Consecuencia',
                            'type' => 'textarea',
                            'required' => true,
                            'max' => 2000,
                        ],
                        [
                            'name' => 'treatment',
                            'label' => 'Recomendación',
                            'type' => 'textarea',
                            'required' => true,
                            'max' => 2000,
                        ],
                        [
                            'name' => 'photos',
                            'label' => 'Evidencia del riesgo',
                            'type' => 'photo_grid',
                            'required' => true,
                            'slots' => RecommendationEvidencePhotos::slots(),
                        ],
                    ],
                ],
            ]),
            ...$this->reviewExtras($companyId, $appBuild),
            $this->fieldModule(SupervisorFieldModule::Alarms, [
                [
                    'name' => 'alarm_type_id',
                    'label' => 'Tipo de alarma',
                    'type' => 'select',
                    'required' => true,
                    'empty_label' => 'Sin tipos. Agréguelos en Ajustes',
                    'options' => $this->namedCatalogOptions(SupervisorAlarmType::class, $companyId),
                ],
                [
                    'name' => 'kind',
                    'label' => 'Modalidad',
                    'type' => 'radio',
                    'required' => true,
                    'options' => collect(SupervisorAlarmKind::cases())
                        ->map(fn (SupervisorAlarmKind $kind) => [
                            'value' => $kind->value,
                            'label' => $kind->label(),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'name' => 'result',
                    'label' => 'Resultado',
                    'type' => 'radio',
                    'required' => true,
                    'options' => $this->alarmResultOptions(SupervisorAlarmKind::Test),
                    'options_when' => [
                        'field' => 'kind',
                        'map' => [
                            SupervisorAlarmKind::Test->value => $this->alarmResultOptions(SupervisorAlarmKind::Test),
                            SupervisorAlarmKind::Response->value => $this->alarmResultOptions(SupervisorAlarmKind::Response),
                        ],
                    ],
                ],
            ]),
            $this->fieldModule(SupervisorFieldModule::Supports, [
                [
                    'name' => 'support_type_id',
                    'label' => 'Tipo de apoyo',
                    'type' => 'select',
                    'required' => true,
                    'empty_label' => 'Sin tipos. Agréguelos en Ajustes',
                    'options' => $this->namedCatalogOptions(SupervisorSupportType::class, $companyId),
                ],
                [
                    'name' => 'novelty',
                    'label' => '¿El apoyo fue por una novedad?',
                    'type' => 'radio',
                    'required' => true,
                    'options' => [
                        ['value' => 'no', 'label' => 'Sin novedad'],
                        ['value' => 'yes', 'label' => 'Con novedad'],
                    ],
                ],
                [
                    'name' => 'reason',
                    'label' => 'Motivo',
                    'type' => 'textarea',
                    'required' => true,
                    'max' => 500,
                ],
            ]),
            $this->fieldModule(SupervisorFieldModule::Documents, [
                [
                    'name' => 'items',
                    'label' => 'Documentos',
                    'type' => 'repeatable',
                    'required' => true,
                    'min' => 1,
                    'add_label' => 'Agregar',
                    'item_label' => 'Documento',
                    'item_fields' => [
                        [
                            'name' => 'document_type_id',
                            'label' => 'Tipo de documento',
                            'type' => 'select',
                            'required' => true,
                            'options' => $this->documentTypeOptions($companyId),
                        ],
                        [
                            'name' => 'counts',
                            'type' => 'qty_pair',
                            'fields' => [
                                ['name' => 'delivered', 'label' => 'Entregado'],
                                ['name' => 'pending', 'label' => 'Pendiente'],
                            ],
                        ],
                        [
                            'name' => 'notes',
                            'label' => 'Observación',
                            'type' => 'textarea',
                            'required' => false,
                            'max' => 500,
                        ],
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Presentación personal va de primera entre los módulos de la revista (es obligatoria).
     *
     * @param  list<array<string, mixed>>  $modules
     * @return list<array<string, mixed>>
     */
    private function uniformFirst(array $modules): array
    {
        $uniform = array_values(array_filter($modules, fn (array $m) => $m['key'] === SupervisorFieldModule::Uniform->value));
        $rest = array_values(array_filter($modules, fn (array $m) => $m['key'] !== SupervisorFieldModule::Uniform->value));
        if ($uniform === []) {
            return $rest;
        }
        array_splice($rest, 1, 0, $uniform);

        return $rest;
    }

    /**
     * Presentación personal (si la empresa tiene prendas) y Capacitación. Solo para apps que las entienden.
     *
     * @return list<array<string, mixed>>
     */
    private function reviewExtras(?int $companyId, int $appBuild): array
    {
        if ($appBuild < SupervisorFieldModule::Uniform->needsAppBuild()) {
            return [];
        }

        $modules = [];
        $items = $this->namedCatalogOptions(SupervisorUniformItem::class, $companyId);
        if ($items !== []) {
            $modules[] = $this->fieldModule(SupervisorFieldModule::Uniform, [
                [
                    'name' => 'items',
                    'label' => 'Prendas',
                    'type' => 'checklist',
                    'required' => true,
                    'items' => $items,
                    'options' => UniformInspection::options(),
                    'group_label' => 'Novedad',
                    'group' => ['yes', 'no'],
                ],
                [
                    'name' => 'notes',
                    'label' => 'Observación de la novedad',
                    'type' => 'textarea',
                    'required' => true,
                    'max' => 1000,
                    'show_when' => ['novelty' => 'yes'],
                ],
                [
                    'name' => 'photos',
                    'label' => 'Evidencia (1 a 3 fotos)',
                    'type' => 'photo_grid',
                    'required' => true,
                    'min' => 1,
                    'slots' => UniformInspection::photoSlots(),
                    'show_when' => ['novelty' => 'yes'],
                ],
            ]) + ['mandatory' => true, 'single' => true];
        }

        $modules[] = $this->fieldModule(SupervisorFieldModule::Trainings, [
            [
                'name' => 'kind',
                'label' => 'Tipo',
                'type' => 'radio',
                'required' => true,
                'no_default' => true,
                'options' => collect(SupervisorTrainingKind::cases())
                    ->map(fn (SupervisorTrainingKind $kind) => ['value' => $kind->value, 'label' => $kind->label()])
                    ->values()
                    ->all(),
            ],
            [
                'name' => 'modality',
                'label' => 'Modalidad',
                'type' => 'radio',
                'required' => true,
                'no_default' => true,
                'options' => collect(SupervisorTrainingModality::cases())
                    ->map(fn (SupervisorTrainingModality $modality) => ['value' => $modality->value, 'label' => $modality->label()])
                    ->values()
                    ->all(),
            ],
            [
                'name' => 'topic',
                'label' => 'Tema',
                'type' => 'topic',
                'required' => true,
                'options' => $this->namedCatalogOptions(SupervisorTrainingTopic::class, $companyId),
            ],
            [
                'name' => 'reason',
                'label' => 'Motivo / falencia identificada',
                'type' => 'textarea',
                'required' => true,
                'max' => 1000,
                'show_when' => ['kind' => SupervisorTrainingKind::Custom->value],
            ],
            [
                'name' => 'description',
                'label' => 'Descripción de la capacitación',
                'type' => 'textarea',
                'required' => true,
                'max' => 2000,
                'show_when' => ['modality' => SupervisorTrainingModality::Onsite->value],
            ],
            [
                'name' => 'timer',
                'label' => 'Duración',
                'type' => 'timer',
                'required' => true,
                'show_when' => ['modality' => SupervisorTrainingModality::Onsite->value],
            ],
            [
                'name' => 'photos',
                'label' => 'Evidencia (1 a 3 fotos)',
                'type' => 'photo_grid',
                'required' => true,
                'min' => 1,
                'slots' => TrainingEvidence::photoSlots(),
            ],
        ]) + ['single' => true];

        return $modules;
    }

    /**
     * @param  class-string<Model>  $model
     * @return list<array{value: string, label: string}>
     */
    private function namedCatalogOptions(string $model, ?int $companyId): array
    {
        if ($companyId === null || $companyId < 1) {
            return [];
        }

        return $model::query()
            ->where('security_company_id', $companyId)
            ->active()
            ->get()
            ->map(fn (Model $row) => [
                'value' => (string) $row->getKey(),
                'label' => (string) $row->getAttribute('name'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function documentTypeOptions(?int $companyId): array
    {
        return $this->namedCatalogOptions(SupervisorDocumentType::class, $companyId);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function controlBookTypeOptions(?int $companyId): array
    {
        return $this->namedCatalogOptions(SupervisorControlBookType::class, $companyId);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function riskTypeOptions(?int $companyId): array
    {
        return $this->namedCatalogOptions(SupervisorRiskType::class, $companyId);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function alarmResultOptions(SupervisorAlarmKind $kind): array
    {
        return collect(SupervisorAlarmResult::forKind($kind))
            ->map(fn (SupervisorAlarmResult $result) => [
                'value' => $result->value,
                'label' => $result->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function fieldModule(SupervisorFieldModule $module, array $fields): array
    {
        return [
            'key' => $module->value,
            'label' => $module->label(),
            'hint' => $module->hint(),
            'capture' => 'logs',
            'requires_client' => $module->requiresClient(),
            'hangs_off_review' => $module->hangsOffReview(),
            'requires_gps' => $module->requiresGps(),
            'fields' => $fields,
        ];
    }
}
