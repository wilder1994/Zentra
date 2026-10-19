@php
    $activeTab = $companyNavActive ?? 'cargos';
@endphp

<x-slot:headerTabs>
    <a href="{{ route('company.job-titles.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'cargos'])>Cargos</a>
    <a href="{{ route('company.collaborator-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'tipos'])>Tipos</a>
    <a href="{{ route('company.structure-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'estructuras'])>Estructuras</a>
    <a href="{{ route('company.supervision-zones.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'zonas'])>Zonas</a>
    <a href="{{ route('company.supervision-shifts.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'turnos'])>Turnos</a>
    <a href="{{ route('company.supervision-post-modalities.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'modalidades'])>Modalidades</a>
    <a href="{{ route('company.supervision-preop.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'preop'])>Preoperacional</a>
    <a href="{{ route('company.supervision-document-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'documentos'])>Documentos</a>
    <a href="{{ route('company.supervision-control-book-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'libros'])>Libros</a>
    <a href="{{ route('company.supervision-weapon-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'armas'])>Tipos de arma</a>
    <a href="{{ route('company.supervision-weapon-brands.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'marcas'])>Marcas</a>
    <a href="{{ route('company.supervision-risk-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'riesgos'])>Riesgos</a>
    <a href="{{ route('company.supervision-alarm-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'alarmas'])>Alarmas</a>
    <a href="{{ route('company.supervision-support-types.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'apoyos'])>Apoyos</a>
    <a href="{{ route('company.supervision-uniform-items.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'presentacion'])>Presentación personal</a>
    <a href="{{ route('company.supervision-training-topics.index') }}" @class(['admin-header-tab', 'is-active' => $activeTab === 'temas'])>Temas de capacitación</a>
</x-slot:headerTabs>
