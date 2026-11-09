<x-client-layout title="Vehículos" :wide="true">
    <div class="space-y-4">
        <div class="flex justify-end">
            <a href="{{ route('client.vehicles.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">Nuevo vehículo</a>
        </div>
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
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">SOAT</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-slate-500">Parqueadero</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($vehicles as $vehicle)
                        @php
                            $soatBadge = 'bg-slate-800 text-slate-500 ring-slate-700';
                            $soatLabel = '—';
                            if ($vehicle->soat_expires_at) {
                                $daysUntilExpiry = now()->diffInDays($vehicle->soat_expires_at, false);
                                if ($daysUntilExpiry < 0) {
                                    $soatBadge = 'bg-red-900/40 text-red-400 ring-red-700/50';
                                    $soatLabel = 'Vencido: ' . $vehicle->soat_expires_at->format('d/m/Y');
                                } elseif ($daysUntilExpiry <= 30) {
                                    $soatBadge = 'bg-amber-900/40 text-amber-400 ring-amber-700/50';
                                    $soatLabel = 'Vence: ' . $vehicle->soat_expires_at->format('d/m/Y');
                                } else {
                                    $soatBadge = 'bg-emerald-900/40 text-emerald-400 ring-emerald-700/50';
                                    $soatLabel = 'Vence: ' . $vehicle->soat_expires_at->format('d/m/Y');
                                }
                            }
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-mono text-teal-300">{{ $vehicle->plate }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ trim("{$vehicle->brand} {$vehicle->model}") }}</td>
                            <td class="px-4 py-3"><x-client.census-node :structure="$vehicle->structure" /></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $soatBadge }}">{{ $soatLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $vehicle->assigned_parking_spot ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Sin vehículos en censo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vehicles->links() }}
    </div>
</x-client-layout>
