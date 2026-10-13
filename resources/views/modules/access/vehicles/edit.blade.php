<x-access-layout title="Editar Vehículo">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.vehicles.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
            <form method="POST" action="{{ route('access.vehicles.update', $vehicle) }}">
                @csrf @method('PATCH')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Placa *</label>
                        <input type="text" name="plate" value="{{ old('plate', $vehicle->plate) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                        @error('plate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Marca</label>
                        <input type="text" name="brand" value="{{ old('brand', $vehicle->brand) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Modelo</label>
                        <input type="text" name="model" value="{{ old('model', $vehicle->model) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Color</label>
                        <input type="text" name="color" value="{{ old('color', $vehicle->color) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Tipo</label>
                        <select name="type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach(['carro','moto','camion'] as $t)
                            <option value="{{ $t }}" {{ old('type', $vehicle->type) == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Visitante (propietario externo)</label>
                        <select name="visitor_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Ninguno --</option>
                            @foreach($visitors as $v)
                            <option value="{{ $v->id }}" {{ old('visitor_id', $vehicle->visitor_id) == $v->id ? 'selected' : '' }}>{{ $v->full_name }} - {{ $v->document_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Propietario (usuario interno)</label>
                        <select name="user_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Ninguno --</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('user_id', $vehicle->user_id) == $u->id ? 'selected' : '' }}>{{ $u->name }} - {{ $u->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300">Residente (propietario residente)</label>
                        <select name="resident_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Ninguno --</option>
                            @foreach($residents as $r)
                            <option value="{{ $r->id }}" {{ old('resident_id', $vehicle->resident_id) == $r->id ? 'selected' : '' }}>{{ $r->full_name }} - {{ $r->document_number }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('access.vehicles.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition-colors">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</x-access-layout>
