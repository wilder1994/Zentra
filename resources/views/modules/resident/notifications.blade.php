<x-resident-layout title="Notificaciones">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-white">Notificaciones</h2>
                <p class="text-sm text-slate-400 mt-1">Alertas y avisos del cliente.</p>
            </div>
            <form method="POST" action="{{ route('resident.notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-600 transition-colors">Marcar todas como leídas</button>
            </form>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
            <div class="divide-y divide-slate-800/70">
                @forelse ($notifications as $n)
                    @php $data = $n->data; @endphp
                    <div class="px-5 py-4 flex items-start gap-3 {{ $n->read_at ? 'opacity-60' : '' }}">
                        <span class="mt-1.5 w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $n->read_at ? 'bg-slate-700' : 'bg-teal-400' }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-white">{{ $data['title'] ?? 'Notificación' }}</p>
                            <p class="text-sm text-slate-400 mt-0.5">{{ $data['message'] ?? '' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $n->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @if(! empty($data['url']))
                            <a href="{{ $data['url'] }}" class="inline-flex items-center text-xs font-semibold text-teal-400 hover:text-teal-300 flex-shrink-0">Ver →</a>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No tienes notificaciones.</div>
                @endforelse
            </div>
        </div>

        <div>
            {{ $notifications->links() }}
        </div>
    </div>
</x-resident-layout>