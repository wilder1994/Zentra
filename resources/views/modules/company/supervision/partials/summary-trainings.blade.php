@php
    $t = $training + ['total' => 0, 'people' => 0, 'monthly' => 0, 'custom' => 0, 'virtual' => 0, 'onsite' => 0, 'minutes' => 0, 'coverage' => ['reviewed' => 0, 'covered' => 0], 'people_rows' => [], 'by_topic' => []];
    $u = $uniform + ['inspected' => 0, 'novelty' => 0, 'reviews' => 0, 'by_item' => [], 'by_people' => [], 'people_count' => 0, 'rows' => []];
    $coverage = $t['coverage']['reviewed'] > 0 ? round($t['coverage']['covered'] / $t['coverage']['reviewed'] * 100) : null;
    $uniformPct = $u['inspected'] > 0 ? round($u['novelty'] / $u['inspected'] * 100) : null;
    $hours = intdiv((int) $t['minutes'], 60);
    $mins = (int) $t['minutes'] % 60;
    $kpis = [
        ['label' => 'Capacitaciones', 'value' => $t['total'], 'tone' => 'text-white'],
        ['label' => 'Personal capacitado', 'value' => $t['people'], 'tone' => 'text-teal-300'],
        ['label' => 'Mensuales', 'value' => $t['monthly'], 'tone' => 'text-sky-300'],
        ['label' => 'Personalizadas', 'value' => $t['custom'], 'tone' => 'text-violet-300'],
        ['label' => 'Virtual', 'value' => $t['virtual'], 'tone' => 'text-slate-200'],
        ['label' => 'En puesto', 'value' => $t['onsite'], 'tone' => 'text-slate-200'],
        ['label' => 'Tiempo en puesto', 'value' => $hours > 0 ? $hours.' h '.$mins.' min' : $mins.' min', 'tone' => 'text-amber-300'],
    ];
@endphp

<section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Capacitaciones</h3>
        <p class="text-[11px] text-slate-500">Dadas al vigilante en la revista. Un tema por vigilante al mes.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
        @foreach ($kpis as $kpi)
            <div class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wide text-slate-500 leading-tight">{{ $kpi['label'] }}</p>
                <p class="mt-0.5 text-lg font-semibold tabular-nums {{ $kpi['tone'] }}">{{ $kpi['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-3">
        <div class="lg:col-span-2 rounded-lg border border-slate-800 bg-slate-950/40 p-3">
            <h4 class="text-sm font-semibold text-white">Capacitaciones {{ $grainLabel }}</h4>
            <p class="text-[11px] text-slate-500">Por tipo (mensual / personalizada) y modalidad (virtual / en puesto).</p>
            <div class="h-56 mt-2"><canvas id="chart-trainings-period"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
            <h4 class="text-sm font-semibold text-white">Cobertura mensual</h4>
            <p class="text-[11px] text-slate-500">Vigilantes con revista en el periodo que recibieron capacitación mensual.</p>
            <div class="relative h-48 mt-2">
                <canvas id="chart-trainings-coverage"></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center pb-6">
                    <span class="text-2xl font-semibold text-white tabular-nums">{{ $coverage !== null ? $coverage.'%' : '—' }}</span>
                    <span class="text-[11px] text-slate-400">{{ $t['coverage']['covered'] }} de {{ $t['coverage']['reviewed'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-5 gap-3">
        <div class="lg:col-span-2 rounded-lg border border-slate-800 bg-slate-950/40 p-3">
            <h4 class="text-sm font-semibold text-white">Por tema</h4>
            <p class="text-[11px] text-slate-500">Los 10 temas más dados, mensual y personalizada.</p>
            <div class="mt-2" style="height: {{ max(160, count($t['by_topic']) * 30 + 50) }}px"><canvas id="chart-trainings-topics"></canvas></div>
        </div>
        <div class="lg:col-span-3 rounded-lg border border-slate-800 bg-slate-950/40 p-3"
             x-data="{ q: '', kind: '', modality: '', rows: @js($t['people_rows']),
                       fold(s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); },
                       get list() { const q = this.fold(this.q); return this.rows.filter((r) => (!this.kind || r.kind === this.kind) && (!this.modality || r.modality === this.modality) && (!q || this.fold(r.employee + ' ' + r.topic).includes(q))); } }">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h4 class="text-sm font-semibold text-white">Personal capacitado · {{ $t['people'] }}</h4>
                <span class="text-[11px] text-slate-500" x-text="list.length + ' de ' + rows.length + ' capacitaciones'"></span>
            </div>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                <input type="search" x-model="q" placeholder="Vigilante o tema…" class="h-8 rounded-md border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                <select x-model="kind" class="h-8 rounded-md border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                    <option value="">Todos los tipos</option>
                    <option value="monthly">Mensual</option>
                    <option value="custom">Personalizada</option>
                </select>
                <select x-model="modality" class="h-8 rounded-md border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                    <option value="">Todas las modalidades</option>
                    <option value="virtual">Virtual</option>
                    <option value="onsite">En puesto</option>
                </select>
            </div>
            <div class="mt-2 max-h-80 overflow-y-auto">
                <table class="w-full text-xs">
                    <thead class="text-[10px] uppercase text-slate-500 sticky top-0 bg-slate-950">
                        <tr>
                            <th class="text-left font-medium py-1.5 pr-2">Vigilante</th>
                            <th class="text-left font-medium py-1.5 pr-2">Tema</th>
                            <th class="text-left font-medium py-1.5 pr-2">Tipo</th>
                            <th class="text-right font-medium py-1.5">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-300">
                        <template x-for="(row, i) in list" :key="i">
                            <tr class="border-t border-slate-800 align-top">
                                <td class="py-1.5 pr-2 text-slate-100" x-text="row.employee"></td>
                                <td class="py-1.5 pr-2">
                                    <span x-text="row.topic"></span>
                                    <span x-show="row.reason" class="block text-[10px] text-slate-500" x-text="'Motivo: ' + row.reason"></span>
                                </td>
                                <td class="py-1.5 pr-2 whitespace-nowrap">
                                    <span :class="row.kind === 'custom' ? 'text-violet-300' : 'text-sky-300'" x-text="row.kind_label"></span>
                                    <span class="block text-[10px] text-slate-500" x-text="row.modality_label + (row.minutes ? ' · ' + row.minutes + ' min' : '')"></span>
                                </td>
                                <td class="py-1.5 text-right tabular-nums whitespace-nowrap" x-text="row.date"></td>
                            </tr>
                        </template>
                        <tr x-show="list.length === 0"><td colspan="4" class="py-4 text-slate-500">Sin capacitaciones con ese filtro.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Presentación personal con novedad</h3>
        <p class="text-[11px] text-slate-500">Solo lo que salió con novedad: prendas, vigilantes y la observación del supervisor.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 lg:max-w-xl">
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">Revistas con novedad</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums {{ $u['novelty'] > 0 ? 'text-orange-300' : 'text-white' }}">{{ $u['novelty'] }}</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">De las revisadas</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums text-white">{{ $uniformPct !== null ? $uniformPct.'%' : '—' }}</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">Vigilantes con novedad</p>
            <p class="mt-0.5 text-lg font-semibold tabular-nums text-white">{{ $u['people_count'] }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-3">
        <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
            <h4 class="text-sm font-semibold text-white">Prendas con más novedades</h4>
            <div class="mt-2" style="height: {{ max(150, count($u['by_item']) * 28 + 40) }}px"><canvas id="chart-uniform-items"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
            <h4 class="text-sm font-semibold text-white">Vigilantes con más novedades</h4>
            <div class="mt-2" style="height: {{ max(150, count($u['by_people']) * 28 + 40) }}px"><canvas id="chart-uniform-people"></canvas></div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
        <h4 class="text-sm font-semibold text-white">Novedades de presentación personal · {{ count($u['rows']) }}</h4>
        <div class="mt-2 max-h-80 overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="text-[10px] uppercase text-slate-500 sticky top-0 bg-slate-950">
                    <tr>
                        <th class="text-left font-medium py-1.5 pr-2">Vigilante</th>
                        <th class="text-left font-medium py-1.5 pr-2">Prendas con novedad</th>
                        <th class="text-left font-medium py-1.5 pr-2">Observación del supervisor</th>
                        <th class="text-right font-medium py-1.5">Fecha</th>
                    </tr>
                </thead>
                <tbody class="text-slate-300">
                    @forelse ($u['rows'] as $row)
                        <tr class="border-t border-slate-800 align-top">
                            <td class="py-1.5 pr-2 text-slate-100">{{ $row['employee'] }}<span class="block text-[10px] text-slate-500">{{ $row['client'] }}</span></td>
                            <td class="py-1.5 pr-2 text-orange-300">{{ $row['items'] }}</td>
                            <td class="py-1.5 pr-2 text-slate-400">{{ $row['note'] ?: '—' }}<span class="block text-[10px] text-slate-500">{{ $row['supervisor'] }}</span></td>
                            <td class="py-1.5 text-right tabular-nums whitespace-nowrap">{{ $row['date'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-slate-500">Sin novedades de presentación personal en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
