<div class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-[11px] text-slate-400 leading-tight">
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-green-600 ring-1 ring-slate-900"></span>Inicio de turno</span>
    <span class="inline-flex items-center gap-1.5">
        <img src="{{ asset('images/ui/supervisor-moto.png') }}?v={{ filemtime(public_path('images/ui/supervisor-moto.png')) }}" alt="" class="h-5 w-auto shrink-0">
        Supervisor (moto)
    </span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-600 ring-1 ring-slate-900"></span>Cierre de turno</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400 ring-1 ring-slate-900"></span>Revista</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-400 ring-1 ring-slate-900"></span>Revista con novedad</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-sky-400 ring-1 ring-slate-900"></span>Apoyo</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400 ring-1 ring-slate-900"></span>Alarma</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-purple-400 ring-1 ring-slate-900"></span>Parada</span>
    @if (($live ?? false) === true)
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-green-600 text-[9px] font-bold text-white ring-1 ring-slate-900">3</span>Puestos revisados</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white ring-1 ring-slate-900">2</span>Revisión parcial</span>
        <span class="inline-flex items-center gap-1.5 col-span-2"><span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[9px] font-bold text-white ring-1 ring-slate-900">4</span>Pendiente por revista (número = puestos)</span>
    @else
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-indigo-500 ring-1 ring-slate-900"></span>Cliente</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-cyan-400 ring-1 ring-slate-900"></span>Instalación</span>
    @endif
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-300 ring-1 ring-slate-900"></span>En línea</span>
    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400 ring-1 ring-slate-900"></span>Pantalla apagada</span>
    <span class="inline-flex items-center gap-1.5 col-span-2"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-300 ring-1 ring-slate-900"></span>Sin señal (más de 90 s sin GPS)</span>
</div>
<p class="mt-1.5 text-[11px] text-slate-600">Hover en la moto: quién está de turno. Clic: lista de eventos si hay varios. Clic otra vez cierra.</p>
