@php
    $selectClass = 'mt-1 block h-9 w-full px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
    $values = [
        'zone_id' => $filter->zoneId,
        'supervisor_id' => $filter->supervisorId,
        'kind' => $filter->sheetKind,
        'client_id' => $filter->clientId,
        'novelty' => $filter->hasNovelty === null ? '' : ($filter->hasNovelty ? '1' : '0'),
    ];
@endphp

<div x-data="supervisionSheetFilters({ values: @js($values) })" class="shrink-0">
    <input type="hidden" name="zone_id" :value="values.zone_id">
    <input type="hidden" name="supervisor_id" :value="values.supervisor_id">
    <input type="hidden" name="kind" :value="values.kind">
    <input type="hidden" name="client_id" :value="values.client_id">
    <input type="hidden" name="novelty" :value="values.novelty">

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
             @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-slate-950/70" @click="close()"></div>
            <div class="relative w-full max-w-[26rem] rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-2xl">
                <p class="text-sm font-semibold text-white">Filtrar fichas</p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs text-slate-500">Zona</span>
                        <select x-model="draft.zone_id" class="{{ $selectClass }}">
                            <option value="">Todas</option>
                            @foreach ($zones ?? [] as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}{{ $zone->is_active ? '' : ' (inactiva)' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-500">Supervisor</span>
                        <select x-model="draft.supervisor_id" class="{{ $selectClass }}">
                            <option value="">Todos</option>
                            @foreach ($supervisors ?? [] as $supervisor)
                                <option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-500">Tipo</span>
                        <select x-model="draft.kind" class="{{ $selectClass }}">
                            <option value="">Todas</option>
                            @foreach ($kindLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs text-slate-500">Novedad</span>
                        <select x-model="draft.novelty" class="{{ $selectClass }}">
                            <option value="">Todas</option>
                            <option value="1">Con novedad</option>
                            <option value="0">Sin novedad</option>
                        </select>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs text-slate-500">Cliente</span>
                        <select x-model="draft.client_id" class="{{ $selectClass }}">
                            <option value="">Todos</option>
                            @foreach ($clients ?? [] as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </label>
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
