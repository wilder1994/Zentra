@php
    $types = $types ?? ($map['types'] ?? []);
@endphp
<div class="obs-card p-2.5 h-full min-h-0 flex flex-col">
    <p class="text-[10px] uppercase tracking-wide text-slate-500">Tipos y nivel</p>
    <div class="mt-1.5 flex items-center gap-3 text-[11px] text-slate-400 shrink-0">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>Instalación</span>
        <span class="inline-flex items-center gap-1.5">▾ reporte</span>
    </div>
    <div class="mt-2 space-y-1 overflow-y-auto sidebar-scroll min-h-0">
        @forelse ($types as $type)
            <div class="flex items-center gap-2 text-[11px] text-slate-200">
                <span class="h-2 w-2 rounded-full shrink-0" style="background: {{ $type['color'] }}"></span>
                <span class="truncate">{{ $type['name'] }}</span>
                <span class="ml-auto text-slate-500 shrink-0">N{{ $type['level'] }}</span>
            </div>
        @empty
            <p class="text-[11px] text-slate-500">El admin del cliente crea los tipos.</p>
        @endforelse
    </div>
</div>
