@php
    $activeTab = $companyNavActive ?? 'cargos';

    $groups = [
        'empresa' => [
            'label' => 'Empresa',
            'items' => [
                'cargos' => ['label' => 'Cargos', 'route' => 'company.job-titles.index'],
                'tipos' => ['label' => 'Tipos de colaborador', 'route' => 'company.collaborator-types.index'],
                'presentacion' => ['label' => 'Presentación personal', 'route' => 'company.supervision-uniform-items.index'],
                'temas' => ['label' => 'Temas de capacitación', 'route' => 'company.supervision-training-topics.index'],
            ],
        ],
        'clientes' => [
            'label' => 'Clientes / sitio',
            'items' => [
                'estructuras' => ['label' => 'Clasificación de cliente', 'route' => 'company.structure-types.index'],
                'perfiles' => ['label' => 'Perfiles de sitio', 'route' => 'company.installation-profiles.index'],
            ],
        ],
        'campo' => [
            'label' => 'App de campo',
            'items' => [
                'zonas' => ['label' => 'Zonas', 'route' => 'company.supervision-zones.index'],
                'turnos' => ['label' => 'Turnos', 'route' => 'company.supervision-shifts.index'],
                'modalidades' => ['label' => 'Modalidades', 'route' => 'company.supervision-post-modalities.index'],
                'preop' => ['label' => 'Preoperacional', 'route' => 'company.supervision-preop.index'],
                'documentos' => ['label' => 'Documentos', 'route' => 'company.supervision-document-types.index'],
                'libros' => ['label' => 'Libros', 'route' => 'company.supervision-control-book-types.index'],
                'armas' => ['label' => 'Tipos de arma', 'route' => 'company.supervision-weapon-types.index'],
                'marcas' => ['label' => 'Marcas', 'route' => 'company.supervision-weapon-brands.index'],
                'riesgos' => ['label' => 'Riesgos', 'route' => 'company.supervision-risk-types.index'],
                'alarmas' => ['label' => 'Alarmas', 'route' => 'company.supervision-alarm-types.index'],
                'apoyos' => ['label' => 'Apoyos', 'route' => 'company.supervision-support-types.index'],
            ],
        ],
    ];

    $activeGroup = 'empresa';
    foreach ($groups as $key => $group) {
        if (array_key_exists($activeTab, $group['items'])) {
            $activeGroup = $key;
            break;
        }
    }
@endphp

<x-slot:headerTabs>
    <div class="w-full space-y-2" x-data="{ group: @js($activeGroup) }">
        <div class="flex flex-wrap items-center gap-1">
            @foreach ($groups as $key => $group)
                <button
                    type="button"
                    @click="group = '{{ $key }}'"
                    class="rounded-md px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase transition-colors"
                    :class="group === '{{ $key }}'
                        ? 'bg-slate-800 text-white'
                        : 'text-slate-500 hover:text-slate-300 hover:bg-slate-900'"
                >{{ $group['label'] }}</button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($groups as $key => $group)
                @foreach ($group['items'] as $tab => $item)
                    <a
                        href="{{ route($item['route']) }}"
                        x-show="group === '{{ $key }}'"
                        x-cloak
                        @class(['admin-header-tab', 'is-active' => $activeTab === $tab])
                    >{{ $item['label'] }}</a>
                @endforeach
            @endforeach
        </div>
    </div>
</x-slot:headerTabs>
