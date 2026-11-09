@php
    use App\Support\Structure\StructureCopy;
@endphp

<x-client-layout :title="$installation->name" :wide="true">
    <div
        class="space-y-4"
        x-data="{ parentId: @js((string) old('parent_id', '')) }"
        @structure-parent.window="parentId = String($event.detail.id); $nextTick(() => { $refs.name?.focus(); $refs.createForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 min-w-0">
                @if ($hasMultipleSites)
                    <a href="{{ route('client.installations.index') }}" class="text-sm text-slate-400 hover:text-white shrink-0">← Sedes</a>
                @endif
                <div class="flex items-center gap-2 min-w-0">
                    <h2 class="text-base font-semibold text-white truncate">{{ $installation->name }}</h2>
                    <x-ui.intro-help storage-key="client.mi-estructura.show" :text="StructureCopy::helpShow()" />
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            <div class="min-w-0 rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ StructureCopy::unitPlural($client) }}</h3>
                    <form method="GET" action="{{ route('client.installations.show', $installation) }}" class="flex items-center gap-2 min-w-0 flex-1 sm:flex-initial sm:max-w-sm justify-end">
                        <label for="structure_q" class="sr-only">Buscar en la estructura</label>
                        <input type="search" id="structure_q" name="q" value="{{ $search }}"
                               placeholder="Buscar en la estructura…"
                               class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30">
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-slate-700 px-3 text-xs text-slate-300 hover:bg-slate-800 shrink-0">Buscar</button>
                        @if ($search !== '')
                            <a href="{{ route('client.installations.show', $installation) }}" class="text-xs text-slate-500 hover:text-slate-300 shrink-0">Limpiar</a>
                        @endif
                    </form>
                </div>

                @if ($tree->isEmpty())
                    <p class="text-slate-500 text-sm">
                        @if ($search !== '')
                            No hay coincidencias para «{{ $search }}».
                        @else
                            {{ StructureCopy::emptyTree($client, $installation->name) }}
                        @endif
                    </p>
                @else
                    <x-client.structure-tree :nodes="$tree" :census="$census" :pick-parent="true" />
                @endif
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 lg:sticky lg:top-20" x-ref="createForm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-4">{{ StructureCopy::addButton($client) }}</h3>
                <form action="{{ route('client.structures.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="installation_id" value="{{ $installation->id }}">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1" for="structure_name">Nombre</label>
                        <input id="structure_name" type="text" name="name" x-ref="name" value="{{ old('name') }}" required placeholder="Torre A, Salón B, 302…" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                        @if ($client->structureType)
                            <p class="mt-1 text-[11px] text-slate-500">Tipo: {{ $client->structureType->name }}</p>
                        @else
                            <p class="mt-1 text-[11px] text-amber-400">Sin tipo en la ficha del cliente. Configúralo antes de agregar.</p>
                        @endif
                        @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1" for="parent_id">Crear dentro de</label>
                        <select id="parent_id" name="parent_id" x-model="parentId" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                            <option value="">{{ StructureCopy::createParentRoot($installation->name) }}</option>
                            @foreach ($parentOptions as $parent)
                                <option value="{{ $parent['id'] }}">{{ str_repeat("\u{00A0}\u{00A0}", $parent['depth'] + 1).$parent['name'] }}</option>
                            @endforeach
                        </select>
                        @error('parent_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">
                        {{ StructureCopy::addButton($client) }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-client-layout>
