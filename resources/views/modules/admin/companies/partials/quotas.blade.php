@php
    $q = $quotaOverview;
    $input = 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
    $pending = $quotaRequests->where('status', \App\Models\QuotaRequest::PENDING);
    $num = fn (int $n) => number_format($n, 0, ',', '.');
    $cards = [
        ['label' => 'Puestos por instalación', 'value' => $num((int) $q['posts_per_installation']), 'hint' => 'plan '.($q['plan_posts_per_installation'] ?? '—')],
        ['label' => 'Empleados por puesto', 'value' => $num((int) $q['employees_per_post']), 'hint' => 'plan '.($q['plan_employees_per_post'] ?? '—')],
        ['label' => 'Empleados totales', 'value' => $num((int) $q['employees_used']).' / '.$num((int) $q['employee_cap']), 'hint' => 'plan '.$num((int) $q['plan_employee_cap']).((int) $q['extra_employees'] > 0 ? ' + '.$num((int) $q['extra_employees']) : '')],
    ];
@endphp

<section id="cupos" class="flex w-full flex-col gap-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4 min-w-0">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-white">Cupos y ampliaciones</h3>
            <p class="text-xs text-slate-500">Uso frente a lo contratado.</p>
        </div>
        @can('platform.companies.manage')
            <x-ui.button type="button" size="sm" @click="quotaOpen = true">Ampliar cupo</x-ui.button>
        @endcan
    </div>

    <div class="flex flex-1 flex-col justify-center gap-4">
        @forelse ($q['products'] as $bar)
            @php($pct = $bar['capacity'] ? (int) min(100, round($bar['used'] / $bar['capacity'] * 100)) : 0)
            <div>
                <div class="flex items-baseline justify-between gap-2 text-xs">
                    <span class="text-slate-300">{{ $bar['label'] }}</span>
                    <span class="tabular-nums text-white">
                        {{ $num((int) $bar['used']) }} / {{ $bar['capacity'] !== null ? $num((int) $bar['capacity']) : 'sin tope' }}
                        <span class="text-slate-500">{{ $bar['unit'] }}</span>
                    </span>
                </div>
                <div class="mt-1 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                    <div @class(['h-full rounded-full', 'bg-rose-500' => $pct >= 90, 'bg-amber-400' => $pct >= 75 && $pct < 90, 'bg-cyan-500' => $pct < 75]) style="width: {{ max($pct, $pct > 0 ? 2 : 0) }}%"></div>
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-sm text-slate-500">Sin productos contratados.</p>
        @endforelse
    </div>

    <div class="grid grid-cols-3 gap-2 text-xs">
        @foreach ($cards as $card)
            <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-2.5 py-2 min-w-0">
                <p class="text-[11px] leading-tight text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-sm text-white tabular-nums whitespace-nowrap">{{ $card['value'] }}</p>
                <p class="text-[11px] text-slate-500">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($pending->isNotEmpty())
        <div class="space-y-2">
            <p class="text-xs font-medium text-amber-300">{{ $pending->count() }} {{ $pending->count() === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' }}</p>
            @foreach ($pending as $req)
                <div class="rounded-lg border border-amber-800/40 bg-amber-950/10 p-3 text-sm">
                    <p class="text-white">
                        +{{ $req->quantity }} · {{ $req->kind->label() }}
                        <span class="text-xs text-slate-500">· {{ $req->targetLabel() }} · {{ $req->created_at->format('d/m/Y') }}{{ $req->requester ? ' · '.$req->requester->name : '' }}</span>
                    </p>
                    @if ($req->message)
                        <p class="text-xs text-slate-400 mt-1">{{ $req->message }}</p>
                    @endif
                    @can('platform.companies.manage')
                        <form method="POST" class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="resolution_note" maxlength="255" placeholder="Nota (cobro, acuerdo…)" class="{{ $input }} max-w-xs">
                            <button type="submit" formaction="{{ route('admin.companies.quota-requests.approve', [$company, $req]) }}" class="ui-chip ui-chip-success">Aprobar</button>
                            <button type="submit" formaction="{{ route('admin.companies.quota-requests.reject', [$company, $req]) }}" class="ui-chip ui-chip-danger">Rechazar</button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif
</section>
