@php
    $state = $auth->state();
    $badge = match ($state) {
        'active' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
        'upcoming' => 'bg-sky-500/15 text-sky-300 ring-sky-500/40',
        'used' => 'bg-indigo-500/15 text-indigo-300 ring-indigo-500/40',
        'cancelled' => 'bg-red-500/10 text-red-300 ring-red-500/30',
        default => 'bg-slate-700/40 text-slate-300 ring-slate-600',
    };
    $total = $auth->guests_count ?? $auth->guests->count();
    $entered = $auth->entered_count ?? $auth->guests->where('entries_count', '>', 0)->count();
@endphp
<div class="flex flex-col rounded-2xl border border-slate-800 bg-slate-900 p-4 transition hover:border-slate-600">
    <div class="flex items-start justify-between gap-2">
        <span class="rounded-md bg-slate-800 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">{{ $auth->typeLabel() }}</span>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $badge }}">{{ $auth->stateLabel() }}</span>
    </div>

    <a href="{{ $href }}" class="mt-3 block truncate text-base font-semibold text-white hover:text-indigo-300" title="{{ $auth->visitor_name }}">{{ $auth->visitor_name }}</a>

    <dl class="mt-3 space-y-1.5 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Destino</dt><dd class="truncate text-right text-slate-200">{{ $auth->destinationLabel() }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Autoriza</dt><dd class="truncate text-right text-slate-200">{{ $auth->authorizerLabel() }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Vigencia</dt><dd class="text-right text-slate-200">{{ $auth->validityLabel() }}</dd></div>
        @if ($auth->vehicle_plate)
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Placa</dt><dd class="font-mono text-slate-200">{{ $auth->vehicle_plate }}</dd></div>
        @endif
    </dl>

    <div class="mt-4 border-t border-slate-800 pt-3">
        <div class="flex items-center justify-between text-xs text-slate-400">
            <span>Ingresaron {{ $entered }} de {{ $total }}</span>
            <span>{{ $auth->multi_entry ? 'Entrada múltiple' : 'Una entrada' }}</span>
        </div>
        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-800">
            <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-400" style="width: {{ $total > 0 ? round($entered / $total * 100) : 0 }}%"></div>
        </div>
    </div>

    @if (! empty($ingresarUrl))
        <a href="{{ $ingresarUrl }}" class="mt-4 inline-flex justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Ingresar</a>
    @endif
</div>
