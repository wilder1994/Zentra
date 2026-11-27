<x-client-layout :title="$event->folio()">
    <x-slot:actions>
        <a href="{{ route('client.observatory.events.index', ['vista' => 'eventos']) }}" class="text-sm text-slate-400 hover:text-white">← Eventos</a>
    </x-slot:actions>
    @include('modules.observatory.partials.event-card', [
            'event' => $event,
            'canUpdateStatus' => $canUpdateStatus,
            'canMove' => $canMove ?? false,
            'statuses' => $statuses,
            'statusAction' => route('client.observatory.events.status', $event),
            'moveRoute' => 'client.observatory.events.reports.move',
            'moveOptions' => $moveOptions ?? [],
        'folioMap' => $folioMap ?? null,
    ])
</x-client-layout>
