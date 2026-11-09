@php
    use App\Support\Privacy\MinorPersonalData;
    $member = $member ?? null;
    $birth = old('birth_date', $member?->birth_date?->format('Y-m-d'));
    $alreadyAccepted = $member?->minor_treatment_accepted_at !== null;
@endphp

<div class="grid sm:grid-cols-2 gap-4" x-data="{
    birth: @js($birth),
    accepted: {{ $alreadyAccepted ? 'true' : 'false' }},
    age() {
        if (! this.birth) return null;
        const d = new Date(this.birth + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return null;
        const now = new Date();
        let years = now.getFullYear() - d.getFullYear();
        const m = now.getMonth() - d.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < d.getDate())) years--;
        return years;
    },
    get minor() {
        const a = this.age();
        return a !== null && a < {{ MinorPersonalData::AGE_OF_MAJORITY }};
    }
}">
    <div>
        <label class="block text-xs text-slate-400 mb-1">Tipo de documento</label>
        <select name="document_type" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
            <option value="">Seleccione…</option>
            @foreach ($documentTypes as $code => $label)
                <option value="{{ $code }}" @selected((string) old('document_type', $member?->document_type) === (string) $code)>{{ $label }}</option>
            @endforeach
        </select>
        @error('document_type')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs text-slate-400 mb-1">Número de documento</label>
        <input type="text" name="document_number" value="{{ old('document_number', $member?->document_number) }}" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
        @error('document_number')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs text-slate-400 mb-1">Fecha de nacimiento</label>
        <input type="date" name="birth_date" x-model="birth" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
        <p class="mt-1 text-xs text-slate-500" x-show="age() !== null" x-cloak>Edad: <span x-text="age()"></span> años</p>
        @error('birth_date')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2 rounded-lg border border-amber-800/70 bg-amber-950/30 p-3 space-y-2" x-show="minor" x-cloak>
        <p class="text-xs text-amber-200 leading-relaxed whitespace-pre-line">{{ MinorPersonalData::notice() }}</p>
        <label class="flex items-start gap-2 text-xs text-amber-100">
            <input type="checkbox" name="minor_treatment_accepted" value="1" class="mt-0.5 rounded border-amber-600 bg-slate-950 text-teal-600" x-bind:required="minor && !accepted" @checked(old('minor_treatment_accepted', $alreadyAccepted))>
            <span>El representante legal autoriza el tratamiento de estos datos conforme a la norma citada.</span>
        </label>
        @error('minor_treatment_accepted')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
    </div>
    @php($member = $member ?? null)
    <p class="sm:col-span-2 text-xs text-slate-500" x-show="!minor">
        El acceso a la app se gestiona en
        <a href="{{ route('client.app-users.index') }}" class="text-teal-400 hover:text-teal-300">Usuarios app</a>.
        @if ($member?->appUser)
            · <a href="{{ route('client.app-users.show', $member->appUser) }}" class="text-teal-400 hover:text-teal-300">Ver cuenta</a>
        @endif
    </p>
    <p class="sm:col-span-2 text-xs text-slate-500" x-show="minor" x-cloak>Los menores no tienen usuario app ni se incluyen en descargas.</p>
</div>
