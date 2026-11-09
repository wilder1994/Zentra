@php
    use App\Support\Structure\StructureCopy;
@endphp

<x-client-layout title="Mi estructura">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <h2 class="text-sm font-semibold text-slate-200">Sedes</h2>
                <x-ui.intro-help storage-key="client.mi-estructura.index" :text="StructureCopy::helpIndex()" />
            </div>
        </div>

        <form method="GET" action="{{ route('client.installations.index') }}"
              class="rounded-lg border border-slate-800 bg-slate-900/60 p-3 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1 min-w-0">
                <label for="q" class="sr-only">Buscar sede</label>
                <input type="search" id="q" name="q" value="{{ $search }}"
                       placeholder="Buscar sede…"
                       class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30">
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-slate-700 px-4 text-sm text-slate-200 hover:bg-slate-800">Buscar</button>
                @if ($search !== '')
                    <a href="{{ route('client.installations.index') }}" class="text-xs text-slate-500 hover:text-slate-300">Limpiar</a>
                @endif
            </div>
        </form>

        <div class="rounded-lg border border-slate-800 overflow-hidden bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium">Sede</th>
                            <th class="px-4 py-2.5 text-left font-medium">Código</th>
                            <th class="px-4 py-2.5 text-left font-medium hidden md:table-cell">Área</th>
                            <th class="px-4 py-2.5 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($installations as $installation)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-200">{{ $installation->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $installation->profileLabel() }}</p>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-teal-300/90">
                                    {{ $installation->code ?: '—' }}
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-slate-400">{{ $installation->commune ?: '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('client.installations.show', $installation) }}" class="text-xs text-teal-400 hover:text-teal-300">Abrir estructura</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Aún no hay sedes. Las crea la empresa de seguridad en el expediente del cliente.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($installations->hasPages())
            <div>{{ $installations->links() }}</div>
        @endif
    </div>
</x-client-layout>
