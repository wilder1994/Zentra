<x-access-layout title="Editar punto de acceso">
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-slate-900 rounded-lg border border-slate-800 p-6">
                <form method="POST" action="{{ route('access.locations.update', $location) }}">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-1 gap-4" x-data="geoCapture()">
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Instalación</label>
                            <select name="installation_id" required class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($installations as $installation)
                                    <option value="{{ $installation->id }}" @selected(old('installation_id', $location->installation_id) == $installation->id)>{{ $installation->name }}</option>
                                @endforeach
                            </select>
                            @error('installation_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Código</label>
                            <input type="text" name="code" value="{{ old('code', $location->code) }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                            @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Nombre</label>
                            <input type="text" name="name" value="{{ old('name', $location->name) }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Coordenadas (GPS)</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" name="latitude" x-ref="latitude" value="{{ old('latitude', $location->latitude) }}" placeholder="Latitud" class="block w-1/2 rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <input type="text" name="longitude" x-ref="longitude" value="{{ old('longitude', $location->longitude) }}" placeholder="Longitud" class="block w-1/2 rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="button" @click="capture()" class="mt-2 inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-300 bg-indigo-950/40 border border-indigo-700 rounded-md hover:bg-indigo-900/40 transition-colors gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Tomar ubicación actual
                            </button>
                            <p x-show="coords" x-text="'Listo: ' + coords" class="mt-1 text-xs text-emerald-400"></p>
                            <p x-show="error" x-text="error" class="mt-1 text-xs text-red-400"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Dirección</label>
                            <input type="text" name="address" value="{{ old('address', $location->address) }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Teléfono</label>
                            <input type="text" name="phone" value="{{ old('phone', $location->phone) }}" class="mt-1 block w-full rounded-md bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $location->is_active) ? 'checked' : '' }} class="rounded border-slate-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-slate-400">Activo</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="{{ route('access.locations.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@push('scripts')
<script>
    function geoCapture() {
        return {
            coords: '',
            error: '',
            loading: false,
            capture() {
                if (!navigator.geolocation) {
                    this.error = 'Geolocalización no disponible en este navegador.';
                    return;
                }
                this.loading = true;
                this.error = '';
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.$refs.latitude.value = pos.coords.latitude.toFixed(7);
                        this.$refs.longitude.value = pos.coords.longitude.toFixed(7);
                        this.coords = pos.coords.latitude.toFixed(7) + ', ' + pos.coords.longitude.toFixed(7);
                        this.loading = false;
                    },
                    (err) => {
                        this.loading = false;
                        switch(err.code) {
                            case err.PERMISSION_DENIED:
                                this.error = 'Permiso denegado. Active la ubicación en su navegador.';
                                break;
                            case err.POSITION_UNAVAILABLE:
                                this.error = 'Ubicación no disponible.';
                                break;
                            case err.TIMEOUT:
                                this.error = 'Tiempo de espera agotado.';
                                break;
                        }
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            }
        }
    }
</script>
@endpush
</x-access-layout>
