@php
    $q = $quotaOverview;
    $input = 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
@endphp

<section id="cupos" class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-white">Cupos de tu plan{{ $q['metal'] ? ' · '.$q['metal'] : '' }}</h3>
            <p class="text-xs text-slate-500 mt-0.5">Más instalaciones: «Cambiar plan». Más empleados, puestos o asignaciones: «Solicitar ampliación».</p>
        </div>
        <x-ui.button type="button" variant="secondary" size="sm" @click="quotaOpen = true">Solicitar ampliación</x-ui.button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Instalaciones</p>
            <p class="text-white tabular-nums">{{ $q['used'] }} de {{ $q['capacity'] }}</p>
            @if ($q['breakdown'] !== '')
                <p class="text-[11px] text-slate-500 mt-1">{{ $q['breakdown'] }}</p>
            @endif
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Empleados activos</p>
            <p class="text-white tabular-nums">{{ $q['employees_used'] }} de {{ $q['employee_cap'] }}</p>
            @if ($q['extra_employees'] > 0)
                <p class="text-[11px] text-slate-500 mt-1">Incluye {{ $q['extra_employees'] }} adicionales</p>
            @endif
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Puestos por instalación</p>
            <p class="text-white tabular-nums">Hasta {{ $q['posts_per_installation'] }}</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Empleados por puesto</p>
            <p class="text-white tabular-nums">Hasta {{ $q['employees_per_post'] }}</p>
        </div>
    </div>

    @if ($q['lines'] !== [])
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach ($q['lines'] as $line)
                <span class="ui-chip">{{ $line['label'] }}: {{ $line['used'] }} de {{ $line['capacity'] ?? '∞' }} instalaciones</span>
            @endforeach
        </div>
    @endif

    @if ($quotaRequests->isNotEmpty())
        <div class="space-y-1">
            <p class="text-xs font-medium text-slate-300">Tus solicitudes</p>
            @foreach ($quotaRequests as $req)
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2">
                    <span class="text-slate-300">+{{ $req->quantity }} · {{ $req->kind->label() }} · {{ $req->targetLabel() }} · {{ $req->created_at->format('d/m/Y') }}</span>
                    <span @class([
                        'ui-chip',
                        'ui-chip-warning' => $req->isPending(),
                        'ui-chip-success' => $req->status === \App\Models\QuotaRequest::APPROVED,
                        'ui-chip-danger' => $req->status === \App\Models\QuotaRequest::REJECTED,
                    ])>{{ $req->statusLabel() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</section>

<div x-show="quotaOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-black/60" @click="quotaOpen = false"></div>
        <div class="relative w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5" @click.stop x-data="{ kind: '{{ old('kind', 'employees') }}' }">
            <h3 class="text-sm font-semibold text-white">Solicitar ampliación</h3>
            <p class="text-xs text-slate-500 mt-1">El equipo Zentra la revisa y te contacta para el cobro. Al aprobarla, el cupo queda disponible de inmediato.</p>
            <form method="POST" action="{{ route('company.quota-requests.store') }}" class="mt-4 space-y-3">
                @csrf
                <label class="block text-xs text-slate-400">Qué necesitas
                    <select name="kind" x-model="kind" class="{{ $input }}">
                        @foreach (\App\Enums\QuotaRequestKind::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs text-slate-400" x-show="kind === 'posts'">Instalación
                    <select name="installation_id" class="{{ $input }}" :disabled="kind !== 'posts'">
                        <option value="">Todas las instalaciones</option>
                        @foreach ($q['installations'] as $row)
                            @if ($row['is_active'])
                                <option value="{{ $row['id'] }}">{{ $row['label'] }} ({{ $row['posts'] }}/{{ $row['limit'] }})</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs text-slate-400" x-show="kind === 'assignments'">Puesto
                    <select name="supervisor_post_id" class="{{ $input }}" :disabled="kind !== 'assignments'">
                        <option value="">Todos los puestos</option>
                        @foreach ($q['posts'] as $row)
                            @if ($row['is_active'])
                                <option value="{{ $row['id'] }}">{{ $row['label'] }} ({{ $row['assigned'] }}/{{ $row['limit'] }})</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs text-slate-400">Cantidad adicional
                    <input type="number" name="quantity" min="1" max="10000" required value="{{ old('quantity', 1) }}" class="{{ $input }}">
                    <x-ui.field-error name="quantity" />
                </label>
                <label class="block text-xs text-slate-400">Comentario (opcional)
                    <input type="text" name="message" maxlength="255" value="{{ old('message') }}" class="{{ $input }}">
                </label>
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" size="sm" @click="quotaOpen = false">Cancelar</x-ui.button>
                    <x-ui.button type="submit" size="sm">Enviar solicitud</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
