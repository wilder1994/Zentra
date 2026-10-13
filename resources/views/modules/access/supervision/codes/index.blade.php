<x-access-layout title="Códigos de Supervisión">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.supervision.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800">
                    <h3 class="text-lg font-semibold text-white">Crear Código</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Asigne un código único por supervisor</p>
                </div>
                <div class="px-6 py-5">
                    <form method="POST" action="{{ route('access.supervision.codes.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Nombre del supervisor</label>
                                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ej: Carlos Rodríguez">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Código único</label>
                                <input type="text" name="code" value="{{ old('code') }}" required maxlength="100" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white placeholder-slate-600 focus:border-indigo-500 focus:ring-indigo-500 font-mono" placeholder="Ej: SUP-2026-001">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Activo</label>
                                <div class="mt-1">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_active" value="1" checked class="rounded bg-slate-950 border-slate-600 text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2 text-xs text-slate-400">Permitir acceso con este código</span>
                                    </label>
                                </div>
                            </div>
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition-colors shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Crear Código
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800">
                    <h3 class="text-lg font-semibold text-white">Supervisores registrados</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Administre los códigos de acceso al módulo</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-slate-800">
                        <thead class="bg-slate-950/60">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Supervisor</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Código</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Registros</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($codes as $code)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                            {{ strtoupper(substr($code->name, 0, 2)) }}
                                        </div>
                                        <p class="ml-3 text-sm font-medium text-white">{{ $code->name }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400 font-mono">{{ $code->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-800 text-slate-300">{{ $code->supervisions_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 {{ $code->is_active ? 'bg-emerald-900/30 text-emerald-300 ring-emerald-700' : 'bg-slate-800 text-slate-400 ring-slate-600' }}">
                                        {{ $code->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <form action="{{ route('access.supervision.codes.toggle', $code) }}" method="POST" class="inline mr-2">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-{{ $code->is_active ? 'amber' : 'emerald' }}-400 hover:text-{{ $code->is_active ? 'amber' : 'emerald' }}-300 font-medium text-xs">
                                            {{ $code->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('access.supervision.codes.destroy', $code) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar el código de {{ $code->name }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300 font-medium text-xs">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center">
                                    <svg class="mx-auto h-10 w-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1115 9z"/></svg>
                                    <p class="mt-2 text-sm text-slate-500">No hay códigos de supervisión creados</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-3 border-t border-slate-800">{{ $codes->links() }}</div>
            </div>
        </div>
    </div>
</x-access-layout>