@php
    $board = $sigBoard ?? [];
    $clientId = $selectedClient?->id;
    $liveUrl = route('company.sig.live', array_filter(['client_id' => $clientId]));
    $clientOptions = $clients->map(fn ($client) => [
        'value' => (string) $client->id,
        'label' => $client->name,
        'hint' => (string) ($client->city ?? ''),
    ])->values()->all();
    $card = 'rounded-xl border border-slate-800 bg-slate-900/80';
@endphp

<x-company-layout title="Instalaciones">
    @if ($selectedClient)
        <x-slot:subtitle>
            <span class="text-slate-500">Filtrado por cliente</span>
            <span class="text-indigo-300 font-medium">{{ $selectedClient->name }}</span>
        </x-slot:subtitle>
    @endif

    <x-slot:actions>
        <form method="GET" action="{{ route('company.installations.index') }}" class="flex flex-wrap items-end gap-2">
            <x-ui.filter-picker
                name="client_id"
                label="Cliente"
                :options="$clientOptions"
                :value="$clientId ? (string) $clientId : ''"
                all-label="Todos los clientes"
                placeholder="Escribe el nombre del cliente…"
            />
        </form>
        @can('company.installations.manage')
            <x-ui.button :href="route('company.installations.create', array_filter(['client_id' => $clientId]))" size="sm" class="self-end">+ Instalación</x-ui.button>
        @endcan
    </x-slot:actions>

    <div class="space-y-4"
         x-data="installationsBoard"
         data-live-url="{{ $liveUrl }}"
         data-show-url="{{ route('company.installations.show', '__ID__') }}"
         data-board='@json($board)'>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Mapa --}}
            <div class="{{ $card }} relative isolate overflow-hidden min-h-[28rem] h-[min(70vh,36rem)]">
                <div x-ref="map" class="h-full w-full"></div>
                <div class="pointer-events-none absolute left-3 right-3 top-3 z-[2] flex items-center justify-between gap-2">
                    <div class="pointer-events-auto inline-flex rounded-md border border-slate-700 bg-slate-950/90 p-0.5 text-xs" x-show="mapReady">
                        <button type="button" @click="setMapType('satellite')" class="rounded px-2 py-1 font-medium"
                                :class="mapType === 'satellite' ? 'bg-indigo-600/80 text-white' : 'text-slate-400 hover:text-slate-200'">Satélite</button>
                        <button type="button" @click="setMapType('terrain')" class="rounded px-2 py-1 font-medium"
                                :class="mapType === 'terrain' ? 'bg-indigo-600/80 text-white' : 'text-slate-400 hover:text-slate-200'">Terreno</button>
                    </div>
                </div>
                <div class="pointer-events-none absolute bottom-3 left-3 z-[2] flex flex-wrap items-center gap-3 rounded-md border border-slate-700 bg-slate-950/90 px-2.5 py-1.5 text-[11px] text-slate-300" x-show="mapReady">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-teal-600 ring-1 ring-slate-950"></span>Con servicios</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-600 ring-1 ring-slate-950"></span>Sin servicios</span>
                    <span class="text-slate-500">Gota con número = grupo</span>
                </div>
                <div x-show="card" x-cloak x-transition.opacity
                     class="zmap-card absolute right-14 top-3 z-[3] w-60 max-w-[calc(100%-4.5rem)] max-h-[calc(100%-1.5rem)] overflow-y-auto">
                    <div class="px-3 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="zmap-card-kind" x-text="card?.kind === 'list' ? (card.rows.length + ' instalaciones en este punto') : 'Instalación'"></span>
                            <button type="button" class="zmap-card-close" @click="closeCard()" aria-label="Cerrar">×</button>
                        </div>
                        <template x-if="card?.kind === 'one'">
                            <div>
                                <p class="zmap-card-title mt-1.5 leading-snug" x-text="card.row.name"></p>
                                <p class="zmap-card-meta mt-0.5" x-text="[card.row.client, card.row.code].filter(Boolean).join(' · ')"></p>
                                <p class="mt-1.5">
                                    <span :class="card.row.posts > 0 ? 'zmap-card-ok' : 'zmap-card-faint'" x-text="card.row.posts + (card.row.posts === 1 ? ' servicio' : ' servicios')"></span>
                                    <span class="zmap-card-faint"> · </span>
                                    <span class="zmap-card-meta" x-text="card.row.guards + (card.row.guards === 1 ? ' vigilante' : ' vigilantes')"></span>
                                </p>
                                <p class="zmap-card-meta mt-1" x-show="card.row.address" x-text="card.row.address"></p>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <a :href="installationUrl(card.row.id)" class="zmap-card-link !mt-0">Ver instalación →</a>
                                    <span x-html="shareHtml(card.row)"></span>
                                </div>
                            </div>
                        </template>
                        <template x-if="card?.kind === 'list'">
                            <ul class="mt-1.5 space-y-1">
                                <template x-for="row in card.rows" :key="row.id">
                                    <li class="flex items-baseline justify-between gap-2">
                                        <a :href="installationUrl(row.id)" class="zmap-card-title truncate hover:underline" x-text="row.name"></a>
                                        <span class="zmap-card-faint shrink-0" x-text="row.posts + ' serv.'"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>
                    </div>
                </div>
                <div x-show="mapError" x-cloak class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-500">
                    Mapa no disponible: falta la clave de Google Maps.
                </div>
            </div>

            {{-- Instalaciones y servicios (junto al mapa) --}}
            <div class="{{ $card }} p-3 flex flex-col min-h-[28rem] h-[min(70vh,36rem)]">
                <div class="flex flex-wrap items-center justify-between gap-2 shrink-0">
                    <h3 class="text-sm font-semibold text-white">Instalaciones y servicios
                        <span class="ml-1 text-xs font-normal text-slate-500 tabular-nums" x-text="'(' + installations.length + ')'"></span>
                    </h3>
                    <input type="search" x-model="query" placeholder="Nombre, código o DANE…"
                           class="h-8 w-48 px-2.5 text-xs rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
                </div>
                <div class="mt-2 flex-1 min-h-0 overflow-y-auto pr-1 space-y-1.5">
                    <template x-for="row in installations" :key="row.id">
                        <div class="rounded-lg border border-slate-800 bg-slate-950/60">
                            <div class="flex items-center gap-2 px-3 py-1.5">
                                <button type="button" @click="toggle(row.id)" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                    <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform" :class="expanded[row.id] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-100" x-text="row.name"></span>
                                        <span class="block truncate text-[11px] text-slate-500"
                                              x-text="[row.client, row.code, row.city].filter(Boolean).join(' · ')"></span>
                                    </span>
                                </button>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] tabular-nums"
                                      :class="row.posts_count > 0 ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-500'"
                                      x-text="row.posts_count + ' serv.'"></span>
                                <span class="hidden sm:inline shrink-0 rounded-full bg-slate-800 px-2 py-0.5 text-[11px] text-slate-300 tabular-nums" x-text="row.guards_count + ' vig.'"></span>
                                <button type="button" x-show="row.has_location" @click="focusInstallation(row)" title="Ubicar en el mapa"
                                        class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-800 hover:text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                </button>
                                <span x-show="!row.has_location" title="Sin ubicación" class="shrink-0 text-[11px] text-amber-300">Sin pin</span>
                                <a :href="installationUrl(row.id)" class="ui-chip-link shrink-0">Ver</a>
                            </div>
                            <div x-show="expanded[row.id]" x-cloak class="border-t border-slate-800 px-3 py-2">
                                <template x-if="row.posts.length">
                                    <table class="w-full text-xs">
                                        <thead class="text-[10px] uppercase text-slate-500">
                                            <tr>
                                                <th class="py-1 text-left font-medium">Servicio</th>
                                                <th class="py-1 text-left font-medium">Modalidad</th>
                                                <th class="py-1 text-right font-medium">Revistas/día</th>
                                                <th class="py-1 text-right font-medium">Vigilantes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800/70">
                                            <template x-for="(post, index) in row.posts" :key="index">
                                                <tr>
                                                    <td class="py-1.5 text-slate-200" x-text="post.name"></td>
                                                    <td class="py-1.5 text-slate-400" x-text="post.modality"></td>
                                                    <td class="py-1.5 text-right text-slate-300 tabular-nums" x-text="post.reviews_per_day"></td>
                                                    <td class="py-1.5 text-right text-slate-200 tabular-nums" x-text="post.guards"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </template>
                                <p x-show="!row.posts.length" class="py-1 text-xs text-slate-500">Sin servicios activos en esta instalación.</p>
                            </div>
                        </div>
                    </template>
                    <div x-show="!installations.length" class="py-4 text-center">
                        <p class="text-sm text-slate-400" x-text="query ? 'Ninguna instalación coincide con la búsqueda.' : 'Aún no hay instalaciones.'">Aún no hay instalaciones.</p>
                        @can('company.installations.manage')
                            <a x-show="!query" href="{{ route('company.installations.create', array_filter(['client_id' => $clientId])) }}" class="mt-3 inline-block text-xs font-semibold text-indigo-300 hover:text-indigo-200">Crear instalación</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        {{-- Respaldo sin JS: nombres para buscadores y lectores --}}
        <noscript>
            <ul class="text-sm text-slate-300">
                @foreach ($board['installations'] ?? [] as $row)
                    <li>{{ $row['name'] }} — {{ $row['client'] }}</li>
                @endforeach
            </ul>
        </noscript>
    </div>
</x-company-layout>
