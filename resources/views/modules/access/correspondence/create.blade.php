<x-access-layout title="Registrar correspondencia">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.correspondence.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    @if($errors->any())
        <div class="mb-4 max-w-2xl rounded-lg border border-red-700 bg-red-900/40 px-4 py-3 text-sm text-red-200">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('access.correspondence.store') }}"
          class="porteria-form max-w-2xl space-y-5 rounded-xl border border-slate-800 bg-slate-900 p-5"
          x-data="correspondenceForm({
              membersUrl: @js(route('access.correspondence.members')),
              structureId: @js((string) old('structure_id', '')),
              memberId: @js((string) old('structure_member_id', '')),
              mode: @js(old('recipient_text') && ! old('structure_id') ? 'other' : 'unit'),
              type: @js(old('package_type', 'caja')),
          })" x-init="boot()">
        @csrf

        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Destinatario</p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="mode = 'unit'"
                        :class="mode === 'unit' ? 'bg-indigo-600 text-white border-transparent' : 'bg-slate-950 text-slate-300 border-slate-700'"
                        class="rounded-lg border px-3 py-2 text-left text-sm font-medium">Unidad del censo</button>
                <button type="button" @click="mode = 'other'"
                        :class="mode === 'other' ? 'bg-indigo-600 text-white border-transparent' : 'bg-slate-950 text-slate-300 border-slate-700'"
                        class="rounded-lg border px-3 py-2 text-left text-sm font-medium">Administración / otro</button>
            </div>

            <template x-if="mode === 'unit'">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block text-xs text-slate-400">Unidad (apartamento / casa) <span class="text-amber-400">*</span>
                        <select name="structure_id" x-model="structureId" @change="loadMembers()" required
                                class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
                            <option value="">Seleccionar</option>
                            @foreach($nodes as $node)
                                <option value="{{ $node->id }}">{{ $node->name }}@if($node->structureType) · {{ $node->structureType->name }}@endif</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-xs text-slate-400">Para (opcional)
                        <select name="structure_member_id" x-model="memberId" :disabled="!structureId"
                                class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white disabled:opacity-50">
                            <option value="">Toda la unidad</option>
                            <template x-for="m in members" :key="m.id">
                                <option :value="String(m.id)" x-text="m.name" :selected="String(m.id) === memberId"></option>
                            </template>
                        </select>
                    </label>
                </div>
            </template>

            <template x-if="mode === 'other'">
                <label class="block text-xs text-slate-400">Destinatario <span class="text-amber-400">*</span>
                    <input name="recipient_text" value="{{ old('recipient_text') }}" required maxlength="150"
                           class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white"
                           placeholder="Administración, consejo, zona común, persona externa…">
                </label>
            </template>
        </div>

        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Paquete</p>
            <input type="hidden" name="package_type" :value="type">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach(\App\Models\Correspondence::TYPES as $value => $label)
                    <button type="button" @click="type = '{{ $value }}'"
                            :class="type === '{{ $value }}' ? 'bg-indigo-600 text-white border-transparent' : 'bg-slate-950 text-slate-300 border-slate-700'"
                            class="rounded-lg border px-3 py-2 text-sm font-medium">{{ $label }}</button>
                @endforeach
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label class="block text-xs text-slate-400 sm:col-span-1">Transportadora
                    <input name="carrier" list="carrier-list" value="{{ old('carrier') }}" maxlength="100"
                           class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white" placeholder="Servientrega, Envía…">
                    <datalist id="carrier-list">
                        @foreach($carriers as $carrier)<option value="{{ $carrier }}">@endforeach
                    </datalist>
                </label>
                <label class="block text-xs text-slate-400">Número de guía
                    <input name="courier_guide" value="{{ old('courier_guide') }}" maxlength="100"
                           class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
                </label>
                <label class="block text-xs text-slate-400">Cantidad
                    <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="20"
                           class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
                </label>
            </div>
            <label class="block text-xs text-slate-400">Notas
                <textarea name="notes" rows="2" maxlength="1000"
                          class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white"
                          placeholder="Estado del paquete, observaciones…">{{ old('notes') }}</textarea>
            </label>
            @include('modules.access.partials.camera-capture', [
                'name' => 'package_photo_data',
                'label' => 'Foto del paquete',
                'required' => false,
                'hint' => 'Opcional · sirve como evidencia del estado al recibirlo',
                'facing' => 'environment',
            ])
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-800 pt-4">
            <a href="{{ route('access.correspondence.index') }}" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-slate-200">Cancelar</a>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Registrar</button>
        </div>
    </form>

    @push('scripts')
        <script>
            function correspondenceForm(opts) {
                return {
                    membersUrl: opts.membersUrl,
                    structureId: opts.structureId,
                    memberId: opts.memberId,
                    mode: opts.mode,
                    type: opts.type,
                    members: [],
                    boot() { if (this.structureId) this.loadMembers(true); },
                    loadMembers(keep = false) {
                        if (!keep) this.memberId = '';
                        if (!this.structureId) { this.members = []; return; }
                        fetch(this.membersUrl + '?structure_id=' + this.structureId, { headers: { Accept: 'application/json' } })
                            .then(r => r.json())
                            .then(d => { this.members = d.members || []; });
                    },
                };
            }
        </script>
    @endpush
</x-access-layout>
