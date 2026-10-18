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

        {{-- KPIs --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="{{ $card }} relative overflow-hidden px-4 py-4">
                <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-indigo-500 to-sky-400"></div>
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Instalaciones
                        <x-ui.help-tip text="Instalaciones activas en este alcance. Sin ubicación = no tienen pin en el mapa." />
                    </p>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/></svg>
                    </span>
                </div>
                <p class="mt-1 text-3xl font-semibold text-white tabular-nums" x-text="board.installations_count ?? 0">{{ $board['installations_count'] ?? 0 }}</p>
                <p class="mt-1 text-xs" :class="(board.installations_without_location || 0) > 0 ? 'text-amber-300' : 'text-emerald-300'">
                    <span x-text="(board.installations_without_location || 0) > 0 ? (board.installations_without_location + ' sin ubicación') : 'Todas ubicadas en el mapa'">
                        {{ ($board['installations_without_location'] ?? 0) > 0 ? $board['installations_without_location'].' sin ubicación' : 'Todas ubicadas en el mapa' }}
                    </span>
                </p>
            </div>

            <div class="{{ $card }} relative overflow-hidden px-4 py-4">
                <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-teal-500 to-emerald-400"></div>
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Servicios hoy
                        <x-ui.help-tip text="Puestos activos (un puesto es un servicio) y vigilantes asignados a ellos." />
                    </p>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-teal-500/15 text-teal-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/></svg>
                    </span>
                </div>
                <p class="mt-1 text-3xl font-semibold text-white tabular-nums" x-text="board.services_today ?? 0">{{ $board['services_today'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-400"><span class="tabular-nums" x-text="board.staff_count ?? 0">{{ $board['staff_count'] ?? 0 }}</span> vigilantes asignados</p>
            </div>

            <div class="{{ $card }} relative overflow-hidden px-4 py-4">
                <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-amber-500 to-yellow-300"></div>
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Revistas hoy
                        <x-ui.help-tip text="Revistas cumplidas de las esperadas hoy (06:00 a 06:00), según las revistas contratadas en cada puesto." />
                    </p>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/15 text-amber-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="4" width="12" height="17" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 12.5l2 2 3.5-4"/></svg>
                    </span>
                </div>
                <p class="mt-1 text-3xl font-semibold text-white tabular-nums">
                    <span x-text="board.reviews_done_today ?? 0">{{ $board['reviews_done_today'] ?? 0 }}</span><span class="text-base font-normal text-slate-500"> de <span x-text="board.reviews_expected_today ?? 0">{{ $board['reviews_expected_today'] ?? 0 }}</span></span>
                </p>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-800">
                    <div class="h-full rounded-full transition-all duration-500"
                         :class="{ 'bg-emerald-400': reviewsTone === 'emerald', 'bg-amber-400': reviewsTone === 'amber', 'bg-red-400': reviewsTone === 'red', 'bg-slate-600': reviewsTone === 'slate' }"
                         :style="'width:' + (reviewsPct ?? 0) + '%'"></div>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    <span x-text="reviewsPct === null ? 'Sin revistas programadas hoy' : (reviewsPct + '% · ' + (board.reviews_today ?? 0) + ' registradas')">Revistas del día</span>
                </p>
            </div>

            <div class="{{ $card }} relative overflow-hidden px-4 py-4">
                <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-500 to-rose-400"></div>
                <div class="flex items-start justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Novedades hoy
                        <x-ui.help-tip align="right" text="Cambios de servicio de hoy: altas, bajas, modalidad y vigilantes." />
                    </p>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg"
                          :class="(board.novedades_today || 0) > 0 ? 'bg-orange-500/20 text-orange-300' : 'bg-slate-800 text-slate-400'">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path stroke-linecap="round" d="M10 20.5a2 2 0 0 0 4 0"/></svg>
                    </span>
                </div>
                <p class="mt-1 text-3xl font-semibold tabular-nums" :class="(board.novedades_today || 0) > 0 ? 'text-orange-300' : 'text-white'" x-text="board.novedades_today ?? 0">{{ $board['novedades_today'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-400">
                    <span x-text="board.last_novelty_at ? ('Última a las ' + board.last_novelty_at) : 'Sin cambios hoy'">{{ ! empty($board['last_novelty_at']) ? 'Última a las '.$board['last_novelty_at'] : 'Sin cambios hoy' }}</span>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Mapa --}}
            <div class="{{ $card }} relative overflow-hidden h-80">
                <div x-ref="map" class="h-full w-full"></div>
                <div class="pointer-events-none absolute left-3 right-3 top-3 z-10 flex items-center justify-between gap-2">
                    <div class="pointer-events-auto inline-flex rounded-md border border-slate-700 bg-slate-950/90 p-0.5 text-xs" x-show="mapReady">
                        <button type="button" @click="setMapType('satellite')" class="rounded px-2 py-1 font-medium"
                                :class="mapType === 'satellite' ? 'bg-indigo-600/80 text-white' : 'text-slate-400 hover:text-slate-200'">Satélite</button>
                        <button type="button" @click="setMapType('terrain')" class="rounded px-2 py-1 font-medium"
                                :class="mapType === 'terrain' ? 'bg-indigo-600/80 text-white' : 'text-slate-400 hover:text-slate-200'">Terreno</button>
                    </div>
                </div>
                <div class="pointer-events-none absolute bottom-3 left-3 z-10 flex flex-wrap items-center gap-3 rounded-md border border-slate-700 bg-slate-950/90 px-2.5 py-1.5 text-[11px] text-slate-300" x-show="mapReady">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-teal-600 ring-1 ring-slate-950"></span>Con servicios</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-600 ring-1 ring-slate-950"></span>Sin servicios</span>
                    <span class="text-slate-500">El número = servicios</span>
                </div>
                <div x-show="mapError" x-cloak class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-500">
                    Mapa no disponible: falta la clave de Google Maps.
                </div>
            </div>

            {{-- Novedades de servicio --}}
            <div class="{{ $card }} p-4 flex flex-col h-80">
                <div class="flex items-baseline justify-between gap-2 shrink-0">
                    <h3 class="text-sm font-semibold text-white">Novedades de servicio</h3>
                    <span class="text-[11px] text-slate-500">Altas, bajas, modalidad y vigilantes</span>
                </div>
                <div class="mt-3 flex-1 min-h-0 overflow-y-auto space-y-2 pr-1">
                    <template x-for="row in (board.feed || [])" :key="row.id">
                        <div class="rounded-lg border border-slate-800 bg-slate-950/70 px-3 py-2">
                            <p class="text-[10px] text-slate-500 tabular-nums" x-text="row.at"></p>
                            <p class="text-sm text-slate-200 leading-snug" x-text="row.body"></p>
                        </div>
                    </template>
                    <p class="py-8 text-center text-sm text-slate-500" x-show="!(board.feed || []).length">Aún no hay cambios de servicio.</p>
                </div>
            </div>

            {{-- Revistas del supervisor --}}
            <div class="{{ $card }} p-4 flex flex-col h-80">
                <div class="flex items-baseline justify-between gap-2 shrink-0">
                    <h3 class="text-sm font-semibold text-white">Revistas del supervisor</h3>
                    <span class="text-[11px] text-slate-500">Últimas 40</span>
                </div>
                <div class="mt-3 flex-1 min-h-0 overflow-y-auto flex flex-wrap content-start gap-2 pr-1">
                    <template x-for="row in (board.reviews || [])" :key="row.id">
                        <article class="w-full sm:w-[calc(50%-0.25rem)] rounded-xl border px-3 py-2"
                                 :class="row.novelty ? 'border-amber-500/40 bg-amber-950/20' : 'border-slate-800 bg-slate-950/70'">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[10px] text-slate-500 tabular-nums" x-text="row.at"></p>
                                <span x-show="row.novelty" class="text-[10px] font-semibold text-amber-300">Novedad</span>
                            </div>
                            <p class="text-sm text-white leading-snug" x-text="row.supervisor"></p>
                            <p class="text-[11px] text-slate-400 mt-0.5"><span x-text="row.post"></span><span class="text-slate-600"> · </span><span x-text="row.guard"></span></p>
                            <p class="text-xs text-slate-300 mt-1 leading-snug" x-text="row.record"></p>
                        </article>
                    </template>
                    <p class="w-full py-8 text-center text-sm text-slate-500" x-show="!(board.reviews || []).length">Sin revistas en este alcance.</p>
                </div>
            </div>

            {{-- Instalaciones y servicios --}}
            <div class="{{ $card }} p-4 flex flex-col h-80">
                <div class="flex flex-wrap items-center justify-between gap-2 shrink-0">
                    <h3 class="text-sm font-semibold text-white">Instalaciones y servicios
                        <span class="ml-1 text-xs font-normal text-slate-500 tabular-nums" x-text="'(' + installations.length + ')'"></span>
                    </h3>
                    <input type="search" x-model="query" placeholder="Nombre, código o DANE…"
                           class="h-8 w-48 px-2.5 text-xs rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
                </div>
                <div class="mt-3 flex-1 min-h-0 overflow-y-auto pr-1 space-y-1.5">
                    <template x-for="row in installations" :key="row.id">
                        <div class="rounded-lg border border-slate-800 bg-slate-950/60">
                            <div class="flex items-center gap-2 px-3 py-2">
                                <button type="button" @click="toggle(row.id)" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                    <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform" :class="expanded[row.id] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-100" x-text="row.name"></span>
                                        <span class="block truncate text-[11px] text-slate-500"
                                              x-text="[row.client, row.code, row.city].filter(Boolean).join(' · ')"></span>
                                    </span>
                                </button>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] tabular-nums"
                                      :class="row.posts_count > 0 ? 'bg-teal-500/15 text-teal-300' : 'bg-slate-800 text-slate-500'"
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
                    <div x-show="!installations.length" class="py-8 text-center">
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
