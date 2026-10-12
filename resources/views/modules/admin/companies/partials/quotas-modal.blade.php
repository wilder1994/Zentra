@can('platform.companies.manage')
    @php
        $q = $quotaOverview;
        $input = 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
        $resolved = $quotaRequests->where('status', '!=', \App\Models\QuotaRequest::PENDING);
        $overrides = collect($q['installations'])->whereNotNull('override')->map(fn ($r) => $r['label'].': '.$r['override'].' puestos'.($r['note'] ? ' · '.$r['note'] : ''))
            ->merge(collect($q['posts'])->whereNotNull('override')->map(fn ($r) => $r['label'].': '.$r['override'].' empleados'.($r['note'] ? ' · '.$r['note'] : '')));
    @endphp
    <div x-show="quotaOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" @keydown.escape.window="quotaOpen = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/60" @click="quotaOpen = false"></div>
            <div class="relative w-full max-w-4xl rounded-xl border border-slate-700 bg-slate-900 p-5 space-y-4" @click.stop>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Ampliar cupo</h3>
                        <p class="text-xs text-slate-500 mt-1">Vacío = lo que da el plan. Las ampliaciones se cobran por fuera del sistema.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white" @click="quotaOpen = false" aria-label="Cerrar">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <form method="POST" action="{{ route('admin.companies.quotas.update', $company) }}" class="space-y-2 rounded-lg border border-slate-800 p-3">
                        @csrf
                        @method('PUT')
                        <p class="text-xs font-medium text-slate-300">Toda la empresa</p>
                        <label class="block text-[11px] text-slate-500">Empleados adicionales
                            <input type="number" min="0" name="extra_employees" value="{{ old('extra_employees', $q['extra_employees']) }}" class="{{ $input }}">
                        </label>
                        <label class="block text-[11px] text-slate-500">Puestos por instalación
                            <input type="number" min="0" name="posts_per_installation" value="{{ old('posts_per_installation', $company->posts_per_installation) }}" placeholder="{{ $q['plan_posts_per_installation'] }}" class="{{ $input }}">
                        </label>
                        <label class="block text-[11px] text-slate-500">Empleados por puesto
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

                @if ($resolved->isNotEmpty())
                    <div>
                        <p class="text-xs font-medium text-slate-300 mb-1">Solicitudes resueltas</p>
                        <ul class="divide-y divide-slate-800 text-xs">
                            @foreach ($resolved as $req)
                                <li class="py-1.5 flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-slate-300">+{{ $req->quantity }} · {{ $req->kind->label() }} <span class="text-slate-500">· {{ $req->targetLabel() }} · {{ $req->created_at->format('d/m/Y') }}{{ $req->resolution_note ? ' · '.$req->resolution_note : '' }}</span></span>
                                    <span @class(['ui-chip', 'ui-chip-success' => $req->status === \App\Models\QuotaRequest::APPROVED, 'ui-chip-danger' => $req->status === \App\Models\QuotaRequest::REJECTED])>{{ $req->statusLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endcan
