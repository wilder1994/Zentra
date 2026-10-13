<x-access-layout title="Detalle de Autorización">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.pre_authorizations.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-5">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center text-white text-lg font-bold">
                        {{ strtoupper(substr($preAuthorization->visitor->full_name, 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">{{ $preAuthorization->visitor->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $preAuthorization->visitor->displayedDocument() }}</p>
                    </div>
                    <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ring-1
                        @if($preAuthorization->status == 'pending') bg-amber-900/30 text-amber-300 ring-amber-700
                        @elseif($preAuthorization->status == 'used') bg-blue-900/30 text-blue-300 ring-blue-700
                        @elseif($preAuthorization->status == 'cancelled') bg-red-900/30 text-red-300 ring-red-700
                        @else bg-gray-900/30 text-gray-300 ring-gray-700 @endif">
                        {{ ucfirst($preAuthorization->status) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Anfitrión</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $preAuthorization->host->name }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Ubicación</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $preAuthorization->location->name }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Fecha</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ $preAuthorization->scheduled_date->format('d/m/Y') }}</p>
                    </div>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Estado</p>
                        <p class="mt-1 text-sm font-semibold text-white">{{ ucfirst($preAuthorization->status) }}</p>
                    </div>
                </div>

                @if($preAuthorization->notes)
                <div class="mt-4">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Notas</p>
                    <div class="bg-slate-950/60 rounded-lg p-3">
                        <p class="text-sm text-white">{{ $preAuthorization->notes }}</p>
                    </div>
                </div>
                @endif

                <div class="mt-6 bg-purple-900/30 border border-purple-800 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-semibold text-purple-300">Código QR de Autorización</p>
                        <span class="text-xs text-purple-400">Presente este código en la portería</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-slate-950 border border-purple-800 rounded-lg px-4 py-3">
                            <code class="text-sm font-mono text-purple-300 break-all">{{ $preAuthorization->qr_code }}</code>
                        </div>
                        <button onclick="navigator.clipboard.writeText('{{ $preAuthorization->qr_code }}')" class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-indigo-500 transition-colors shadow-sm whitespace-nowrap">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            Copiar
                        </button>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-950/60 border-t border-slate-800 flex justify-end gap-2">
                @if($preAuthorization->status == 'pending')
                <form action="{{ route('access.pre_authorizations.destroy', $preAuthorization) }}" method="POST" onsubmit="return confirm('¿Cancelar esta pre-autorización?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-red-700 transition-colors shadow-sm">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancelar Autorización
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</x-access-layout>
