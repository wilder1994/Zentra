@php($icon = 'h-4 w-4 shrink-0')
<div class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] text-slate-400 leading-tight">
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'start', 'class' => $icon])Inicio de turno</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'flag', 'class' => $icon])Cierre de turno</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'review', 'class' => $icon])Revista</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'novelty', 'class' => $icon])Revista con novedad</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'alarm', 'class' => $icon])Alarma</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'support', 'class' => $icon])Apoyo</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'stop', 'class' => $icon])Parada</span>
    <span class="inline-flex items-center gap-1.5">
        <img src="{{ asset('images/ui/supervisor-moto.png') }}?v={{ filemtime(public_path('images/ui/supervisor-moto.png')) }}" alt="" class="h-4 w-auto shrink-0">
        Supervisor (moto)
    </span>
    @if (($live ?? false) === true)
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-green-600 text-[8px] font-bold text-white ring-1 ring-slate-900">3</span>Puestos revisados</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[8px] font-bold text-white ring-1 ring-slate-900">2</span>Revisión parcial</span>
        <span class="inline-flex items-center gap-1.5 col-span-2"><span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-red-600 text-[8px] font-bold text-white ring-1 ring-slate-900">4</span>Pendiente por revista (número = puestos)</span>
    @else
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-4 w-4 shrink-0 rounded-full bg-indigo-500 ring-1 ring-slate-900"></span>Cliente</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-4 w-4 shrink-0 rounded-full bg-cyan-400 ring-1 ring-slate-900"></span>Instalación</span>
    @endif
</div>
<p class="mt-1.5 text-[11px] text-slate-500">Señal: <span class="text-emerald-400">En línea</span> · <span class="text-amber-400">Pantalla apagada</span> · <span class="text-red-400">Sin señal</span> (más de 90 s sin GPS)</p>
<p class="mt-1 text-[11px] text-slate-600">Hover en la moto: quién está de turno. Clic: lista de eventos si hay varios. Clic otra vez cierra.</p>
