<x-access-layout title="Editar Residente">
    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
        <form method="POST" action="{{ route('access.residents.update', $resident) }}" x-data="residentForm()">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300">Tipo Documento</label>
                    <select name="document_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['CC','CE','Pasaporte','NIT'] as $dt)
                        <option value="{{ $dt }}" {{ old('document_type', $resident->document_type) == $dt ? 'selected' : '' }}>{{ $dt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Número Documento</label>
                    <input type="text" name="document_number" value="{{ old('document_number', $resident->document_number) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                    @error('document_number') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Nombres</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $resident->first_name) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Apellidos</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $resident->last_name) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Fecha Nacimiento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $resident->birth_date) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Tipo Sangre</label>
                    <input type="text" name="blood_type" value="{{ old('blood_type', $resident->blood_type) }}" placeholder="A+, O-, etc." class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Teléfono</label>
                    <input type="text" name="phone" value="{{ old('phone', $resident->phone) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Email</label>
                    <input type="email" name="email" value="{{ old('email', $resident->email) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Tipo Residente</label>
                    <select name="resident_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="propietario" {{ $resident->resident_type == 'propietario' ? 'selected' : '' }}>Propietario</option>
                        <option value="inquilino" {{ $resident->resident_type == 'inquilino' ? 'selected' : '' }}>Inquilino</option>
                        <option value="familiar" {{ $resident->resident_type == 'familiar' ? 'selected' : '' }}>Familiar</option>
                        <option value="empleado_domestico" {{ $resident->resident_type == 'empleado_domestico' ? 'selected' : '' }}>Empleado Doméstico</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Activo</label>
                    <select name="is_active" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1" {{ old('is_active', $resident->is_active) ? 'selected' : '' }}>Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800">
                <h3 class="text-lg font-semibold text-white mb-3">Asignación de Vivienda</h3>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-slate-300">Seleccionar Apartamento(s)</label>
                    <select name="housing_units[]" multiple x-model="selectedUnitIds" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" size="5">
                        <template x-for="unit in housingUnits" :key="unit.id">
                            <option :value="unit.id" x-text="unit.building_name + ' - ' + unit.unit_number + ' (' + unit.type + ')'"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Vivienda Principal</label>
                    <select name="primary_unit" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Ninguna</option>
                        <template x-for="unit in selectedUnits" :key="unit.id">
                            <option :value="unit.id" x-text="unit.building_name + ' - ' + unit.unit_number" :selected="String(unit.id) === primaryUnitId"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-300">Notas</label>
                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $resident->notes) }}</textarea>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <a href="{{ route('access.residents.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Actualizar</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function residentForm() {
            return {
                housingUnits: @json($housingUnitsData),
                selectedUnitIds: @json($residentUnitIds),
                primaryUnitId: @json($primaryUnitId),
                get selectedUnits() {
                    return this.housingUnits.filter(u => this.selectedUnitIds.includes(String(u.id)));
                }
            }
        }
    </script>
    @endpush
</x-access-layout>
