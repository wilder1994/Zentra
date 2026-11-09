<x-client-layout :title="$structure->name">
    @php
        $client = $activeClient ?? view()->shared('activeClient');
        $showVehicles = $client instanceof \App\Models\Client && $client->panelModuleEnabled('vehicles');
        $showPets = $client instanceof \App\Models\Client && $client->panelModuleEnabled('pets');
        $statCols = 1 + (int) $showVehicles + (int) $showPets;
    @endphp
    <div class="space-y-6">
        <div>
            <a href="{{ route('client.installations.show', $structure->installation_id) }}" class="text-sm text-teal-400 hover:text-teal-300">← Volver a la instalación</a>
            <h2 class="text-2xl font-bold text-white mt-2">{{ $structure->name }}</h2>
            <p class="text-sm text-slate-400">{{ $structure->full_path }} · {{ $structure->structureType?->name }}</p>
        </div>

        <div class="border-b border-slate-800">
            <nav class="flex gap-4 text-sm">
                <span class="px-1 py-2 border-b-2 border-teal-500 text-teal-300 font-medium">Datos</span>
                <span class="px-1 py-2 text-slate-500">Visitas</span>
                <span class="px-1 py-2 text-slate-500">Correspondencia</span>
            </nav>
        </div>

        <div class="grid gap-4 {{ $statCols === 1 ? 'md:grid-cols-1' : ($statCols === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3') }}">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="text-xs uppercase text-slate-500">Personas</p>
                <p class="text-2xl font-bold text-white">{{ $structure->members->count() }}</p>
            </div>
            @if ($showVehicles)
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                    <p class="text-xs uppercase text-slate-500">Vehículos</p>
                    <p class="text-2xl font-bold text-white">{{ $structure->vehicles->count() }}</p>
                </div>
            @endif
            @if ($showPets)
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                    <p class="text-xs uppercase text-slate-500">Mascotas</p>
                    <p class="text-2xl font-bold text-white">{{ $structure->pets->count() }}</p>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-4 py-3 bg-slate-950/60 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Personas en esta unidad</h3>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-800">
                    @forelse ($structure->members as $member)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('client.members.show', $member) }}" class="text-teal-300 hover:text-teal-200">{{ $member->full_name }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $member->memberType?->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $member->displayedDocument() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-slate-500">Sin personas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($showPets)
        <div class="rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-4 py-3 bg-slate-950/60 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Mascotas</h3>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/30">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Nombre</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Especie</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Raza</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold uppercase text-slate-500">Peligroso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($structure->pets as $pet)
                        <tr>
                            <td class="px-4 py-2 text-white">{{ $pet->name }}</td>
                            <td class="px-4 py-2 text-slate-400">{{ $pet->species->label() }}</td>
                            <td class="px-4 py-2 text-slate-400">{{ $pet->breed ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if($pet->is_potentially_dangerous)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900/50 text-red-300">Sí</span>
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-slate-500">Sin mascotas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-client-layout>
