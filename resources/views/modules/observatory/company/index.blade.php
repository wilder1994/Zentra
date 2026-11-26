<x-company-layout title="Observatorio">
    <x-slot:actions>
        @include('modules.observatory.partials.header-filters', [
            'action' => route('company.observatory.events.index'),
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'vista' => $filters->vista,
            'isCompany' => true,
            'shareClients' => $shareClients,
            'exportRoute' => route('company.observatory.board.export'),
        ])
    </x-slot:actions>

    <x-slot:headerTabs>
        @include('modules.observatory.partials.nav-tabs', [
            'indexRoute' => 'company.observatory.events.index',
            'typesRoute' => 'company.observatory.types.index',
            'vista' => $filters->vista,
        ])
    </x-slot:headerTabs>

    @include('modules.observatory.partials.index-workspace', [
        'filters' => $filters,
        'filterOptions' => $filterOptions,
        'board' => $board,
        'map' => $map,
        'events' => $events,
        'showClientColumn' => true,
        'eventShowRoute' => 'company.observatory.events.show',
        'accent' => 'indigo',
        'observatoryLiveUrl' => route('company.observatory.live', $filters->query()),
        'observatoryTrailUrl' => str_replace('/0/', '/__SHIFT__/', route('company.observatory.shifts.trail', ['shift' => 0])),
        'observatoryPositionsUrl' => route('company.observatory.shifts.positions', $filters->query()),
    ])
</x-company-layout>
