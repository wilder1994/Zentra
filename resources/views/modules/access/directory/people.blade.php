<x-access-layout title="Personas">
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
        </x-client.census-filters>
        <div class="rounded-xl border border-slate-800 overflow-hidden bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Persona</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Unidad</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Tipo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($members as $member)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-white">{{ $member->full_name }}</p>
                                <p class="text-xs text-slate-500">{{ $member->displayedDocument() }}</p>
                            </td>
                            <td class="px-4 py-3"><x-client.census-node :structure="$member->structure" /></td>
                            <td class="px-4 py-3 text-slate-400">{{ $member->memberType?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">No hay personas en el censo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $members->links() }}
    </div>
</x-access-layout>
