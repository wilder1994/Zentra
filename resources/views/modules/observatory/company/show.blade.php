<x-company-layout :title="$event->folio()">
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('company.observatory.events.index', ['vista' => 'eventos'])" size="sm">← Eventos</x-ui.button>
    </x-slot:actions>
    @include('modules.observatory.partials.event-card', [
        'event' => $event,
        'canUpdateStatus' => $canUpdateStatus,
        'canMerge' => $canMerge,
        'canDetach' => $canDetach,
        'statuses' => $statuses,
        'statusAction' => route('company.observatory.events.status', $event),
        'mergeAction' => route('company.observatory.events.merge', $event),
        'mergeCandidates' => $mergeCandidates,
        'detachRoute' => 'company.observatory.events.reports.detach',
        'folioMap' => $folioMap ?? null,
    ])
</x-company-layout>
