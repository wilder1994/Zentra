@php
    $activeTab = $clientNavActive ?? 'cliente';
    $vista = $vista ?? $activeTab;
    $statusTone = $client->is_active ? 'text-emerald-400' : 'text-slate-500';
    $locationBits = array_filter([(string) $client->city, (string) $client->department]);
    $locationLabel = $locationBits !== [] ? implode(', ', $locationBits) : null;
@endphp

<x-slot:subtitle>
    <span class="text-slate-500">
        {{ $client->slug }}
        @if ($locationLabel)
            · {{ $locationLabel }}
        @endif
        · {{ $client->securityCompany?->package_modality?->label() ?? 'Modalidad N/D' }}
    </span>
    <span class="{{ $statusTone }} font-medium pl-3 sm:pl-4">
        {{ $client->is_active ? 'Activo' : 'Archivado' }}
    </span>
    @if ($client->has_access)
        <span class="text-indigo-300 font-medium pl-3">Accesos</span>
    @endif
    @if ($client->has_supervision)
        <span class="text-amber-300 font-medium pl-3">Supervisión</span>
    @endif
    @if ($client->has_observatory)
        <span class="text-sky-300 font-medium pl-3">Observatorio</span>
    @endif
    @if ($client->isCatalogOnly())
        <span class="text-slate-500 font-medium pl-3">Solo ficha</span>
    @endif
</x-slot:subtitle>

<x-slot:actions>
    <x-ui.button variant="secondary" :href="route('company.clients.index')" size="sm">← Cartera</x-ui.button>
    @can('company.clients.manage')
        @if ($client->is_active)
            <form method="POST" action="{{ route('company.clients.archive', $client) }}" onsubmit="return confirm('¿Archivar este cliente? Sus instalaciones y puestos se archivan y liberan cupo. Podrás restaurarlo si hay cupo.')">
                @csrf
                <x-ui.button type="submit" variant="secondary" size="sm">Archivar</x-ui.button>
            </form>
        @elseif ($client->lifecycle === \App\Enums\ClientLifecycle::Active)
            <form method="POST" action="{{ route('company.clients.restore', $client) }}">
                @csrf
                <x-ui.button type="submit" variant="success" size="sm">Restaurar</x-ui.button>
            </form>
            <form method="POST" action="{{ route('company.clients.release', $client) }}" onsubmit="return confirm('¿Dar de baja este cliente? Sale de tu cartera y no se puede deshacer. Los datos se conservan según la política de retención.')">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="secondary" size="sm">Dar de baja</x-ui.button>
            </form>
        @endif
        <x-ui.button :href="route('company.clients.create')" size="sm">+ Cliente</x-ui.button>
    @endcan
</x-slot:actions>

<x-slot:headerTabs>
    <a
        href="{{ route('company.clients.show', [$client, 'vista' => 'cliente']) }}"
        @class(['admin-header-tab', 'is-active' => in_array($vista, ['cliente', 'sitio', 'puertas', 'accesos', 'supervision'], true)])
    >
        Cliente
    </a>

    @if ($client->has_access)
        <a
            href="{{ route('company.clients.show', [$client, 'vista' => 'resumen']) }}"
            @class(['admin-header-tab', 'is-active' => $vista === 'resumen'])
        >
            Resumen
        </a>
    @endif
</x-slot:headerTabs>
