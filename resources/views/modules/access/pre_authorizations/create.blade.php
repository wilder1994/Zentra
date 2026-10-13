<x-access-layout title="Nueva Pre-Autorización">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.pre_authorizations.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Autorizar Ingreso</h3>
                <p class="text-sm text-slate-500 mt-0.5">Autorice el ingreso anticipado de un visitante</p>
            </div>
            <div class="px-6 py-5">
                <form method="POST" action="{{ route('access.pre_authorizations.store') }}" x-data="entryForm()">
                    @csrf

                    <div class="bg-purple-900/30 rounded-xl p-4 mb-6">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Visitante</label>

                        <div class="mb-3 p-3 bg-slate-950/50 border border-slate-700/50 rounded-lg">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Escáner QR Cédula</label>
                            @include('modules.access.partials.qr-scan-field')
                        </div>

                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="search" @input.debounce="searchVisitor()" placeholder="Buscar por documento o nombre..." class="block w-full pl-10 rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <input type="hidden" name="visitor_id" x-model="selectedVisitorId">
                        <div x-show="searchResults.length > 0 && !selectedVisitorId" class="mt-2 bg-slate-950 border border-slate-700 rounded-xl shadow-sm overflow-hidden divide-y divide-slate-800">
                            <template x-for="v in searchResults" :key="v.id">
                                <div @click="selectVisitor(v)" class="px-4 py-3 hover:bg-indigo-900/30 cursor-pointer flex items-center justify-between transition-colors">
                                    <div>
                                        <p class="text-sm font-medium text-white" x-text="v.first_name + ' ' + v.last_name"></p>
                                        <p class="text-xs text-slate-500" x-text="v.document_type + ' ' + v.document_number"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="selectedVisitor" class="mt-3 p-3 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 rounded-xl flex items-center justify-between">
                            <p class="text-sm font-medium text-emerald-200" x-text="selectedVisitorLabel"></p>
                            <button type="button" @click="clearSelection()" class="text-xs text-red-600 hover:text-red-800 font-medium">Cambiar</button>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('access.visitors.create') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Crear nuevo visitante
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Ubicación</label>
                            <select name="location_id" required class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Fecha Programada</label>
                            <input type="date" name="scheduled_date" value="{{ old('scheduled_date', date('Y-m-d')) }}" min="{{ date('Y-m-d') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                        </div>
<div>
                            <label class="block text-sm font-medium text-slate-300">Hora (opcional)</label>
                            <input type="time" name="scheduled_time" value="{{ old('scheduled_time') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div class="mt-4" x-data="{ recurrence: '{{ old('recurrence', 'puntual') }}' }">
                        <label class="block text-sm font-medium text-slate-300">Recurrencia</label>
                        <select name="recurrence" x-model="recurrence" class="mt-1 block w-full max-w-xs rounded-lg bg-slate-950 border-slate-700 text-white focus:border-purple-500 focus:ring-purple-500">
                            <option value="puntual">Puntual (una sola vez)</option>
                            <option value="diario">Diaria</option>
                            <option value="semanal">Semanal</option>
                            <option value="bisemanal">Bisemanal</option>
                            <option value="mensual">Mensual</option>
                        </select>
                        <div x-show="recurrence !== 'puntual'" x-cloak class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Válido hasta</label>
                                <input type="date" name="valid_until" value="{{ old('valid_until') }}" min="{{ date('Y-m-d') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-purple-500 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Ingresos por día</label>
                                <input type="number" name="entries_per_day" value="{{ old('entries_per_day') }}" min="1" max="99" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-purple-500 focus:ring-purple-500" placeholder="1">
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-300">Notas</label>
                        <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Información adicional para el guardia...">{{ old('notes') }}</textarea>
                    </div>
                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <a href="{{ route('access.pre_authorizations.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Crear Pre-Autorización
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function entryForm() {
            return {
                scanBuffer: '',
                search: '',
                searchResults: [],
                selectedVisitorId: null,
                selectedVisitor: null,
                get selectedVisitorLabel() {
                    if (!this.selectedVisitor) return '';
                    return this.selectedVisitor.document_type + ' ' + this.selectedVisitor.document_number + ' - ' + this.selectedVisitor.first_name + ' ' + this.selectedVisitor.last_name;
                },
                async searchVisitor() {
                    if (this.search.length < 2) { this.searchResults = []; return; }
                    try {
                        const res = await fetch('{{ route("access.visitors.search.json") }}?q=' + encodeURIComponent(this.search));
                        this.searchResults = await res.json();
                    } catch(e) { this.searchResults = []; }
                },
                selectVisitor(v) {
                    this.selectedVisitor = v;
                    this.selectedVisitorId = v.id;
                    this.search = v.document_number + ' - ' + v.first_name + ' ' + v.last_name;
                    this.searchResults = [];
                },
                clearSelection() {
                    this.selectedVisitor = null;
                    this.selectedVisitorId = null;
                    this.search = '';
                    this.searchResults = [];
                },
                async handleScan() {
                    let parts = this.scanBuffer.trim().split(/[|\t]/);
                    if (parts.length < 5) return;
                    let numero = parts[0], apellido1 = parts[1] || '', apellido2 = parts[2] || '';
                    let nombre1 = parts[3] || '', nombre2 = parts[4] || '';
                    let nombreCompleto = (nombre1 + ' ' + nombre2).trim();
                    let apellidoCompleto = (apellido1 + ' ' + apellido2).trim();
                    this.scanBuffer = '';
                    try {
                        const res = await fetch('{{ route("access.visitors.scan-register") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ document_number: numero, first_name: nombreCompleto, last_name: apellidoCompleto })
                        });
                        const data = await res.json();
                        this.selectVisitor(data.visitor);
                    } catch(e) {}
                }
            }
        }
    </script>
    @endpush
</x-access-layout>
