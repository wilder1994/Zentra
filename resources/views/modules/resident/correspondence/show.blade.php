<x-resident-layout title="Correspondencia">
    <div class="max-w-2xl">
        <a href="{{ route('resident.correspondence.index') }}" class="text-sm text-teal-400 hover:text-teal-300">← Correspondencia</a>
        <h2 class="text-2xl font-bold text-white mt-2">Detalle de correspondencia</h2>

        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900 p-6 space-y-3">
            <p class="text-sm text-slate-300">Tipo: <span class="text-white">{{ $correspondence->package_type }}</span></p>
            <p class="text-sm text-slate-300">Mensajero: {{ $correspondence->carrier ?? '—' }}</p>
            <p class="text-sm text-slate-300">Guía: {{ $correspondence->courier_guide ?? '—' }}</p>
            <p class="text-sm text-slate-300">Recibido: {{ $correspondence->received_at->format('d/m/Y H:i') }} por {{ $correspondence->receiver?->name ?? '—' }}</p>
            @if($correspondence->delivered_at)
                <p class="text-sm text-slate-300">Entregado: {{ $correspondence->delivered_at->format('d/m/Y H:i') }} por {{ $correspondence->deliverer?->name ?? '—' }}</p>
            @endif
            <p class="text-sm text-slate-300">
                Estado:
                <span class="px-2 py-0.5 rounded text-xs font-medium {{
                    $correspondence->status === 'pending' ? 'bg-yellow-900/50 text-yellow-300' : 'bg-green-900/50 text-green-300'
                }}">{{ $correspondence->status === 'pending' ? 'Pendiente' : 'Entregado' }}</span>
            </p>
            @if($correspondence->notes)
                <p class="text-sm text-slate-400 mt-2">Notas: {{ $correspondence->notes }}</p>
            @endif
        </div>
    </div>
</x-resident-layout>
