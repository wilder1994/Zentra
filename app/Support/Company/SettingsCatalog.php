<?php

declare(strict_types=1);

namespace App\Support\Company;

final class SettingsCatalog
{
    /**
     * @return array<string, array{label: string, blurb: string, items: array<string, array{label: string, blurb: string, route: string}>}>
     */
    public static function groups(): array
    {
        return [
            'empresa' => [
                'label' => 'Empresa',
                'blurb' => 'Cargos, tipos de colaborador y catálogos de formación.',
                'items' => [
                    'cargos' => [
                        'label' => 'Cargos',
                        'blurb' => 'Nombres de puesto del personal.',
                        'route' => 'company.job-titles.index',
                    ],
                    'tipos' => [
                        'label' => 'Tipos de colaborador',
                        'blurb' => 'Clasificación operativa del empleado.',
                        'route' => 'company.collaborator-types.index',
                    ],
                    'presentacion' => [
                        'label' => 'Presentación personal',
                        'blurb' => 'Ítems de uniforme en revistas.',
                        'route' => 'company.supervision-uniform-items.index',
                    ],
                    'temas' => [
                        'label' => 'Temas de capacitación',
                        'blurb' => 'Temas para capacitaciones de campo.',
                        'route' => 'company.supervision-training-topics.index',
                    ],
                ],
            ],
            'clientes' => [
                'label' => 'Clientes / sitio',
                'blurb' => 'Clasificación del cliente y perfiles de cada instalación.',
                'items' => [
                    'estructuras' => [
                        'label' => 'Clasificación de cliente',
                        'blurb' => 'Tipo de estructura del censo.',
                        'route' => 'company.structure-types.index',
                    ],
                    'perfiles' => [
                        'label' => 'Perfiles de sitio',
                        'blurb' => 'Reglas del sitio: DANE y Observatorio.',
                        'route' => 'company.installation-profiles.index',
                    ],
                ],
            ],
            'campo' => [
                'label' => 'App de campo',
                'blurb' => 'Catálogos que usa el supervisor en la PWA.',
                'items' => [
                    'zonas' => [
                        'label' => 'Zonas',
                        'blurb' => 'Rutas y asignación de clientes.',
                        'route' => 'company.supervision-zones.index',
                    ],
                    'turnos' => [
                        'label' => 'Turnos',
                        'blurb' => 'Plantillas Día / Noche.',
                        'route' => 'company.supervision-shifts.index',
                    ],
                    'modalidades' => [
                        'label' => 'Modalidades',
                        'blurb' => 'Horas de puesto (8, 12, 24…).',
                        'route' => 'company.supervision-post-modalities.index',
                    ],
                    'preop' => [
                        'label' => 'Preoperacional',
                        'blurb' => 'Checklists EPP y vehículo.',
                        'route' => 'company.supervision-preop.index',
                    ],
                    'documentos' => [
                        'label' => 'Documentos',
                        'blurb' => 'Tipos de documento en puesto.',
                        'route' => 'company.supervision-document-types.index',
                    ],
                    'libros' => [
                        'label' => 'Libros',
                        'blurb' => 'Tipos de libro de control.',
                        'route' => 'company.supervision-control-book-types.index',
                    ],
                    'armas' => [
                        'label' => 'Tipos de arma',
                        'blurb' => 'Catálogo de armas.',
                        'route' => 'company.supervision-weapon-types.index',
                    ],
                    'marcas' => [
                        'label' => 'Marcas',
                        'blurb' => 'Marcas de arma.',
                        'route' => 'company.supervision-weapon-brands.index',
                    ],
                    'riesgos' => [
                        'label' => 'Riesgos',
                        'blurb' => 'Tipos de riesgo en revista.',
                        'route' => 'company.supervision-risk-types.index',
                    ],
                    'alarmas' => [
                        'label' => 'Alarmas',
                        'blurb' => 'Tipos de alarma.',
                        'route' => 'company.supervision-alarm-types.index',
                    ],
                    'apoyos' => [
                        'label' => 'Apoyos',
                        'blurb' => 'Tipos de apoyo.',
                        'route' => 'company.supervision-support-types.index',
                    ],
                ],
            ],
        ];
    }

    /** @return array{group: string, item: array{label: string, blurb: string, route: string}}|null */
    public static function find(string $catalogKey): ?array
    {
        foreach (self::groups() as $groupKey => $group) {
            if (isset($group['items'][$catalogKey])) {
                return [
                    'group' => $groupKey,
                    'item' => $group['items'][$catalogKey],
                ];
            }
        }

        return null;
    }

    public static function groupOf(string $catalogKey): ?string
    {
        return self::find($catalogKey)['group'] ?? null;
    }

    public static function hubRedirect(string $catalogKey): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('company.settings.hub', [
            'group' => self::groupOf($catalogKey),
            'catalog' => $catalogKey,
        ]);
    }
}
