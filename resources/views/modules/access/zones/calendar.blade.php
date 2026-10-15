<x-access-layout title="Reservas">
    <div class="space-y-5">
        <div>
            @include('modules.access.zones.partials.tabs', ['tab' => 'calendario'])
        </div>

        @include('modules.client.bookings.partials.calendar', [
            'mode' => 'porteria',
            'urls' => [
                'index' => 'access.zones.calendar',
                'members' => route('access.zones.members'),
                'visitor' => route('access.zones.visitor'),
                'store' => route('access.zones.request'),
            ],
        ])
    </div>
</x-access-layout>
