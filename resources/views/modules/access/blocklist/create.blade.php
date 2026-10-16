<x-access-layout title="Agregar a Lista de Bloqueo">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.blocklist.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Bloquear ingreso</h3>
                <p class="text-sm text-slate-500 mt-0.5">Bloquea por cédula o placa. El motivo lo ven solo los administradores; el vigilante solo verá que no puede ingresar.</p>
            </div>
            <div class="px-6 py-5">
                <form method="POST" action="{{ route('access.blocklist.store') }}" x-data="blocklistForm()">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Qué bloquear</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2.5 cursor-pointer"
                                   :class="kind === 'person' ? 'border-red-500 bg-red-950/30' : 'border-slate-700 bg-slate-950'">
                                <input type="radio" name="kind" value="person" x-model="kind" class="text-red-500 focus:ring-red-500">
                                <span class="text-sm text-white">Persona (cédula)</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2.5 cursor-pointer"
                                   :class="kind === 'vehicle' ? 'border-red-500 bg-red-950/30' : 'border-slate-700 bg-slate-950'">
                                <input type="radio" name="kind" value="vehicle" x-model="kind" class="text-red-500 focus:ring-red-500">
                                <span class="text-sm text-white">Vehículo (placa)</span>
                            </label>
                        </div>
                    </div>

                    <div class="bg-red-900/20 rounded-xl p-4 mb-6 space-y-3">
                        <div x-show="kind === 'person'">
                            <label class="block text-sm font-semibold text-slate-300 mb-2">Cédula / documento</label>
                            <input type="text" name="document_number" x-model="documentNumber" @input.debounce="searchItems()"
                                   class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="Ej: 1234567890" autocomplete="off">
                            @error('document_number') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div x-show="kind === 'vehicle'">
                            <label class="block text-sm font-semibold text-slate-300 mb-2">Placa</label>
                            <input type="text" name="plate" x-model="plate" @input.debounce="searchItems()"
                                   class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white uppercase focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="Ej: ABC123" autocomplete="off">
                            @error('plate') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Buscar en censo / fichas (opcional)</label>
                            <input type="text" x-model="search" @input.debounce="searchItems()"
                                   class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   :placeholder="kind === 'person' ? 'Nombre o documento…' : 'Placa…'">
                        </div>

                        <input type="hidden" name="blockable_type" :value="selectedType || ''">
                        <input type="hidden" name="blockable_id" :value="selectedId || ''">
                        <input type="hidden" name="display_name" :value="displayName || ''">

                        <div x-show="results.length > 0" class="bg-slate-900 border border-slate-700 rounded-xl overflow-hidden divide-y divide-slate-800">
                            <template x-for="item in results" :key="(item.blockable_type || 'x') + '-' + (item.id || item.document_number || item.plate)">
                                <button type="button" @click="selectItem(item)" class="w-full px-4 py-3 hover:bg-indigo-900/30 text-left transition-colors">
                                    <p class="text-sm font-medium text-white" x-text="item.label"></p>
                                </button>
                            </template>
                        </div>
                        <div x-show="displayName" class="p-3 bg-emerald-900/30 border border-emerald-700 rounded-xl flex items-center justify-between">
                            <p class="text-sm font-medium text-emerald-200" x-text="displayName"></p>
                            <button type="button" @click="clearSelection()" class="text-xs text-red-400 hover:text-red-300 font-medium">Quitar nombre</button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300">Motivo del bloqueo</label>
                        <textarea name="reason" rows="3" required class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ej: No pago de administración · Restricción de ingreso">{{ old('reason') }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">Visible solo para administradores. El vigilante no verá este texto.</p>
                        @error('reason') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300">Expira (opcional)</label>
                        <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-slate-500">Sin fecha = permanente hasta removerlo manualmente.</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <a href="{{ route('access.blocklist.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition-colors shadow-sm">
                            Bloquear ingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function blocklistForm() {
            return {
                kind: 'person',
                documentNumber: @js(old('document_number', '')),
                plate: @js(old('plate', '')),
                search: '',
                results: [],
                selectedId: null,
                selectedType: null,
                displayName: '',
                async searchItems() {
                    const q = this.search || (this.kind === 'person' ? this.documentNumber : this.plate);
                    if (!q || q.length < 2) { this.results = []; return; }
                    try {
                        const res = await fetch('{{ route("access.blocklist.search") }}?type=' + this.kind + '&q=' + encodeURIComponent(q));
                        this.results = await res.json();
                    } catch (e) { this.results = []; }
                },
                selectItem(item) {
                    this.displayName = item.display_name || '';
                    this.selectedId = item.id || null;
                    this.selectedType = item.blockable_type || null;
                    if (this.kind === 'person' && item.document_number) {
                        this.documentNumber = item.document_number;
                    }
                    if (this.kind === 'vehicle' && item.plate) {
                        this.plate = item.plate;
                    }
                    this.results = [];
                    this.search = '';
                },
                clearSelection() {
                    this.displayName = '';
                    this.selectedId = null;
                    this.selectedType = null;
                }
            }
        }
    </script>
    @endpush
</x-access-layout>
