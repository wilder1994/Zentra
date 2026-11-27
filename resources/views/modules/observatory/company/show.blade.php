<x-company-layout :title="$event->folio()">
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('company.observatory.events.index', ['vista' => 'eventos'])" size="sm">← Eventos</x-ui.button>
    </x-slot:actions>
    @include('modules.observatory.partials.event-card', [
        'event' => $event,
        'canUpdateStatus' => $canUpdateStatus,
        'canMove' => $canMove ?? false,
        'statuses' => $statuses,
        'statusAction' => route('company.observatory.events.status', $event),
        'moveRoute' => 'company.observatory.events.reports.move',
        'moveOptions' => $moveOptions ?? [],
        'folioMap' => $folioMap ?? null,
    ])
</x-company-layout>
