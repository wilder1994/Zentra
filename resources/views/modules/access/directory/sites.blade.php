@php
    use App\Support\Structure\StructureCopy;

    $structureClient = $activeClient ?? null;
    $unitsLabel = mb_strtolower(StructureCopy::unitPlural($structureClient));
@endphp

<x-access-layout title="Estructura">
    <div class="space-y-4">
        <div class="rounded-lg border border-slate-800 bg-slate-900/60 px-4 py-3">
            <p class="text-sm text-slate-300">Consulta de sedes y {{ $unitsLabel }}. Solo lectura: no se crean ni editan desde portería.</p>
        </div>

        @forelse ($sites as $site)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-white">{{ $site->name }}</h2>
                        <p class="text-xs text-slate-500">{{ $site->structures_count }} {{ $unitsLabel }}</p>
                    </div>
                </div>

                @php $tree = $trees[(int) $site->id] ?? collect(); @endphp
                @if ($tree->isEmpty())
                    <p class="text-sm text-slate-500">Sin {{ $unitsLabel }} en esta sede.</p>
                @else
                    <x-client.structure-tree
                        :nodes="$tree"
                        :linkable="false"
                        :pick-parent="false"
                        :show-vehicles="false"
                        :show-pets="false"
                    />
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-10 text-center text-sm text-slate-500">
                Sin sedes para consultar.
            </div>
        @endforelse
    </div>
</x-access-layout>
