@php
    use App\Models\StructureAppUser;
    $prefillInstallation = $prefillMember?->structure?->installation_id;
    $prefillStructure = $prefillMember?->structure_id;
@endphp

<x-client-layout title="Crear usuario app">
    <form
        action="{{ route('client.app-users.store') }}"
        method="POST"
        class="max-w-xl space-y-4 rounded-xl border border-slate-800 bg-slate-900 p-5"
        x-data="{
            installationId: @js((string) old('installation_id', $prefillInstallation ?? '')),
            structureId: @js((string) old('structure_id', $prefillStructure ?? '')),
            memberId: @js((string) old('member_id', $prefillMember?->id ?? '')),
            nodesByInstallation: @js($nodeOptions),
            membersByStructure: @js($membersByStructure),
            get nodes() { return this.nodesByInstallation[this.installationId] ?? []; },
            get members() { return this.membersByStructure[this.structureId] ?? []; },
            selectedMember() { return this.members.find(m => String(m.id) === String(this.memberId)); },
        }"
    >
        @csrf
        <div>
            <a href="{{ route('client.app-users.index') }}" class="text-sm text-slate-400 hover:text-white">← Usuarios app</a>
            <h2 class="text-lg font-semibold text-white mt-2">Crear usuario app</h2>
            <p class="text-xs text-slate-400 mt-1">Se genera usuario y contraseña; se envían al correo de la persona. Máx. {{ $maxPerStructure }} por unidad.</p>
        </div>

        <div>
            <label class="block text-xs text-slate-400 mb-1" for="installation_id">Sede</label>
            <select id="installation_id" x-model="installationId" @change="structureId = ''; memberId = ''"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white" required>
                <option value="">Seleccione sede</option>
                @foreach ($installations as $installation)
                    <option value="{{ $installation->id }}">{{ $installation->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="installationId" x-cloak>
            <label class="block text-xs text-slate-400 mb-1" for="structure_id">Unidad</label>
            <select id="structure_id" x-model="structureId" @change="memberId = ''"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white" required>
                <option value="">Seleccione unidad</option>
                <template x-for="node in nodes" :key="node.id">
                    <option :value="String(node.id)" x-text="' '.repeat((node.depth + 1) * 2) + node.name"></option>
                </template>
            </select>
            <p x-show="installationId && nodes.length === 0" class="mt-1 text-xs text-amber-400">No hay unidades en esta sede. Créalas en Mi estructura.</p>
        </div>

        <div x-show="structureId" x-cloak>
            <label class="block text-xs text-slate-400 mb-1" for="member_id">Persona</label>
            <select id="member_id" name="member_id" x-model="memberId"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white" required>
                <option value="">Seleccione persona</option>
                <template x-for="m in members" :key="m.id">
                    <option :value="String(m.id)" x-text="m.name + (m.email ? ' · ' + m.email : ' · sin correo')"></option>
                </template>
            </select>
            <p x-show="structureId && members.length === 0" class="mt-1 text-xs text-amber-400">No hay personas adultas disponibles en esta unidad (o ya tienen usuario app).</p>
            <p x-show="selectedMember() && !selectedMember().email" class="mt-1 text-xs text-amber-400">Esta persona no tiene correo. Edítala en Personas antes de crear el usuario.</p>
            @error('member_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        <fieldset class="space-y-2">
            <legend class="text-xs text-slate-400 mb-1">Canal del dispositivo</legend>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="radio" name="delivery_channel" value="{{ StructureAppUser::CHANNEL_ANDROID }}"
                       @checked(old('delivery_channel', StructureAppUser::CHANNEL_ANDROID) === StructureAppUser::CHANNEL_ANDROID)
                       class="border-slate-600 bg-slate-950 text-teal-600">
                Android — APK (Descargas)
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="radio" name="delivery_channel" value="{{ StructureAppUser::CHANNEL_IOS_WEB }}"
                       @checked(old('delivery_channel') === StructureAppUser::CHANNEL_IOS_WEB)
                       class="border-slate-600 bg-slate-950 text-teal-600">
                iPhone — enlace web
            </label>
            @error('delivery_channel')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </fieldset>

        @if ($client)
            <p class="text-[11px] text-slate-500">El login quedará como <span class="font-mono text-slate-300">{{ 'nombre.apellido@'.$client->login_suffix }}</span> (ajustado si hay duplicado).</p>
        @endif

        <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">
            Crear y enviar credenciales
        </button>
    </form>
</x-client-layout>
