<x-company-layout :title="$installation->name">
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('company.installations.index')" size="sm">← Listado</x-ui.button>
        @can('update', $installation->client)
            <x-ui.button :href="route('company.installations.edit', $installation)" size="sm">Editar</x-ui.button>
        @endcan
    </x-slot:actions>

    <div class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-stretch">
            @include('modules.company.installations.partials.pin-map', [
                'installation' => $installation,
                'maps' => $maps,
                'mapId' => 'installation-pin-map',
            ])
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                <div>
                    <p class="text-xs text-slate-500">Instalación</p>
                    <h3 class="text-lg font-semibold text-white">{{ $installation->name }}</h3>
                    @if ($installation->addressLine() !== '')
                        <p class="text-xs text-slate-400 mt-1">{{ $installation->addressLine() }}</p>
                    @endif
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ $installation->client?->name }}
                        @if ($installation->is_client_site)
                            · Mismo cliente
                        @endif
                        · {{ $installation->is_active ? 'Activa' : 'Archivada' }}
                    </p>
                </div>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Tipo</dt>
                        <dd class="text-slate-200">{{ $installation->kindLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Código</dt>
                        <dd class="font-mono text-indigo-300">{{ $installation->code ?: '—' }}</dd>
                    </div>
                    @if ($installation->dane_code)
                        <div>
                            <dt class="text-xs text-slate-500">DANE de la instalación</dt>
                            <dd class="font-mono text-indigo-300">{{ $installation->dane_code }}</dd>
                        </div>
                    @endif
                    @php
                        $cali = $installation->hasCoordinates()
                            ? app(\App\Support\Geo\CaliComunaLayer::class)->locate((float) $installation->latitude, (float) $installation->longitude)
                            : null;
                    @endphp
                    <div>
                        <dt class="text-xs text-slate-500">{{ $cali ? 'Comuna' : $installation->areaKindLabel() }}</dt>
                        <dd class="text-slate-200">{{ $cali['name'] ?? ($installation->commune ?: '—') }}</dd>
                        @if ($cali)
                            <p class="text-[10px] text-slate-500">IDESC Cali</p>
                        @endif
                    </div>
                    @include('modules.company.installations.partials.ficha-staff')
                </dl>
            </div>
        </div>

        @if ($installation->client?->has_access || $installation->client?->has_supervision)
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                <div>
                    <p class="text-sm font-medium text-white">Puestos</p>
                    <p class="text-xs text-slate-500">Modalidad y vigilantes de la empresa. Un puesto no es una puerta.</p>
                </div>
                @include('modules.company.clients.partials.posts-block', [
                    'client' => $installation->client,
                    'installation' => $installation,
                    'installations' => collect([$installation]),
                    'vista' => 'sitio',
                    'accent' => 'indigo',
                    'canManageTree' => $canManageTree,
                    'postModalities' => $postModalities,
                    'returnTo' => 'installation',
                ])
            </div>
        @endif

        <p class="text-xs text-slate-500">
            Puertas se gestionan en la
            <a href="{{ route('company.clients.show', [$installation->client, 'vista' => 'puertas']) }}" class="text-indigo-400 hover:text-indigo-300">ficha del cliente</a>.
        </p>
    </div>

    @include('modules.company.installations.partials.pin-map-script', [
        'installation' => $installation,
        'maps' => $maps,
        'mapId' => 'installation-pin-map',
        'callback' => 'initInstallationPinMap',
    ])
</x-company-layout>
