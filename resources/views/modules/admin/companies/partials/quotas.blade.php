@php
    $q = $quotaOverview;
    $input = 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
    $pending = $quotaRequests->where('status', \App\Models\QuotaRequest::PENDING);
    $bars = [
        ['label' => 'Instalaciones', 'used' => (int) $q['used'], 'cap' => (int) $q['capacity'], 'hint' => $q['breakdown'] !== '' ? $q['breakdown'] : null],
        ['label' => 'Empleados activos', 'used' => (int) $q['employees_used'], 'cap' => (int) $q['employee_cap'], 'hint' => 'Plan '.number_format((int) $q['plan_employee_cap'], 0, ',', '.').((int) $q['extra_employees'] > 0 ? ' + '.$q['extra_employees'].' adicionales' : '')],
    ];
@endphp

<section id="cupos" class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4 min-w-0">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-white">Cupos y ampliaciones</h3>
            <p class="text-xs text-slate-500">Las ampliaciones se cobran por fuera del sistema.</p>
        </div>
        @can('platform.companies.manage')
            <x-ui.button type="button" variant="secondary" size="sm" @click="quotaOpen = true">Ampliar cupo</x-ui.button>
        @endcan
    </div>

    <div class="space-y-3">
        @foreach ($bars as $bar)
            @php($pct = $bar['cap'] > 0 ? (int) min(100, round($bar['used'] / $bar['cap'] * 100)) : 0)
            <div>
                <div class="flex items-baseline justify-between gap-2 text-xs">
                    <span class="text-slate-400">{{ $bar['label'] }}</span>
                    <span class="tabular-nums text-white">{{ number_format($bar['used'], 0, ',', '.') }} / {{ number_format($bar['cap'], 0, ',', '.') }}</span>
                </div>
                <div class="mt-1 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                    <div @class(['h-full rounded-full', 'bg-rose-500' => $pct >= 90, 'bg-amber-400' => $pct >= 75 && $pct < 90, 'bg-cyan-500' => $pct < 75]) style="width: {{ max($pct, $pct > 0 ? 2 : 0) }}%"></div>
                </div>
                @if ($bar['hint'])
                    <p class="mt-0.5 text-[11px] text-slate-500">{{ $bar['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2">
            <p class="text-slate-500">Puestos por instalación</p>
            <p class="text-sm text-white tabular-nums">{{ $q['posts_per_installation'] }} <span class="text-[11px] text-slate-500">· plan {{ $q['plan_posts_per_installation'] ?? '—' }}</span></p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2">
            <p class="text-slate-500">Empleados por puesto</p>
            <p class="text-sm text-white tabular-nums">{{ $q['employees_per_post'] }} <span class="text-[11px] text-slate-500">· plan {{ $q['plan_employees_per_post'] ?? '—' }}</span></p>
        </div>
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
