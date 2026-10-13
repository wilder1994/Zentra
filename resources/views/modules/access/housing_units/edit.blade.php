<x-access-layout title="Editar Apartamento / Casa">
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-slate-900 rounded-lg border border-slate-800 p-6">
                <form method="POST" action="{{ route('access.housing_units.update', $housingUnit) }}">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Torre/Bloque</label>
                            <select name="building_id" required class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                @foreach($buildings as $b)
                                <option value="{{ $b->id }}" {{ old('building_id', $housingUnit->building_id) == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ $b->code }})</option>
                                @endforeach
                            </select>
                            @error('building_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Número</label>
                            <input type="text" name="unit_number" value="{{ old('unit_number', $housingUnit->unit_number) }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                            @error('unit_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Piso</label>
                            <input type="text" name="floor" value="{{ old('floor', $housingUnit->floor) }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Tipo</label>
                            <select name="type" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="apartamento" {{ $housingUnit->type == 'apartamento' ? 'selected' : '' }}>Apartamento</option>
                                <option value="casa" {{ $housingUnit->type == 'casa' ? 'selected' : '' }}>Casa</option>
                                <option value="local_comercial" {{ $housingUnit->type == 'local_comercial' ? 'selected' : '' }}>Local Comercial</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Activo</label>
                            <select name="is_active" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="1" {{ old('is_active', $housingUnit->is_active) ? 'selected' : '' }}>Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-300">Notas</label>
                        <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $housingUnit->notes) }}</textarea>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="{{ route('access.housing_units.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-access-layout>
