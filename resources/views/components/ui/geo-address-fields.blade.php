@props([
    'address' => null,
    'city' => null,
    'department' => null,
    'latitude' => null,
    'longitude' => null,
    'accent' => 'default',
])

@php
    $uid = 'geo-'.uniqid();
    $inputAccent = $accent === 'platform' ? 'platform' : 'indigo';
    $mapsConfig = [
        'apiKey' => config('google-maps.api_key'),
        'center' => config('google-maps.default_center'),
        'zoom' => (int) config('google-maps.default_zoom', 6),
    ];
@endphp

<div
    class="space-y-4"
    data-cali-comunas-url="{{ route('geo.cali-comunas') }}"
    x-data="geoAddressPicker({
        address: @js(old('address', $address)),
        city: @js(old('city', $city)),
        department: @js(old('department', $department)),
        latitude: @js(old('latitude', $latitude)),
        longitude: @js(old('longitude', $longitude)),
        maps: @js($mapsConfig),
        uid: @js($uid),
    })"
>
    <div>
        <x-ui.label for="{{ $uid }}-address">Dirección</x-ui.label>
        <div class="flex gap-2">
            <x-ui.input
                id="{{ $uid }}-address"
                name="address"
                x-model="address"
                placeholder="Calle, barrio…"
                :accent="$inputAccent"
                class="flex-1"
            />
            <button
                type="button"
                @click="openMap()"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-950/80 p-0.5 transition hover:border-slate-500 hover:bg-slate-900"
                title="Seleccionar en mapa"
                aria-label="Seleccionar ubicación en mapa"
            >
                <img
                    src="{{ asset('images/ui/map-pin.png') }}"
                    alt=""
                    class="h-7 w-7 object-contain"
                    aria-hidden="true"
                >
            </button>
        </div>
        <x-ui.field-error :messages="$errors->get('address')" />
        <p class="mt-1 text-xs text-slate-500">Puedes editar el texto sin cambiar las coordenadas. Usa el mapa para fijar la ubicación.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-ui.label for="{{ $uid }}-city">Ciudad</x-ui.label>
            <x-ui.input id="{{ $uid }}-city" name="city" x-model="city" placeholder="Municipio / ciudad" :accent="$inputAccent" />
            <x-ui.field-error :messages="$errors->get('city')" />
        </div>
        <div>
            <x-ui.label for="{{ $uid }}-department">Departamento</x-ui.label>
            <x-ui.input id="{{ $uid }}-department" name="department" x-model="department" placeholder="Departamento" :accent="$inputAccent" />
            <x-ui.field-error :messages="$errors->get('department')" />
        </div>
    </div>

    <input type="hidden" name="latitude" :value="latitude ?? ''">
    <input type="hidden" name="longitude" :value="longitude ?? ''">

    <p x-show="latitude !== null && longitude !== null" x-cloak class="text-xs text-slate-500 tabular-nums">
        Coordenadas: <span x-text="Number(latitude).toFixed(5)"></span>, <span x-text="Number(longitude).toFixed(5)"></span>
    </p>

    {{-- Modal mapa --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $uid }}-map-title"
        >
            <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" @click="closeMap()"></div>
            <div class="relative w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl overflow-visible">
                <div class="flex items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
                    <div>
                        <h3 id="{{ $uid }}-map-title" class="text-sm font-semibold text-white">Seleccionar ubicación</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Busca un lugar o mueve el pin en el mapa.</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-white text-sm" @click="closeMap()">Cerrar</button>
                </div>

                <div class="p-4 space-y-3 overflow-visible">
                    <template x-if="!maps.apiKey">
                        <p class="text-sm text-amber-300 rounded-lg border border-amber-700/50 bg-amber-900/20 px-3 py-2">
                            Configura <code class="text-amber-200">GOOGLE_MAPS_API_KEY</code> y habilita Maps JavaScript API + Places API (New).
                        </p>
                    </template>

                    <div class="relative z-50">
                        <input
                            type="text"
                            x-model="searchQuery"
                            @input="onSearchInput()"
                            @keydown.escape.window="suggestions = []"
                            placeholder="Buscar dirección, empresa o lugar…"
                            class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30"
                            autocomplete="off"
                        >
                        <ul
                            x-show="suggestions.length > 0"
                            x-cloak
                            class="absolute left-0 right-0 top-full mt-1 max-h-56 overflow-auto rounded-lg border border-slate-700 bg-slate-950 shadow-xl"
                        >
                            <template x-for="(item, idx) in suggestions" :key="idx">
                                <li>
                                    <button
                                        type="button"
                                        class="w-full px-3 py-2 text-left hover:bg-slate-800"
                                        @click="selectSuggestion(item)"
                                    >
                                        <span class="block text-sm text-white" x-text="item.label"></span>
                                        <span class="block text-xs text-slate-400" x-show="item.secondary" x-text="item.secondary"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <p x-show="searching" x-cloak class="mt-1 text-[11px] text-slate-500">Buscando…</p>
                    </div>

                    <div x-ref="map" class="h-72 w-full rounded-lg border border-slate-800 bg-slate-950"></div>

                    <p class="text-xs text-slate-400" x-text="draftLabel || 'Sin ubicación seleccionada'"></p>
                    <p x-show="draftComuna" x-cloak class="text-xs text-sky-300">
                        <span x-text="draftComuna?.name"></span> · IDESC Cali
                    </p>
                    <p x-show="!draftComuna && draftLat !== null" x-cloak class="text-xs text-slate-500">
                        Fuera del perímetro urbano de Cali. El área sale de la dirección (Places).
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-800 px-4 py-3">
                    <button type="button" class="h-9 px-4 text-sm rounded-lg border border-slate-700 text-slate-200 hover:bg-slate-800" @click="closeMap()">Cancelar</button>
                    <button
                        type="button"
                        class="h-9 px-4 text-sm font-medium rounded-lg {{ $accent === 'platform' ? 'bg-violet-600 hover:bg-violet-500' : 'bg-indigo-600 hover:bg-indigo-500' }} text-white disabled:opacity-40"
                        :disabled="draftLat === null"
                        @click="confirmPlace()"
                    >
                        Usar esta ubicación
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
        <style>[x-cloak]{display:none!important}</style>
    @endpush
@endonce
