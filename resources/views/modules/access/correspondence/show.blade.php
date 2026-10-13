<x-access-layout title="Detalle de Correspondencia">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.correspondence.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    @php
        $destinatario = $correspondence->resident?->full_name ?? $correspondence->housingUnit?->full_label ?? $correspondence->host?->name ?? '-';
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-5">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-pink-500 to-pink-700 flex items-center justify-center text-white text-lg font-bold">
                        {{ strtoupper(substr($destinatario, 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">{{ $destinatario }}</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1
                            @if($correspondence->package_type == 'caja') bg-orange-900/30 text-orange-300 ring-orange-700
                            @elseif($correspondence->package_type == 'sobre') bg-blue-900/30 text-blue-300 ring-blue-700
                            @else bg-gray-900/30 text-gray-300 ring-gray-700 @endif">
                            {{ ucfirst($correspondence->package_type) }}
                        </span>
                    </div>
                    <span class="ml-auto inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ring-1
                        {{ $correspondence->status == 'pending' ? 'bg-amber-900/30 text-amber-300 ring-amber-700' : 'bg-emerald-900/30 text-emerald-300 ring-emerald-700' }}">
                        @if($correspondence->status == 'pending')
                            <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                            Pendiente
                        @else
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Entregado
                        @endif
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Transportadora</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $correspondence->carrier ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Guía</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $correspondence->courier_guide ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Recibido por</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $correspondence->receiver?->name ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Recibido el</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $correspondence->received_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @if($correspondence->delivered_at)
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Entregado por</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $correspondence->deliverer?->name ?? '-' }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Entregado el</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $correspondence->delivered_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @endif
                </div>

                @if($correspondence->notes)
                <div class="mt-4">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Notas</p>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-sm text-white">{{ $correspondence->notes }}</p>
                    </div>
                </div>
                @endif
            </div>
            <div class="px-6 py-4 bg-slate-950/60 border-t border-slate-800 flex justify-end gap-2">
                @if($correspondence->status == 'pending')
                <form action="{{ route('access.correspondence.deliver', $correspondence) }}" method="POST" onsubmit="return confirm('¿Marcar como entregado?')">
                    @csrf @method('PATCH')
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-emerald-700 transition-colors shadow-sm">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Marcar como Entregado
                    </button>
                </form>
                @endif
                <form action="{{ route('access.correspondence.destroy', $correspondence) }}" method="POST" onsubmit="return confirm('¿Eliminar este registro?')">
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
