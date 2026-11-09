@props([
    'installations',
    'nodeOptions',
    'installationId' => null,
    'structureId' => null,
])

<div class="flex flex-wrap items-end gap-3">
    <form method="GET" class="flex flex-wrap items-end gap-3 min-w-0 flex-1">
        {{ $slot }}
        <div>
            <label for="installation_id" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Instalación</label>
            <select id="installation_id" name="installation_id"
                    onchange="const s=this.form.querySelector('[name=structure_id]'); if(s) s.value=''; this.form.submit();"
                    class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-white min-w-[12rem]">
                <option value="">Todas</option>
                @foreach ($installations as $installation)
                    <option value="{{ $installation->id }}" @selected((string) $installationId === (string) $installation->id)>{{ $installation->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($installationId)
            <div>
                <label for="structure_id" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Unidad</label>
                <select id="structure_id" name="structure_id" onchange="this.form.submit()"
                        class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-white min-w-[12rem]">
                    <option value="">Todas las unidades</option>
                    @foreach (($nodeOptions[(string) $installationId] ?? []) as $node)
                        <option value="{{ $node['id'] }}" @selected((string) $structureId === (string) $node['id'])>{{ str_repeat("\u{00A0}\u{00A0}", $node['depth']).$node['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Filtrar</button>
    </form>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 ml-auto">
            {{ $actions }}
        </div>
    @endisset
</div>
