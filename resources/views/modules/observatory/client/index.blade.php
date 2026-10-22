<x-client-layout title="Observatorio">
    <x-slot:headerTabs>
        @include('modules.observatory.partials.nav-tabs', [
            'indexRoute' => 'client.observatory.events.index',
            'typesRoute' => 'client.observatory.types.index',
            'vista' => $filters->vista,
        ])
    </x-slot:headerTabs>

    <x-slot:actions>
        <div class="flex flex-wrap items-end gap-2">
            @include('modules.observatory.partials.header-filters', [
                'action' => route('client.observatory.events.index'),
                'filters' => $filters,
                'filterOptions' => $filterOptions,
                'vista' => $filters->vista,
                'isCompany' => false,
                'publicUrl' => $publicUrl,
                'exportRoute' => route('client.observatory.board.export'),
            ])
            <a href="{{ route('client.observatory.reports.create') }}"
               class="inline-flex h-9 items-center rounded-lg bg-teal-600 px-4 text-sm font-medium text-white hover:bg-teal-500">
                Nuevo reporte
            </a>
        </div>
    </x-slot:actions>

    @include('modules.observatory.partials.index-workspace', [
        'filters' => $filters,
        'filterOptions' => $filterOptions,
        'board' => $board,
        'map' => $map,
        'events' => $events,
        'showClientColumn' => false,
        'eventShowRoute' => 'client.observatory.events.show',
        'accent' => 'teal',
        'observatoryLiveUrl' => route('client.observatory.live', $filters->query()),
    ])
</x-client-layout>
