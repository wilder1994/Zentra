<form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="registros">
    <label class="min-w-[14rem] flex-1">
        <span class="block text-xs text-slate-500">Buscar</span>
        <input type="search" name="q" value="{{ $q }}" placeholder="Nombre, cédula o placa"
               class="mt-1 h-9 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white">
    </label>
    @include('modules.company.supervision.partials.period-picker', ['from' => $from, 'to' => $to])
    <label class="w-44">
        <span class="block text-xs text-slate-500">Tipo</span>
        <select name="scope" class="mt-1 h-9 w-full rounded-lg border-slate-700 bg-slate-950 py-0 text-sm text-white">
            <option value="all" @selected($scope === 'all')>Todos</option>
            <option value="member" @selected($scope === 'member')>Censo · persona</option>
            <option value="visitor" @selected($scope === 'visitor')>Visitante · persona</option>
            <option value="member_vehicle" @selected($scope === 'member_vehicle')>Censo · vehículo</option>
            <option value="visitor_vehicle" @selected($scope === 'visitor_vehicle')>Visitante · vehículo</option>
        </select>
    </label>
    <label class="w-44">
        <span class="block text-xs text-slate-500">Vigilante</span>
        <select name="host_id" class="mt-1 h-9 w-full rounded-lg border-slate-700 bg-slate-950 py-0 text-sm text-white">
            <option value="">Todos</option>
            @foreach($guards as $guard)
                <option value="{{ $guard->id }}" @selected($hostId === (int) $guard->id)>{{ $guard->name }}</option>
            @endforeach
        </select>
    </label>
    <button class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">Filtrar</button>
</form>

<div x-data="accessLogHistory(@js(route('access.logs.history')))">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($logs as $log)
            @php
                $isVehicle = $log->vehicle_id !== null;
                $kind = $isVehicle ? 'vehicle' : ($log->structure_member_id ? 'member' : ($log->visitor_id ? 'visitor' : null));
                $subjectId = $isVehicle ? $log->vehicle_id : ($log->structure_member_id ?: $log->visitor_id);
                $visits = $kind ? ($visitCounts[$kind.'-'.$subjectId] ?? 1) : 1;
                $photo = $log->fichaPhotoUrl();
                $inside = $log->status === 'active';
                $title = $isVehicle ? ($log->vehicle?->plate ?? 'Vehículo') : $log->subjectName();
                $exitLabel = $log->exit_time
                    ? $log->exit_time->format($log->entry_time && $log->exit_time->isSameDay($log->entry_time) ? 'H:i' : 'd/m H:i')
                    : '—';
                $census = $log->isCensusMovement();
                $destination = $census ? $log->censusUnitLabel() : $log->destinationLabel();
                $subtitle = $census && $isVehicle ? $log->censusUnitLabel() : $log->subjectName();
                $authorizer = $log->authorizerLabel();
                $guardName = $log->host?->name ?? '—';
            @endphp
            <article class="flex overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                <div class="flex w-24 shrink-0 items-center justify-center bg-slate-950 sm:w-28">
                    @if($photo)
                        <img src="{{ $photo }}" alt="" class="aspect-[3/4] h-full w-full object-cover">
                    @else
                        <svg class="h-10 w-10 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            @if($isVehicle)
                                <path d="M5 16h14M6 16l1.5-5h9L18 16M7 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm10 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
                            @else
                                <circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>
                            @endif
                        </svg>
                    @endif
                </div>
                <div class="w-px shrink-0 bg-gradient-to-b from-transparent via-indigo-500/70 to-transparent"></div>
                <div class="flex min-w-0 flex-1 flex-col p-3">
                    <p class="truncate text-sm font-semibold text-white" title="{{ $title }}">{{ $title }}</p>
                    @if($isVehicle)
                        <p class="truncate text-[11px] text-slate-400" title="{{ $subtitle }}">{{ $subtitle }}</p>
                    @endif
                    <div class="mt-0.5 flex items-center justify-between gap-1">
                        <span class="truncate text-[11px] text-slate-500">{{ $log->movementLabel() }}</span>
                        <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $inside ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/40 text-slate-400' }}">
                            {{ $inside ? 'Dentro' : 'Salió' }}
                        </span>
                    </div>
                    <dl class="mt-2 space-y-0.5 text-[11px] leading-snug">
                        <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Hora</dt><dd class="truncate text-slate-200">{{ $log->entry_time?->format('d/m H:i') ?? '—' }} → {{ $exitLabel }}</dd></div>
                        <div class="flex gap-1"><dt class="shrink-0 text-slate-500">{{ $census ? 'Unidad' : 'Destino' }}</dt><dd class="truncate text-slate-200" title="{{ $destination }}">{{ $destination }}</dd></div>
                        @unless($census)
                            <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Autoriza</dt><dd class="truncate text-slate-200" title="{{ $authorizer }}">{{ $authorizer }}</dd></div>
                        @endunless
                        <div class="flex gap-1"><dt class="shrink-0 text-slate-500">Vigilante</dt><dd class="truncate text-slate-200" title="{{ $guardName }}">{{ $guardName }}</dd></div>
                    </dl>
                    <div class="mt-auto flex gap-1.5 pt-2">
                        @if($kind)
                            <button type="button" @click="openFor(@js($kind), {{ (int) $subjectId }}, @js($title))"
                                    class="rounded-md border border-slate-700 px-2 py-1 text-[11px] font-semibold text-slate-200 hover:bg-slate-800">
                                Historial ({{ $visits }})
                            </button>
                        @endif
                        @if($inside)
                            @can('access.register.exit')
                                <form method="POST" action="{{ route('access.logs.exit', $log) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-md px-2 py-1 text-[11px] font-semibold text-white {{ $census ? 'bg-emerald-600' : 'bg-amber-600' }}">Dar salida</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-xl border border-slate-800 bg-slate-900 px-4 py-10 text-center text-sm text-slate-500">Sin movimientos en el periodo.</p>
        @endforelse
    </div>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-slate-950/70" @click="open = false"></div>
            <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-800 p-4">
                    <div>
                        <h3 class="text-base font-semibold text-white" x-text="'Historial · ' + title"></h3>
                        <p class="text-xs text-slate-400" x-show="data">
                            <span x-text="data?.total ?? 0"></span> <span x-text="data?.census ? 'ingresos en total' : 'visitas en total'"></span>
                            <template x-if="data && data.census && data.unit">
                                <span> · Unidad: <span x-text="data.unit"></span></span>
                            </template>
                            <template x-if="data && data.top_destinations.length">
                                <span> · Destinos frecuentes: <span x-text="data.top_destinations.map(d => d.name + ' (' + d.count + ')').join(', ')"></span></span>
                            </template>
                        </p>
                    </div>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                </div>
                <div class="overflow-y-auto p-4">
                    <p x-show="loading" class="text-sm text-slate-400">Cargando…</p>
                    <p x-show="error" class="text-sm text-red-400" x-text="error"></p>
                    <ul class="space-y-2" x-show="data && !loading">
                        <template x-for="(item, i) in (data?.items ?? [])" :key="i">
                            <li class="rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-200" x-text="item.entry + ' → ' + (item.exit ?? 'sin salida')"></span>
                                    <span class="rounded-full px-2 py-0.5 font-semibold"
                                          :class="item.active ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/40 text-slate-400'"
                                          x-text="item.active ? 'Dentro' : 'Salió'"></span>
                                </div>
                                <p class="mt-1 text-slate-400">
                                    <span x-text="item.census ? 'Unidad: ' : 'Destino: '"></span><span class="text-slate-200" x-text="item.destination"></span>
                                    <template x-if="!item.census"><span> · Autoriza: <span class="text-slate-200" x-text="item.authorizer"></span></span></template>
                                    · Vigilante: <span class="text-slate-200" x-text="item.guard"></span>
                                    <template x-if="item.door"><span> · Puerta: <span class="text-slate-200" x-text="item.door"></span></span></template>
                                </p>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </template>
</div>

<div class="mt-4">{{ $logs->links() }}</div>

@once
    @push('scripts')
        <script>
            function accessLogHistory(url) {
                return {
                    open: false, loading: false, error: '', data: null, title: '',
                    async openFor(kind, id, title) {
                        this.open = true; this.loading = true; this.error = ''; this.data = null; this.title = title;
                        try {
                            const res = await fetch(url + '?kind=' + encodeURIComponent(kind) + '&id=' + id, { headers: { Accept: 'application/json' } });
                            if (!res.ok) throw new Error();
                            this.data = await res.json();
                        } catch (e) {
                            this.error = 'No se pudo cargar el historial.';
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
