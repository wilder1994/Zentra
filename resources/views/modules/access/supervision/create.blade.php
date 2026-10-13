<x-access-layout title="Nueva Supervisión">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.supervision.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @php($activeSupervisor = session('supervision.supervisor_name'))
        @php($isManager = auth()->user()->isSupervisionManager())
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Registrar Supervisión</h3>
                <p class="text-sm text-slate-500 mt-0.5">Complete los campos para registrar la visita de supervisión</p>
            </div>
            <div class="px-6 py-5" x-data="geoCapture()">
                <form method="POST" action="{{ route('access.supervision.store') }}" enctype="multipart/form-data">
                    @csrf

                    @if($errors->any())
                    <div class="mb-5 rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                    @endif

                    @if ($activeSupervisor)
                    <div class="mb-5 flex items-center gap-3 bg-indigo-900/40 border border-indigo-700 rounded-lg p-4">
                        <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold flex-shrink-0">{{ strtoupper(substr($activeSupervisor, 0, 2)) }}</div>
                        <div>
                            <p class="text-xs font-medium text-indigo-300 uppercase tracking-wider">Supervisor que supervisa</p>
                            <p class="text-sm font-semibold text-white">{{ $activeSupervisor }}</p>
                        </div>
                        <input type="hidden" name="supervisor_name" value="{{ $activeSupervisor }}">
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Ubicación</label>
                            <select name="location_id" required class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Fecha/Hora</label>
                            <input type="datetime-local" name="log_date" value="{{ old('log_date', date('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Tipo de supervisión</label>
                            <select name="type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="rutina">✅ Ronda de rutina</option>
                                <option value="inspeccion">🔍 Inspección</option>
                                <option value="novedad">🔶 Novedad</option>
                                <option value="incidente">🚨 Incidente</option>
                                <option value="general">📋 General</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300">Turno</label>
                            <select name="shift_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="diurno">☀️ Diurno</option>
                                <option value="nocturno">🌙 Nocturno</option>
                            </select>
                        </div>
                    </div>

                    @if (!$activeSupervisor)
                    <div class="mt-5">
                        <label class="block text-sm font-medium text-slate-300">Nombre del supervisor (opcional)</label>
                        <input type="text" name="supervisor_name" value="{{ old('supervisor_name') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Nombre de quien supervisa">
                    </div>
                    @endif

                    <div class="mt-5 bg-slate-950 rounded-lg p-4 border border-slate-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="text-sm font-medium text-slate-300">Geolocalización</span>
                            </div>
                            <button @click="capture()" type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-300 bg-indigo-900/40 rounded-lg hover:bg-indigo-800/60 transition-colors" x-text="captured ? 'Capturada' : 'Capturar ubicación'"></button>
                        </div>
                        <div class="mt-2 text-xs text-slate-500" x-show="!captured && !error && !loading">
                            La ubicación se usará para verificar la presencia del supervisor en el sitio.
                        </div>
                        <div class="mt-2 text-xs text-emerald-400" x-show="captured" x-cloak>
                            <span class="font-medium">✓ Ubicación capturada:</span>
                            <span x-text="`${lat}, ${lng}`"></span>
                        </div>
                        <div class="mt-2 text-xs text-red-400" x-show="error" x-cloak x-text="error"></div>
                        <div class="mt-2 text-xs text-slate-500" x-show="loading" x-cloak>Obteniendo ubicación...</div>
                        <input type="hidden" name="latitude" x-model="lat">
                        <input type="hidden" name="longitude" x-model="lng">
                    </div>

                    <div class="mt-5 bg-slate-950 rounded-lg p-4 border border-slate-700">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span class="text-sm font-medium text-slate-300">Registros fotográficos</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2">Fotografías de la supervisión (evidencias, rondas, novedades). Puede adjuntar varias.</p>
                        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-900/50 file:text-indigo-200 hover:file:bg-indigo-800/60">
                    </div>

                    <div class="mt-5 bg-slate-950 rounded-lg p-4 border border-slate-700">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-sm font-medium text-slate-300">Documentos</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2">Documentos de respaldo (informes, actas, reportes en PDF, hojas de cálculo).</p>
                        <input type="file" name="documents[]" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png,image/webp" multiple class="block w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-900 file:text-indigo-200 hover:file:bg-indigo-800/60">
                    </div>

                    <div class="mt-5">
                        <label class="block text-sm font-medium text-slate-300">Descripción</label>
                        <textarea name="description" rows="4" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Describa los hallazgos, observaciones o novedades de la supervisión..." required>{{ old('description') }}</textarea>
                    </div>

                    <div class="mt-5 bg-emerald-900/30 rounded-lg p-4 border border-emerald-700">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="signed" value="1" class="mt-0.5 rounded bg-slate-950 border-emerald-600 text-emerald-500 focus:ring-emerald-500" required>
                            <div>
                                <p class="text-sm font-medium text-emerald-200">Confirmo que la información registrada es verídica</p>
                                <p class="text-xs text-emerald-400">Firma digital — el sistema registrará su identidad y hora de confirmación.</p>
                            </div>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <a href="{{ route('access.supervision.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition-colors shadow-sm">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Guardar Supervisión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@push('scripts')
<script>
    function geoCapture() {
        return {
            lat: '{{ old('latitude') }}',
            lng: '{{ old('longitude') }}',
            captured: !!'{{ old('latitude') }}',
            loading: false,
            error: '',
            capture() {
                if (!navigator.geolocation) {
                    this.error = 'Geolocalización no disponible en este navegador.';
                    return;
                }
                this.loading = true;
                this.error = '';
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.lat = pos.coords.latitude.toFixed(7);
                        this.lng = pos.coords.longitude.toFixed(7);
                        this.captured = true;
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