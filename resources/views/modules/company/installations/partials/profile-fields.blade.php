@php
    $installation = $installation ?? null;
    $profiles = $installationProfiles ?? [];
    $selectedProfileId = (int) old('installation_profile_id', $installation?->installation_profile_id ?? 0);
    $clientStructureLabel = $clientStructureLabel ?? null;
@endphp

<div
    class="space-y-2"
    x-data="{
        profileId: @js((string) $selectedProfileId),
        profiles: @js($profiles),
        get selected() {
            return this.profiles.find((row) => String(row.id) === String(this.profileId)) || null
        },
        get requiresDane() {
            return Boolean(this.selected?.requires_dane)
        },
        get hint() {
            if (! this.selected) return 'Opcional. Define reglas del sitio (DANE, Observatorio).'
            const bits = []
            if (this.selected.requires_dane) bits.push('exige código DANE')
            if (this.selected.observatory_community) bits.push('aparece en Observatorio comunidad')
            return bits.length ? bits.join(' · ') : 'Sin reglas especiales.'
        }
    }"
>
    @if ($clientStructureLabel)
        <p class="text-[11px] text-slate-500">
            Clasificación del cliente: <span class="text-slate-300">{{ $clientStructureLabel }}</span>
            <span class="text-slate-600">(estructura del censo; no es el perfil del sitio)</span>
        </p>
    @endif

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <x-ui.label for="installation_profile_id">Perfil de sitio</x-ui.label>
            <select
                id="installation_profile_id"
                name="installation_profile_id"
                x-model="profileId"
                class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30"
            >
                <option value="">Sin perfil</option>
                @foreach ($profiles as $profile)
                    <option value="{{ $profile['id'] }}">{{ $profile['name'] }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-slate-500" x-text="hint"></p>
            <x-ui.field-error :messages="$errors->get('installation_profile_id')" />
        </div>
        <div x-show="requiresDane" x-cloak>
            <x-ui.label for="dane_code">Código DANE de la instalación</x-ui.label>
            <input
                id="dane_code"
                type="text"
                name="dane_code"
                value="{{ old('dane_code', $installation?->dane_code) }}"
                placeholder="8 a 12 dígitos"
                inputmode="numeric"
                :disabled="!requiresDane"
                class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30"
            />
            <p class="mt-1 text-[11px] text-slate-500">Oficial y único. No sale del mapa.</p>
            <x-ui.field-error :messages="$errors->get('dane_code')" />
        </div>
    </div>
</div>
