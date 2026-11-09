<x-layouts.resident-app title="Correspondencia">
    <ul class="space-y-2">
        @forelse ($items as $item)
            <li class="rounded-xl border border-slate-800 bg-slate-900/80 p-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white">{{ $item->typeLabel() }}</p>
                        <p class="text-[11px] text-slate-500">
                            {{ $item->received_at?->format('d/m/Y H:i') }}
                            @if ($item->carrier)
                                · {{ $item->carrier }}
                            @endif
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1 truncate">{{ $item->recipientLabel() }}</p>
                    </div>
                    <span class="text-[11px] shrink-0 {{ $item->status === 'delivered' ? 'text-emerald-400' : 'text-amber-300' }}">
                        {{ $item->status === 'delivered' ? 'Entregada' : 'En portería' }}
                    </span>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-500">
                No hay correspondencia registrada para tu unidad.
            </li>
        @endforelse
    </ul>
</x-layouts.resident-app>
