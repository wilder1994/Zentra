<x-access-layout title="Nueva Torre / Bloque">
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-slate-900 rounded-lg border border-slate-800 p-6">
                <form method="POST" action="{{ route('access.buildings.store') }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Código</label>
                            <input type="text" name="code" value="{{ old('code') }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required placeholder="TORRE-1">
                            @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Nombre</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required placeholder="Torre 1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Tipo</label>
                            <select name="type" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="torre">Torre</option>
                                <option value="bloque">Bloque</option>
                                <option value="casa_independiente">Casa Independiente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Portería (ubicación)</label>
                            <select name="location_id" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Dirección</label>
                            <input type="text" name="address" value="{{ old('address') }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Activo</label>
                            <select name="is_active" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="{{ route('access.buildings.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-access-layout>
