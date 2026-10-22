@php
    /** @var \App\Support\Observatory\ObservatoryFilters $filters */
    $options = $filterOptions ?? ['places' => [], 'types' => [], 'comunas' => []];
    $place = $filters->installationId !== null
        ? 'site:'.$filters->installationId
        : ($filters->clientId !== null && ($isCompany ?? false) ? 'client:'.$filters->clientId : '');
    $selectClass = 'mt-1 block h-9 w-full px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
    $showSearch = ($vista ?? 'tablero') === 'eventos';
    $placeLabel = ($isCompany ?? false) ? 'Cliente o instalación' : 'Instalación';
@endphp

<div x-data="observatoryFilters(@js([
        'values' => [
            'place' => $place,
            'status' => $filters->status,
            'type' => $filters->type,
            'comuna' => $filters->comuna,
            'q' => $filters->search,
        ],
        'places' => $options['places'],
    ]))" class="shrink-0">
    <input type="hidden" name="client_id" :value="clientId" :disabled="!clientId">
    <input type="hidden" name="installation_id" :value="installationId" :disabled="!installationId">
    <input type="hidden" name="status" :value="values.status" :disabled="!values.status">
    <input type="hidden" name="type" :value="values.type" :disabled="!values.type">
    <input type="hidden" name="comuna" :value="values.comuna" :disabled="!values.comuna">
    <input type="hidden" name="q" :value="values.q" :disabled="!values.q">

    <span class="block text-xs text-slate-500">Filtros</span>
    <button type="button" @click="show()"
            class="mt-1 inline-flex h-9 items-center gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white hover:bg-slate-800">
        <svg class="h-4 w-4 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-6 8v5l-4 2v-7L4 5z"></path>
        </svg>
        <span>Filtros</span>
        <span x-show="count > 0" x-cloak
              class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-400 px-1.5 text-xs font-semibold text-slate-950"
              x-text="count"></span>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-24 sm:items-center sm:pt-4"
             @keydown.escape.window="placeOpen ? (placeOpen = false) : close()">
            <div class="absolute inset-0 bg-slate-950/70" @click="close()"></div>
            <div class="relative w-full max-w-[28rem] rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-2xl">
                <p class="text-sm font-semibold text-white">{{ $showSearch ? 'Filtrar eventos' : 'Filtrar tablero' }}</p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2 relative" @click.outside="placeOpen = false">
                        <span class="text-xs text-slate-500">{{ $placeLabel }}</span>
                        <button type="button" @click="placeOpen ? (placeOpen = false) : openPlaces()" @keydown.arrow-down.prevent="movePlace(1)"
                                class="mt-1 flex h-9 w-full items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white hover:bg-slate-800">
                            <span class="flex min-w-0 items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="11" cy="11" r="6.5"></circle><path stroke-linecap="round" d="M20 20l-4.2-4.2"></path>
                                </svg>
                                <span class="truncate" :class="placeLabel ? '' : 'text-slate-400'" x-text="placeLabel || 'Todos'"></span>
                            </span>
                            <svg class="h-4 w-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="placeOpen" x-cloak class="absolute left-0 right-0 z-10 mt-1 rounded-lg border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                            <input type="search" x-ref="placeSearch" x-model="placeQuery" @input="placeActive = 0"
                                   @keydown.arrow-down.prevent="movePlace(1)" @keydown.arrow-up.prevent="movePlace(-1)" @keydown.enter.prevent="enterPlace()"
                                   placeholder="Escribe cliente, instalación o DANE…" autocomplete="off"
                                   class="w-full h-9 px-3 text-sm rounded-md border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600">
                            <ul x-ref="placeList" class="mt-2 max-h-60 overflow-y-auto" role="listbox">
                                <li x-show="placeQuery.trim() === ''" @mousedown.prevent="pickPlace(null)"
                                    class="cursor-pointer rounded-md px-3 py-1.5 text-sm italic text-slate-400 hover:bg-slate-800">Todos</li>
                                <template x-for="(o, i) in placeMatches" :key="o.value">
                                    <li role="option" @mousedown.prevent="pickPlace(o)" @mouseenter="placeActive = i"
                                        :class="i === placeActive ? 'bg-slate-800 text-white' : 'text-slate-300'"
                                        class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-3 py-1.5 text-sm">
                                        <span class="truncate" x-text="o.label"></span>
                                        <span class="shrink-0 truncate max-w-[45%] text-[11px] text-slate-500" x-text="o.hint"></span>
                                    </li>
                                </template>
                                <li x-show="placeMatches.length === 0" class="px-3 py-1.5 text-sm text-slate-500">Sin resultados</li>
                            </ul>
                        </div>
                    </div>

                    <label class="block">
                        <span class="text-xs text-slate-500">Estado</span>
                        <select x-model="draft.status" class="{{ $selectClass }}">
                            <option value="">Todos</option>
                            @foreach (\App\Enums\ObservatoryEventStatus::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-500">Tipo de evento</span>
                        <select x-model="draft.type" class="{{ $selectClass }}">
                            <option value="">Todos</option>
                            @foreach ($options['types'] as $type)
                                <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if (($options['comunas'] ?? []) !== [])
                        <label class="block sm:col-span-2">
                            <span class="text-xs text-slate-500">Comuna (Cali)</span>
                            <select x-model="draft.comuna" class="{{ $selectClass }}">
                                <option value="">Todas</option>
                                @foreach ($options['comunas'] as $row)
                                    <option value="{{ $row['code'] }}">{{ $row['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    @if ($showSearch)
                        <label class="block sm:col-span-2">
                            <span class="text-xs text-slate-500">Texto o folio</span>
                            <input type="search" x-model="draft.q" placeholder="Folio (EV-…) o una palabra del reporte" maxlength="120"
                                   class="{{ $selectClass }} placeholder:text-slate-600">
                        </label>
                    @endif
                </div>

                <div class="mt-4 flex items-center justify-between gap-2">
                    <button type="button" @click="clear()" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:bg-slate-800">Limpiar</button>
                    <div class="flex gap-2">
                        <button type="button" @click="close()" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                        <button type="button" @click="apply()" class="h-9 rounded-lg bg-amber-400 px-4 text-sm font-semibold text-slate-950 hover:bg-amber-300">Aplicar</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
