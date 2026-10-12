@php($icon = 'h-5 w-5 shrink-0')
<h3 class="text-sm font-semibold text-white shrink-0">Convenciones</h3>
<div class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] text-slate-400 leading-tight">
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'start', 'class' => $icon])Inicio de turno</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'flag', 'class' => $icon])Cierre de turno</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'review', 'class' => $icon])Revista</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'novelty', 'class' => $icon])Revista con novedad</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'alarm', 'class' => $icon])Alarma</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'support', 'class' => $icon])Apoyo</span>
    <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', ['kind' => 'stop', 'class' => $icon])Parada</span>
    <span class="inline-flex items-center gap-1.5">
        <img src="{{ asset('images/ui/supervisor-moto.png') }}?v={{ filemtime(public_path('images/ui/supervisor-moto.png')) }}" alt="" class="h-5 w-auto shrink-0">
        Supervisor (moto)
    </span>
    @if (($live ?? false) === true)
        @php($coverage = ['kind' => 'coverage', 'class' => $icon, 'ring' => '', 'fontSize' => '13'])
        <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', $coverage + ['color' => '#16a34a', 'text' => '3'])Puestos revisados</span>
        <span class="inline-flex items-center gap-1.5">@include('modules.company.supervision.partials.pin-icon', $coverage + ['color' => '#f59e0b', 'text' => '2'])Revisión parcial</span>
        <span class="inline-flex items-center gap-1.5 col-span-2">@include('modules.company.supervision.partials.pin-icon', $coverage + ['color' => '#dc2626', 'text' => '4'])Pendiente por revista (número = puestos)</span>
    @else
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-5 w-5 shrink-0 items-center justify-center"><span class="h-[18px] w-[18px] rounded-full bg-indigo-500 ring-1 ring-slate-900"></span></span>Cliente</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-5 w-5 shrink-0 items-center justify-center"><span class="h-[18px] w-[18px] rounded-full bg-cyan-400 ring-1 ring-slate-900"></span></span>Instalación</span>
    @endif
</div>
<p class="mt-1.5 text-[11px] text-slate-500">Señal: <span class="text-emerald-400">En línea</span> · <span class="text-amber-400">Pantalla apagada</span> · <span class="text-red-400">Sin señal</span> (más de 90 s sin GPS)</p>
