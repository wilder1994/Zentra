<x-access-layout>
    <div class="-mt-6 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 pt-6 pb-8 bg-gradient-to-r from-slate-800 to-indigo-900 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-300">Seguridad</p>
                <h2 class="text-xl font-bold text-white">Lista de Bloqueo</h2>
            </div>
            <a href="{{ route('access.blocklist.create') }}" class="inline-flex items-center px-3 py-1.5 bg-red-500 hover:bg-red-400 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                Agregar a bloqueo
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        @if(session('success'))
        <div class="mt-6 rounded-lg bg-emerald-900/40 border border-emerald-700 text-emerald-200 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif

        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Entradas Bloqueadas</h3>
                <p class="text-sm text-slate-500 mt-0.5">Personas y vehículos con ingreso denegado al conjunto</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Identificación</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Razón</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Bloqueado por</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Desde</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Expira</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($entries as $entry)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php $type = $entry->normalizedType(); @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($type === 'visitor') bg-blue-900/30 text-blue-300 ring-1 ring-blue-700
                                    @elseif($type === 'vehicle') bg-cyan-900/30 text-cyan-300 ring-1 ring-cyan-700
                                    @elseif($type === 'resident') bg-violet-900/30 text-violet-300 ring-1 ring-violet-700
                                    @else bg-slate-800 text-slate-300 ring-1 ring-slate-600 @endif">
                                    {{ $entry->typeLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">#{{ $entry->blockable_id }}</td>
                            <td class="px-6 py-4 text-sm text-slate-400 max-w-xs truncate">{{ $entry->reason }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $entry->blocker?->name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $entry->blocked_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm {{ $entry->expires_at && $entry->expires_at->isPast() ? 'text-red-400 font-semibold' : 'text-slate-400' }}">
                                {{ $entry->expires_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @can('access.blocklist.remove')
                                <form action="{{ route('access.blocklist.destroy', $entry) }}" method="POST" onsubmit="return confirm('¿Remover este bloqueo?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-2.5 py-1 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z"/></svg>
                                        Remover
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No hay entradas bloqueadas.</p>
                                <a href="{{ route('access.blocklist.create') }}" class="mt-3 inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-colors">
                                    Agregar a lista de bloqueo
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $entries->links() }}
    </div>
</x-access-layout>