@php
    $q = $quotaOverview;
    $input = 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
    $pending = $quotaRequests->where('status', \App\Models\QuotaRequest::PENDING);
@endphp

<section id="cupos" class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-white">Cupos y ampliaciones</h3>
            <p class="text-xs text-slate-500">Plan {{ $q['metal'] ?? '—' }}. Las ampliaciones se cobran por fuera del sistema.</p>
        </div>
        @if ($pending->isNotEmpty())
            <span class="ui-chip ui-chip-warning">{{ $pending->count() }} {{ $pending->count() === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' }}</span>
        @endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Instalaciones</p>
            <p class="text-white tabular-nums">{{ $q['used'] }} / {{ $q['capacity'] }}</p>
            @if ($q['breakdown'] !== '')
                <p class="text-[11px] text-slate-500 mt-1">{{ $q['breakdown'] }}</p>
            @endif
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Empleados activos</p>
            <p class="text-white tabular-nums">{{ $q['employees_used'] }} / {{ $q['employee_cap'] }}</p>
            <p class="text-[11px] text-slate-500 mt-1">Plan {{ $q['plan_employee_cap'] }} + {{ $q['extra_employees'] }} adicionales</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Puestos por instalación</p>
            <p class="text-white tabular-nums">{{ $q['posts_per_installation'] }}</p>
            <p class="text-[11px] text-slate-500 mt-1">Plan {{ $q['plan_posts_per_installation'] ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Empleados por puesto</p>
            <p class="text-white tabular-nums">{{ $q['employees_per_post'] }}</p>
            <p class="text-[11px] text-slate-500 mt-1">Plan {{ $q['plan_employees_per_post'] ?? '—' }}</p>
        </div>
    </div>

    @if ($q['lines'] !== [])
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach ($q['lines'] as $line)
                <span class="ui-chip">{{ $line['label'] }} {{ $line['used'] }}/{{ $line['capacity'] ?? '∞' }}</span>
            @endforeach
        </div>
    @endif

    @if ($quotaRequests->isNotEmpty())
        <div class="space-y-2">
            <p class="text-xs font-medium text-slate-300">Solicitudes de ampliación</p>
            @foreach ($quotaRequests as $req)
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-white">
                            +{{ $req->quantity }} · {{ $req->kind->label() }}
                            <span class="text-xs text-slate-500">· {{ $req->targetLabel() }} · {{ $req->created_at->format('d/m/Y H:i') }}{{ $req->requester ? ' · '.$req->requester->name : '' }}</span>
                        </p>
                        <span @class([
                            'ui-chip',
                            'ui-chip-warning' => $req->isPending(),
                            'ui-chip-success' => $req->status === \App\Models\QuotaRequest::APPROVED,
                            'ui-chip-danger' => $req->status === \App\Models\QuotaRequest::REJECTED,
                        ])>{{ $req->statusLabel() }}</span>
                    </div>
                    @if ($req->message)
                        <p class="text-xs text-slate-400 mt-1">{{ $req->message }}</p>
                    @endif
                    @if ($req->resolution_note)
                        <p class="text-xs text-slate-500 mt-1">Respuesta: {{ $req->resolution_note }}</p>
                    @endif
                    @can('platform.companies.manage')
                        @if ($req->isPending())
                            <form method="POST" class="mt-2 flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="text" name="resolution_note" maxlength="255" placeholder="Nota (cobro, acuerdo…)" class="{{ $input }} max-w-xs">
                                <button type="submit" formaction="{{ route('admin.companies.quota-requests.approve', [$company, $req]) }}" class="ui-chip ui-chip-success">Aprobar</button>
                                <button type="submit" formaction="{{ route('admin.companies.quota-requests.reject', [$company, $req]) }}" class="ui-chip ui-chip-danger">Rechazar</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @can('platform.companies.manage')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <form method="POST" action="{{ route('admin.companies.quotas.update', $company) }}" class="space-y-2 rounded-lg border border-slate-800 p-3">
                @csrf
                @method('PUT')
                <p class="text-xs font-medium text-slate-300">Toda la empresa</p>
                <label class="block text-[11px] text-slate-500">Empleados adicionales
                    <input type="number" min="0" name="extra_employees" value="{{ old('extra_employees', $q['extra_employees']) }}" class="{{ $input }}">
                </label>
                <label class="block text-[11px] text-slate-500">Puestos por instalación (vacío = plan)
                    <input type="number" min="0" name="posts_per_installation" value="{{ old('posts_per_installation', $company->posts_per_installation) }}" placeholder="{{ $q['plan_posts_per_installation'] }}" class="{{ $input }}">
                </label>
                <label class="block text-[11px] text-slate-500">Empleados por puesto (vacío = plan)
                    <input type="number" min="0" name="employees_per_post" value="{{ old('employees_per_post', $company->employees_per_post) }}" placeholder="{{ $q['plan_employees_per_post'] }}" class="{{ $input }}">
                </label>
                <label class="block text-[11px] text-slate-500">Nota
                    <input type="text" maxlength="255" name="quota_note" value="{{ old('quota_note', $q['quota_note']) }}" class="{{ $input }}">
                </label>
                <x-ui.button type="submit" size="sm">Guardar</x-ui.button>
            </form>

            <form method="POST" action="{{ route('admin.companies.quotas.installation', $company) }}" class="space-y-2 rounded-lg border border-slate-800 p-3">
                @csrf
                @method('PUT')
                <p class="text-xs font-medium text-slate-300">Excepción por instalación</p>
                <label class="block text-[11px] text-slate-500">Instalación
                    <select name="installation_id" class="{{ $input }}" required>
                        @foreach ($q['installations'] as $row)
                            <option value="{{ $row['id'] }}">{{ $row['label'] }} · {{ $row['posts'] }}/{{ $row['limit'] }}{{ $row['override'] !== null ? ' (excepción)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-[11px] text-slate-500">Puestos permitidos (vacío = quitar excepción)
                    <input type="number" min="0" name="max_posts" class="{{ $input }}">
                </label>
                <label class="block text-[11px] text-slate-500">Nota
                    <input type="text" maxlength="255" name="note" class="{{ $input }}">
                </label>
                <x-ui.button type="submit" size="sm" :disabled="$q['installations'] === []">Guardar</x-ui.button>
            </form>

            <form method="POST" action="{{ route('admin.companies.quotas.post', $company) }}" class="space-y-2 rounded-lg border border-slate-800 p-3">
                @csrf
                @method('PUT')
                <p class="text-xs font-medium text-slate-300">Excepción por puesto</p>
                <label class="block text-[11px] text-slate-500">Puesto
                    <select name="supervisor_post_id" class="{{ $input }}" required>
                        @foreach ($q['posts'] as $row)
                            <option value="{{ $row['id'] }}">{{ $row['label'] }} · {{ $row['assigned'] }}/{{ $row['limit'] }}{{ $row['override'] !== null ? ' (excepción)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-[11px] text-slate-500">Empleados asignables (vacío = quitar excepción)
                    <input type="number" min="0" name="max_employees" class="{{ $input }}">
                </label>
                <label class="block text-[11px] text-slate-500">Nota
                    <input type="text" maxlength="255" name="note" class="{{ $input }}">
                </label>
                <x-ui.button type="submit" size="sm" :disabled="$q['posts'] === []">Guardar</x-ui.button>
            </form>
        </div>

        @php
            $overrides = collect($q['installations'])->whereNotNull('override')->map(fn ($r) => $r['label'].': '.$r['override'].' puestos'.($r['note'] ? ' · '.$r['note'] : ''))
                ->merge(collect($q['posts'])->whereNotNull('override')->map(fn ($r) => $r['label'].': '.$r['override'].' empleados'.($r['note'] ? ' · '.$r['note'] : '')));
        @endphp
        @if ($overrides->isNotEmpty())
            <div>
                <p class="text-xs font-medium text-slate-300 mb-1">Excepciones vigentes</p>
                <ul class="text-xs text-slate-400 space-y-0.5">
                    @foreach ($overrides as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endcan
</section>
