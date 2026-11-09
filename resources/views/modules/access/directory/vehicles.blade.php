<x-access-layout title="Vehículos">
    <div class="space-y-4">
        <x-client.census-filters
            :installations="$installations"
            :node-options="$nodeOptions"
            :installation-id="$installationId"
            :structure-id="$structureId"
        >
            <div>
                <label for="q" class="block text-xs uppercase tracking-wide text-slate-500 mb-1">Buscar</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Placa"
                       class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-white">
            </div>
        </x-client.census-filters>
        <div class="rounded-xl border border-slate-800 overflow-hidden bg-slate-900">
            <table class="min-w-full text-sm divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Placa</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Vehículo</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Unidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td class="px-4 py-3 font-mono text-indigo-300">{{ $vehicle->plate }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ trim("{$vehicle->brand} {$vehicle->model}") }}</td>
                            <td class="px-4 py-3"><x-client.census-node :structure="$vehicle->structure" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-slate-500">Sin vehículos del censo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vehicles->links() }}
    </div>
</x-access-layout>
