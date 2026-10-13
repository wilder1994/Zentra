@php
    $selectedTypeId = (string) ($selected ?? '');
    $canCreateType = auth()->user()?->can('create', App\Models\MemberType::class) ?? false;
@endphp
<div x-data="memberTypeSelect({
        types: @js($memberTypes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()),
        selected: @js($selectedTypeId),
        url: @js(route('client.settings.member-types.store')),
    })">
    <div class="flex items-center justify-between mb-1">
        <label class="block text-xs text-slate-400">Tipo</label>
        @if ($canCreateType)
            <button type="button" @click="openNew()" class="text-xs font-semibold text-teal-400 hover:text-teal-300">+ Nuevo tipo</button>
        @endif
    </div>

    <select name="member_type_id" x-model="selected" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
        <option value="">Seleccione tipo</option>
        <template x-for="t in types" :key="t.id">
            <option :value="String(t.id)" x-text="t.name" :selected="String(t.id) === selected"></option>
        </template>
    </select>
    <p x-show="types.length === 0" class="mt-1 text-xs text-amber-300">No hay tipos todavía. Usa «+ Nuevo tipo».</p>
    @error('member_type_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror

    @if ($canCreateType)
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                 @click.self="open = false" @keydown.escape.window="open = false">
                <div class="w-full max-w-sm rounded-xl border border-slate-800 bg-slate-900 p-4 shadow-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-white">Nuevo tipo de persona</p>
                        <button type="button" @click="open = false" class="text-slate-400 hover:text-white text-xl leading-none" aria-label="Cerrar">&times;</button>
                    </div>
                    <input type="text" x-model="newName" x-ref="newName" @keydown.enter.prevent="save()"
                           placeholder="Ej. Propietario, Inquilino, Funcionario"
                           class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                    <p x-show="error" x-text="error" class="text-xs text-red-400"></p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm text-slate-300">Cancelar</button>
                        <button type="button" @click="save()" :disabled="saving || !newName.trim()"
                                class="rounded-lg bg-teal-600 px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50">Crear y usar</button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
@once
@push('scripts')
<script>
function memberTypeSelect(cfg) {
    return {
        types: cfg.types,
        selected: cfg.selected,
        url: cfg.url,
        open: false,
        newName: '',
        error: '',
        saving: false,
        openNew() {
            this.open = true;
            this.newName = '';
            this.error = '';
            this.$nextTick(() => this.$refs.newName?.focus());
        },
        async save() {
            const name = this.newName.trim();
            if (!name || this.saving) return;
            this.saving = true;
            this.error = '';
            try {
                const res = await fetch(this.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({ name, is_active: true }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.error = data.errors?.name?.[0] || data.message || 'No se pudo crear el tipo.';
                    return;
                }
                this.types.push({ id: data.id, name: data.name });
                this.selected = String(data.id);
                this.open = false;
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endonce
