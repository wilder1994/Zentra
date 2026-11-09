<x-client-layout title="Mi estructura" :wide="true">
    <div
        class="space-y-4"
        x-data="{ parentId: @js((string) old('parent_id', '')) }"
        @structure-parent.window="parentId = String($event.detail.id); $nextTick(() => { $refs.name?.focus(); $refs.createForm?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })"
    >
        @if ($installations->isEmpty())
            <div class="rounded-xl border border-amber-800/60 bg-amber-950/30 p-6">
                <p class="text-sm text-amber-100">Este cliente aún no tiene sedes. La empresa debe crearlas en el expediente antes de armar torres, salones o apartamentos.</p>
            </div>
        @else
            <form method="GET" action="{{ route('client.structures.index') }}" class="flex flex-wrap items-center gap-3">
                <label for="installation_id" class="text-xs uppercase tracking-wide text-slate-500 shrink-0">Instalación</label>
                <select id="installation_id" name="installation_id" onchange="this.form.submit()"
                        class="min-w-0 flex-1 sm:max-w-md rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                    @foreach ($installations as $option)
                        <option value="{{ $option->id }}" @selected($installation?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </form>

            @if ($installation)
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
                    <div class="min-w-0 rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-4">{{ $installation->name }}</h3>
                        @if ($tree->isEmpty())
                            <p class="text-slate-500 text-sm">Aún no hay unidades en esta sede. Agrega la primera.</p>
                        @else
                            <x-client.structure-tree :nodes="$tree" :census="$census" :pick-parent="true" />
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 lg:sticky lg:top-20" x-ref="createForm">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-4">Agregar unidad</h3>
                        <form action="{{ route('client.structures.store') }}" method="POST" class="space-y-3">
                            @csrf
                            <input type="hidden" name="installation_id" value="{{ $installation->id }}">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1" for="structure_name">Nombre</label>
                                <input id="structure_name" type="text" name="name" x-ref="name" value="{{ old('name') }}" required placeholder="Torre A, Salón B…" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                @if ($client->structureType)
                                    <p class="mt-1 text-[11px] text-slate-500">Tipo: {{ $client->structureType->name }}</p>
                                @else
                                    <p class="mt-1 text-[11px] text-amber-400">Sin tipo en la ficha del cliente.</p>
                                @endif
                                @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1" for="parent_id">Crear dentro de</label>
                                <select id="parent_id" name="parent_id" x-model="parentId" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                    <option value="">{{ $installation->name }} (esta sede)</option>
                                    @foreach ($parentOptions as $parent)
                                        <option value="{{ $parent['id'] }}">{{ str_repeat("\u{00A0}\u{00A0}", $parent['depth'] + 1).$parent['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('parent_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">
                                Agregar unidad
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-client-layout>
