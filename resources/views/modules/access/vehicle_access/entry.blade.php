<x-access-layout title="Registrar Ingreso Vehicular">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.vehicle_access.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Ingreso de Vehículo</h3>
                <p class="text-sm text-slate-500 mt-0.5">Busque la placa del vehículo para registrar su ingreso</p>
            </div>
            <div class="px-6 py-5">
                <form method="POST" action="{{ route('access.vehicle_access.entry.store') }}" x-data="vehicleForm()">
                    @csrf

                    <div class="bg-cyan-900/30 rounded-xl p-4 mb-6">
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Buscar Vehículo por Placa</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="search" @input.debounce="searchVehicle()" placeholder="Ingrese la placa..." class="block w-full pl-10 rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <input type="hidden" name="vehicle_id" x-model="selectedVehicleId">
                        <input type="hidden" name="user_id" x-model="selectedUserId">
                        <input type="hidden" name="resident_id" x-model="selectedResidentId">
                        <div x-show="searchResults.length > 0 && !selectedVehicleId" class="mt-2 bg-slate-950 border border-slate-700 rounded-xl shadow-sm overflow-hidden divide-y divide-slate-800">
                            <template x-for="v in searchResults" :key="v.id">
                                <div @click="selectVehicle(v)" class="px-4 py-3 hover:bg-cyan-900/30 cursor-pointer flex items-center justify-between transition-colors">
                                    <div>
                                        <p class="text-sm font-semibold text-white" x-text="v.plate"></p>
                                        <p class="text-xs text-slate-500" x-text="v.brand + ' ' + v.model + ' (' + v.color + ')'"></p>
                                    </div>
                                    <div class="text-right text-xs text-slate-500">
                                        <p x-show="v.owner" x-text="v.owner.name"></p>
                                        <p x-show="v.resident" x-text="v.resident.first_name + ' ' + v.resident.last_name"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="selectedVehicle" class="mt-3 p-3 bg-cyan-900/30 border border-cyan-700 rounded-xl flex items-center justify-between">
                            <p class="text-sm font-medium text-cyan-300" x-text="selectedVehicleLabel"></p>
                            <button type="button" @click="clearSelection()" class="text-xs text-red-600 hover:text-red-800 font-medium">Cambiar</button>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('access.vehicles.create') }}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Registrar nuevo vehículo
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
                            <label class="block text-sm font-medium text-slate-300">Propósito</label>
                            <input type="text" name="purpose" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ingreso a parqueadero, carga, etc.">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-300">Notas</label>
                        <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Observaciones adicionales..."></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <a href="{{ route('access.vehicle_access.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Registrar Ingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function vehicleForm() {
            return {
                search: '',
                searchResults: [],
                selectedVehicleId: null,
                selectedUserId: null,
                selectedResidentId: null,
                selectedVehicle: null,
                get selectedVehicleLabel() {
                    if (!this.selectedVehicle) return '';
                    let label = this.selectedVehicle.plate;
                    if (this.selectedVehicle.owner) label += ' - ' + this.selectedVehicle.owner.name;
                    else if (this.selectedVehicle.resident) label += ' - ' + this.selectedVehicle.resident.first_name + ' ' + this.selectedVehicle.resident.last_name;
                    return label;
                },
                async searchVehicle() {
                    if (this.search.length < 1) { this.searchResults = []; return; }
                    try {
                        const res = await fetch('{{ route("access.vehicle_access.search") }}?q=' + encodeURIComponent(this.search));
                        this.searchResults = await res.json();
                    } catch(e) { this.searchResults = []; }
                },
                selectVehicle(v) {
                    this.selectedVehicle = v;
                    this.selectedVehicleId = v.id;
                    this.selectedUserId = v.user_id;
                    this.selectedResidentId = v.resident_id;
                    let ownerName = v.owner ? v.owner.name : (v.resident ? v.resident.first_name + ' ' + v.resident.last_name : '');
                    this.search = v.plate + (ownerName ? ' - ' + ownerName : '');
                    this.searchResults = [];
                },
                clearSelection() {
                    this.selectedVehicle = null;
                    this.selectedVehicleId = null;
                    this.selectedUserId = null;
                    this.selectedResidentId = null;
                    this.search = '';
                    this.searchResults = [];
                }
            }
        }
    </script>
    @endpush
</x-access-layout>
