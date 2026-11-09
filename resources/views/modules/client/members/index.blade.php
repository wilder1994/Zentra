<x-client-layout title="Personas" :wide="true">
    <div class="space-y-4">
        <x-client.census-filters
            :installations="$installations"
            :node-options="$nodeOptions"
            :installation-id="$installationId"
            :structure-id="$structureId"
        >
            <div>
                <label for="q" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Buscar</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o documento"
                       class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-white">
            </div>
            <x-slot:actions>
                @include('modules.client.members.partials.types-modal')
                <a href="{{ route('client.members.export') }}" class="inline-flex rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600">
                    Exportar
                </a>
                <a href="{{ route('client.members.create') }}" class="inline-flex rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">
                    Nueva persona
                </a>
            </x-slot:actions>
        </x-client.census-filters>

        <div class="rounded-xl border border-slate-800 overflow-hidden bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Persona</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Unidad</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Código de acceso</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($members as $member)
                        <tr class="hover:bg-slate-800/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($member->revealsPii() && $member->photo_path)
                                        <img src="{{ Storage::url($member->photo_path) }}" alt="" class="w-8 h-8 rounded-full object-cover ring-2 ring-slate-700">
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-teal-500 to-teal-700 flex items-center justify-center text-white text-xs font-bold ring-2 ring-slate-700">
                                            {{ strtoupper(substr($member->first_name, 0, 1)) }}{{ strtoupper(substr($member->last_name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <a href="{{ route('client.members.show', $member) }}" class="font-medium text-white hover:text-teal-300">{{ $member->full_name }}</a>
                                        <p class="text-xs text-slate-500">{{ $member->displayedDocument() }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3"><x-client.census-node :structure="$member->structure" /></td>
                            <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-300 ring-1 ring-slate-700">{{ $member->memberType?->name ?? '—' }}</span></td>
                            <td class="px-4 py-3 font-mono text-xs text-indigo-300">{{ $member->revealsPii() ? $member->access_code : '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('client.members.edit', $member) }}" class="text-xs text-slate-500 hover:text-teal-400 transition-colors">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No hay personas en el censo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $members->links() }}
    </div>
</x-client-layout>
