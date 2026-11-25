<x-client-layout title="Riesgos del Observatorio">
    <x-slot:headerTabs>
        @include('modules.observatory.partials.nav-tabs', [
            'indexRoute' => 'client.observatory.events.index',
            'typesRoute' => 'client.observatory.types.index',
            'vista' => 'tipos',
        ])
    </x-slot:headerTabs>

    <p class="mb-3 text-xs text-slate-500">Catálogo de riesgo endógeno de la empresa. Un cambio se ve en todos los clientes con Observatorio.</p>
    @include('modules.observatory.partials.types-catalog', [
        'types' => $types,
        'parameters' => $parameters,
        'nextColor' => $nextColor,
        'canEdit' => $canEdit,
        'storeRoute' => 'client.observatory.types.store',
        'updateRoute' => 'client.observatory.types.update',
        'destroyRoute' => 'client.observatory.types.destroy',
        'accent' => 'teal',
    ])
</x-client-layout>
