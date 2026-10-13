<x-access-layout title="Minuta de Vigilancia">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.guard_logs.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-5">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-white text-lg font-bold">
                        {{ strtoupper(substr($guardLog->user->name, 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">{{ $guardLog->user->name }}</p>
                        <p class="text-xs text-slate-500">{{ $guardLog->user->email }}</p>
                    </div>
                    <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ring-1
                        @if($guardLog->type == 'incidente') bg-red-900/30 text-red-300 ring-red-700
                        @elseif($guardLog->type == 'novedad') bg-amber-900/30 text-amber-300 ring-amber-700
                        @elseif($guardLog->type == 'turno') bg-blue-900/30 text-blue-300 ring-blue-700
                        @else bg-slate-800 text-slate-300 ring-slate-600 @endif">
                        {{ ucfirst($guardLog->type) }}
                    </span>
                </div>

                @if($guardLog->is_panic)
                <div class="mb-6 bg-red-900/30 border border-red-800 rounded-lg p-4 flex items-center gap-3">
                    <svg class="w-8 h-8 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <div>
                        <p class="text-sm font-bold text-red-200">ALERTA DE PÁNICO</p>
                        <p class="text-xs text-red-400">Esta minuta fue generada por el botón de pánico</p>
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-slate-950 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Ubicación</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $guardLog->location->name }}</p>
                    </div>
                    <div class="bg-slate-950 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Fecha/Hora</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $guardLog->log_time->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="bg-slate-950 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Turno</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ ucfirst($guardLog->shift_type) }}</p>
                    </div>
                </div>

                @if($guardLog->latitude && $guardLog->longitude)
                <div class="mb-6 bg-slate-950 rounded-lg p-4 border border-slate-700">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Geolocalización</p>
                    </div>
                    <p class="text-xs text-slate-400 font-mono">{{ $guardLog->latitude }}, {{ $guardLog->longitude }}</p>
                    <a href="https://www.google.com/maps?q={{ $guardLog->latitude }},{{ $guardLog->longitude }}" target="_blank" class="mt-1 inline-flex items-center text-xs text-indigo-400 hover:text-indigo-300">
                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Ver en Google Maps
                    </a>
                </div>
                @endif

                @if($guardLog->signed_at)
                <div class="mb-6 rounded-lg bg-emerald-900/40 border border-emerald-700 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-emerald-200">Firmada digitalmente por {{ $guardLog->user->name }}</p>
                        <p class="text-xs text-emerald-400">{{ $guardLog->signed_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                    @if($guardLog->supervisor_name)
                        <div class="text-right">
                            <p class="text-sm font-medium text-indigo-200">Validada por {{ $guardLog->supervisor_name }}</p>
                            <p class="text-xs text-indigo-400">Firma de supervisor (código registrado)</p>
                        </div>
                    @endif
                </div>
                @endif

                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Descripción</p>
                    <div class="bg-slate-950 rounded-lg p-4">
                        <p class="text-sm text-white whitespace-pre-wrap leading-relaxed">{{ $guardLog->description }}</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-950 border-t border-slate-800 flex justify-end">
                <form action="{{ route('access.guard_logs.destroy', $guardLog) }}" method="POST" onsubmit="return confirm('¿Eliminar esta minuta?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-red-700 transition-colors shadow-sm">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-access-layout>