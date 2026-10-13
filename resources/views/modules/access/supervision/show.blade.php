<x-access-layout title="Detalle de Supervisión">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.supervision.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-5">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-lg font-bold flex-shrink-0">
                        {{ strtoupper(substr($supervision->supervisor_name ?: 'S', 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">{{ $supervision->supervisor_name ?? 'Sin supervisor asignado' }}</p>
                        <p class="text-xs text-slate-500">Registrado por {{ $supervision->user->name }}</p>
                    </div>
                    <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ring-1
                        @if($supervision->type == 'incidente') bg-red-900/30 text-red-300 ring-red-700
                        @elseif($supervision->type == 'novedad') bg-amber-900/30 text-amber-300 ring-amber-700
                        @elseif($supervision->type == 'rutina') bg-emerald-900/30 text-emerald-300 ring-emerald-700
                        @elseif($supervision->type == 'inspeccion') bg-blue-900/30 text-blue-300 ring-blue-700
                        @else bg-slate-800 text-slate-300 ring-slate-600 @endif">
                        {{ ucfirst($supervision->type) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-slate-950 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Ubicación</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $supervision->location->name }}</p>
                    </div>
                    <div class="bg-slate-950 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Fecha/Hora</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $supervision->log_time->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="bg-slate-950 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Turno</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ ucfirst($supervision->shift_type) }}</p>
                    </div>
                </div>

                @if($supervision->latitude && $supervision->longitude)
                <div class="mb-6 bg-slate-950 rounded-lg p-4 border border-slate-700">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Geolocalización</p>
                    </div>
                    <p class="text-xs text-slate-400 font-mono">{{ $supervision->latitude }}, {{ $supervision->longitude }}</p>
                    <a href="https://www.google.com/maps?q={{ $supervision->latitude }},{{ $supervision->longitude }}" target="_blank" class="mt-1 inline-flex items-center text-xs text-indigo-400 hover:text-indigo-300">
                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Ver en Google Maps
                    </a>
                </div>
                @endif

                @if($supervision->signed_at)
                <div class="mb-6 rounded-lg bg-emerald-900/40 border border-emerald-700 p-4 flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-medium text-emerald-200">Firmada digitalmente por {{ $supervision->user->name }}</p>
                        <p class="text-xs text-emerald-400">{{ $supervision->signed_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>
                @endif

                <div class="mb-6">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Descripción</p>
                    <div class="bg-slate-950 rounded-lg p-4">
                        <p class="text-sm text-white whitespace-pre-wrap leading-relaxed">{{ $supervision->description }}</p>
                    </div>
                </div>

                @if($supervision->attachments->count() > 0)
                <div class="mb-6">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-3">
                        Adjuntos ({{ $supervision->attachments->count() }})
                    </p>

                    @php($photos = $supervision->attachments->where('kind', 'photo'))
                    @if($photos->count() > 0)
                    <p class="text-sm font-semibold text-white mb-2">📷 Registros fotográficos</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
                        @foreach($photos as $photo)
                        <a href="{{ $photo->url() }}" target="_blank" class="group relative rounded-lg overflow-hidden border border-slate-700 bg-slate-950 aspect-square">
                            <img src="{{ $photo->url() }}" alt="{{ $photo->file_name }}" class="w-full h-full object-cover group-hover:opacity-80 transition-opacity">
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/90 to-transparent px-2 py-1 text-[10px] text-slate-300 truncate">{{ $photo->file_name }}</span>
                        </a>
                        @endforeach
                    </div>
                    @endif

                    @php($docs = $supervision->attachments->where('kind', 'document'))
                    @if($docs->count() > 0)
                    <p class="text-sm font-semibold text-white mb-2">📄 Documentos</p>
                    <div class="divide-y divide-slate-800 border border-slate-700 rounded-lg overflow-hidden">
                        @foreach($docs as $doc)
                        <a href="{{ $doc->url() }}" target="_blank" class="flex items-center gap-3 px-4 py-3 bg-slate-950 hover:bg-slate-900 transition-colors">
                            <svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-sm text-slate-300 group-hover:text-white truncate flex-1">{{ $doc->file_name }}</span>
                            @if($doc->size)
                            <span class="text-xs text-slate-500">{{ round($doc->size / 1024) }} KB</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif
            </div>
            <div class="px-6 py-4 bg-slate-950 border-t border-slate-800 flex justify-end">
                <form action="{{ route('access.supervision.destroy', $supervision) }}" method="POST" onsubmit="return confirm('¿Eliminar este registro de supervisión?')">
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