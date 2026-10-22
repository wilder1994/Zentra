<x-company-layout title="Tipos del Observatorio">
    <x-slot:actions>
        <form method="GET" action="{{ route('company.observatory.types.index') }}" class="flex flex-wrap items-end gap-2">
            <x-ui.filter-picker
                name="client_id"
                label="Cliente"
                :options="$filterClients->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values()->all()"
                :value="$filterClient ? (string) $filterClient->id : ''"
                all-label="Elige un cliente"
                placeholder="Escribe el nombre del cliente…"
            />
        </form>
    </x-slot:actions>

    <x-slot:headerTabs>
        @include('modules.observatory.partials.nav-tabs', [
            'indexRoute' => 'company.observatory.events.index',
            'typesRoute' => 'company.observatory.types.index',
            'vista' => 'tipos',
        ])
    </x-slot:headerTabs>

    @if (! $filterClient)
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-6 text-sm text-slate-400">
            Cada cliente tiene sus propios tipos de hecho (nombre, nivel y color del pin). Elige un cliente en el buscador de arriba para verlos{{ $canEdit ? ' y cambiarlos' : '' }}.
        </div>
    @else
        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($canEdit && $filterClients->count() > 1)
            @php($others = $filterClients->reject(fn ($c) => (int) $c->id === (int) $filterClient->id)->values())
            <div class="mb-3 flex justify-end" x-data="{ open: false, q: '', all: false }">
                <button type="button" @click="open = true"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-700 px-3 text-sm text-slate-200 hover:bg-slate-800">
                    <svg class="h-4 w-4 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>
                    Copiar estos tipos a otros clientes
                </button>
                <template x-teleport="body">
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
                        <div class="absolute inset-0 bg-slate-950/70" @click="open = false"></div>
                        <form method="POST" action="{{ route('company.observatory.types.copy', $filterClient) }}"
                              class="relative flex max-h-[min(90vh,40rem)] w-full max-w-md flex-col rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                            @csrf
                            <div class="shrink-0 border-b border-slate-800 px-5 py-4">
                                <h3 class="text-sm font-semibold text-white">Copiar tipos de {{ $filterClient->name }}</h3>
                                <p class="mt-1 text-xs text-slate-400">En cada cliente elegido se crean los tipos que le falten. Los que ya tiene no se tocan.</p>
                                <input type="search" x-model="q" placeholder="Buscar cliente"
                                       class="mt-3 h-9 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white">
                                <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-300">
                                    <input type="checkbox" x-model="all" @change="$root.querySelectorAll('[data-copy-target]').forEach((el) => { if (el.closest('label').style.display !== 'none') el.checked = all })"
                                           class="rounded border-slate-700 text-indigo-600">
                                    Seleccionar todos
                                </label>
                            </div>
                            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto px-5 py-3">
                                @foreach ($others as $other)
                                    <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
                                           x-show="!q.trim() || @js(\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($other->name))).includes(q.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''))">
                                        <input type="checkbox" name="client_ids[]" value="{{ $other->id }}" data-copy-target class="rounded border-slate-700 text-indigo-600">
                                        {{ $other->name }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex shrink-0 justify-end gap-2 border-t border-slate-800 px-5 py-3">
                                <button type="button" @click="open = false" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                                <button type="submit" class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">Copiar</button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        @endif

        @include('modules.observatory.partials.types-catalog', [
            'types' => $types,
            'nextColor' => $nextColor,
            'canEdit' => $canEdit,
            'storeUrl' => $canEdit ? route('company.observatory.types.store', $filterClient) : null,
            'storeRoute' => null,
            'updateRoute' => 'company.observatory.types.update',
            'destroyRoute' => 'company.observatory.types.destroy',
            'accent' => 'indigo',
            'readOnlyNote' => 'Para crear o cambiar tipos necesitas el permiso «Gestionar» del Observatorio.',
        ])
    @endif
</x-company-layout>
