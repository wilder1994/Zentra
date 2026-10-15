<x-client-layout title="Reservas">
    @include('modules.client.bookings.partials.calendar', [
        'mode' => 'admin',
        'urls' => [
            'index' => 'client.bookings.index',
            'zones' => route('client.bookings.zones'),
            'members' => route('client.bookings.members'),
            'visitor' => route('client.bookings.visitor'),
            'store' => route('client.bookings.store'),
            'booking' => url('client/bookings').'/__ID__',
        ],
    ])
</x-client-layout>
