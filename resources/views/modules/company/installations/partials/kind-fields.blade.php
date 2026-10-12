@php
    $installation = $installation ?? null;
    $kinds = \App\Enums\InstallationKind::options();
    $selectedKind = (string) old('kind', $installation?->kind ?? '');
@endphp

<div class="grid sm:grid-cols-2 gap-3" x-data="{ kind: @js($selectedKind) }">
    <div>
        <x-ui.label for="kind">Tipo de instalación</x-ui.label>
        <select
            id="kind"
            name="kind"
            x-model="kind"
            class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30"
        >
            <option value="">Sin tipo</option>
            @foreach ($kinds as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <x-ui.field-error :messages="$errors->get('kind')" />
    </div>
    <div x-show="kind === 'colegio'" x-cloak>
        <x-ui.label for="dane_code">Código DANE de la instalación</x-ui.label>
        <input
            id="dane_code"
            type="text"
            name="dane_code"
            value="{{ old('dane_code', $installation?->dane_code) }}"
            placeholder="8 a 12 dígitos"
            inputmode="numeric"
            :disabled="kind !== 'colegio'"
            class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30"
        />
        <p class="mt-1 text-[11px] text-slate-500">Oficial y único. No sale del mapa.</p>
        <x-ui.field-error :messages="$errors->get('dane_code')" />
    </div>
</div>
