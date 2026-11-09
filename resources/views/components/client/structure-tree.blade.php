@props(['nodes', 'census' => null, 'depth' => 0, 'pickParent' => false, 'linkable' => true, 'showVehicles' => null, 'showPets' => null])

@php
    $client = $activeClient ?? view()->shared('activeClient');
    $showVehicles = $showVehicles ?? ($client instanceof \App\Models\Client && $client->panelModuleEnabled('vehicles'));
    $showPets = $showPets ?? ($client instanceof \App\Models\Client && $client->panelModuleEnabled('pets'));
    $showCensus = is_array($census);
@endphp

<ul class="{{ $depth === 0 ? 'space-y-2' : 'mt-2 ml-4 space-y-2 border-l border-slate-800 pl-4' }}">
    @foreach ($nodes as $node)
        @php
            $counts = $showCensus ? ($census[$node->id] ?? ['members' => 0, 'vehicles' => 0, 'pets' => 0]) : null;
        @endphp
        <li class="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0 flex items-start gap-2">
                    @if ($pickParent)
                        <button
                            type="button"
                            class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded border border-slate-700 text-sm font-semibold text-slate-300 hover:border-teal-500 hover:text-teal-300"
                            title="Crear dentro de {{ $node->name }}"
                            @click="$dispatch('structure-parent', { id: {{ $node->id }} })"
                        >+</button>
                    @endif
                    <div class="min-w-0">
                        @if ($linkable)
                            <a href="{{ route('client.structures.show', $node) }}" class="font-medium text-white hover:text-teal-300">
                                {{ $node->name }}
                            </a>
                        @else
                            <p class="font-medium text-white">{{ $node->name }}</p>
                        @endif
                        <p class="text-xs text-slate-500">{{ $node->structureType?->name }}</p>
                    </div>
                </div>
                @if ($showCensus)
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-indigo-900/50 px-2 py-0.5 text-indigo-200" title="Personas">{{ $counts['members'] }} personas</span>
                        @if ($showVehicles)
                            <span class="rounded-full bg-sky-900/50 px-2 py-0.5 text-sky-200" title="Vehículos">{{ $counts['vehicles'] }} vehículos</span>
                        @endif
                        @if ($showPets)
                            <span class="rounded-full bg-amber-900/50 px-2 py-0.5 text-amber-200" title="Mascotas">{{ $counts['pets'] }} mascotas</span>
                        @endif
                    </div>
                @endif
            </div>
            @if ($node->children->isNotEmpty())
                <x-client.structure-tree
                    :nodes="$node->children"
                    :census="$census"
                    :depth="$depth + 1"
                    :pick-parent="$pickParent"
                    :linkable="$linkable"
                    :show-vehicles="$showVehicles"
                    :show-pets="$showPets"
                />
            @endif
        </li>
    @endforeach
</ul>
