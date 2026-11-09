@props([
    'installations',
    'nodeOptions',
    'installationId' => null,
    'structureId' => null,
    'required' => true,
])

<div
    class="space-y-3"
    x-data="{
        installationId: @js($installationId !== null && $installationId !== '' ? (string) $installationId : ''),
        structureId: @js($structureId !== null && $structureId !== '' ? (string) $structureId : ''),
        nodesByInstallation: @js($nodeOptions),
        get nodes() {
            return this.nodesByInstallation[this.installationId] ?? [];
        }
    }"
>
    <div>
        <label for="census_installation_id" class="block text-xs text-slate-400 mb-1">Instalación</label>
        <select id="census_installation_id" x-model="installationId" @change="structureId = ''"
                @required($required)
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
            <option value="">Seleccione instalación</option>
            @foreach ($installations as $installation)
                <option value="{{ $installation->id }}">{{ $installation->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="structure_id" class="block text-xs text-slate-400 mb-1">Unidad</label>
        <select id="structure_id" name="structure_id" x-model="structureId" @required($required)
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
            <option value="">Seleccione unidad</option>
            <template x-for="node in nodes" :key="node.id">
                <option :value="String(node.id)" x-text="' '.repeat((node.depth + 1) * 2) + node.name"></option>
            </template>
        </select>
        <p x-show="installationId && nodes.length === 0" x-cloak class="mt-1 text-xs text-slate-500">No hay unidades en esta sede. Créalas en Mi estructura.</p>
        @error('structure_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>
</div>
