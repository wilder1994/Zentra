<x-access-layout title="Agregar a Lista de Bloqueo">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.blocklist.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Bloquear Ingreso</h3>
                <p class="text-sm text-slate-500 mt-0.5">Restrinja el acceso a una persona o vehículo</p>
            </div>
            <div class="px-6 py-5">
                <form method="POST" action="{{ route('access.blocklist.store') }}" x-data="blocklistForm()">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Tipo</label>
                        <select x-model="type" name="blockable_type" required class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="bg-red-900/20 rounded-xl p-4 mb-6">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Buscar</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="search" @input.debounce="searchItems()" placeholder="Buscar por nombre, documento o placa..." class="block w-full pl-10 rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <input type="hidden" name="blockable_id" x-model="selectedId">
                        <div x-show="results.length > 0 && !selectedId" class="mt-2 bg-slate-900 border border-slate-700 rounded-xl overflow-hidden divide-y divide-slate-800">
                            <template x-for="item in results" :key="item.id">
                                <div @click="selectItem(item)" class="px-4 py-3 hover:bg-indigo-900/30 cursor-pointer flex items-center justify-between transition-colors">
                                    <div>
                                        <p class="text-sm font-medium text-white" x-text="item.document_number || item.plate || item.first_name"></p>
                                        <p class="text-xs text-slate-500" x-text="item.first_name ? ' ' + item.last_name : (item.brand ? ' ' + item.brand + ' ' + item.model : '')"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="selectedItem" class="mt-3 p-3 bg-emerald-900/30 border border-emerald-700 rounded-xl flex items-center justify-between">
                            <p class="text-sm font-medium text-emerald-200" x-text="selectedLabel"></p>
                            <button type="button" @click="clearSelection()" class="text-xs text-red-400 hover:text-red-300 font-medium">Cambiar</button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300">Razón del bloqueo</label>
                        <textarea name="reason" rows="3" required class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ej: Ingreso denegado por comportamiento inapropiado"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300">Expira (opcional)</label>
                        <input type="datetime-local" name="expires_at" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-slate-500">Si no se especifica fecha, el bloqueo será permanente hasta que se remueva manualmente.</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <a href="{{ route('access.blocklist.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Bloquear Ingreso
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
                type: 'visitor',
                search: '',
                results: [],
                selectedId: null,
                selectedItem: null,
                get selectedLabel() {
                    if (!this.selectedItem) return '';
                    return this.selectedItem.document_number || this.selectedItem.plate || (this.selectedItem.first_name + ' ' + this.selectedItem.last_name);
                },
                async searchItems() {
                    if (this.search.length < 2) { this.results = []; return; }
                    try {
                        const res = await fetch('{{ route("access.blocklist.search") }}?type=' + this.type + '&q=' + encodeURIComponent(this.search));
                        this.results = await res.json();
                    } catch(e) { this.results = []; }
                },
                selectItem(item) {
                    this.selectedItem = item;
                    this.selectedId = item.id;
                    this.search = this.selectedLabel;
                    this.results = [];
                },
                clearSelection() {
                    this.selectedItem = null;
                    this.selectedId = null;
                    this.search = '';
                    this.results = [];
                }
            }
        }
    </script>
    @endpush
</x-access-layout>