@can('platform.companies.manage')
    @php
        $q = $quotaOverview;
        $input = 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white';
        $resolved = $quotaRequests->where('status', '!=', \App\Models\QuotaRequest::PENDING);
        $accessSeats = $company->accessSeats();
        $expandConfig = [
            'catalog' => $planCatalog,
            'cycle' => $company->billing_cycle?->value ?? 'monthly',
            'seats' => [
                'manual' => $accessSeats->manual,
                'hardware' => $accessSeats->hardware,
                'supervision' => (int) ($company->supervision_package_sku?->size() ?? 0),
                'indexing' => (int) ($company->indexing_seats ?? 0),
                'observatory' => (int) ($company->observatory_seats ?? 0),
            ],
            'today' => $company->contractedAmount(),
            'products' => collect($q['products'])->map(fn ($p) => ['key' => $p['key'], 'label' => $p['label'], 'seats' => (int) $p['seats']])->values(),
            'product' => old('product'),
            'quantity' => old('quantity', 1),
            'agreed' => old('agreed_amount'),
        ];
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
                        <p class="text-xs text-slate-500 mt-1">Amplía un producto del plan con su precio pactado, o ajusta los límites de puestos y empleados.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white" @click="quotaOpen = false" aria-label="Cerrar">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                </div>

                @if ($q['products'] !== [])
                    <form method="POST" action="{{ route('admin.companies.plan.expand', $company) }}" class="rounded-lg border border-slate-700 bg-slate-950/40 p-4 space-y-4"
                        x-data="quotaExpand(@js($expandConfig))">
                        @csrf
                        <input type="hidden" name="action_context" value="expand">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="text-sm font-medium text-white">Ampliar producto del plan</p>
                            <p class="text-[11px] text-slate-500">Hoy <span class="tabular-nums text-slate-300" x-text="money(today) + per"></span></p>
                        </div>
                        @if ($expandContext && $errors->any())
                            <div class="rounded-lg border border-rose-800/50 bg-rose-950/30 px-3 py-2 text-xs text-rose-200">
                                @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <template x-for="p in products" :key="p.key">
                                <label class="cursor-pointer rounded-lg border px-3 py-2 text-xs transition"
                                    :class="product === p.key ? 'border-cyan-500 bg-cyan-600/20 text-white' : 'border-slate-700 text-slate-300 hover:border-slate-500'">
                                    <input type="radio" name="product" class="sr-only" :value="p.key" x-model="product">
                                    <span class="font-medium" x-text="p.label"></span>
                                    <span class="ml-1 text-slate-400 tabular-nums" x-text="p.seats.toLocaleString('es-CO')"></span>
                                </label>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] text-slate-500">Cantidad a ampliar
                                    <input type="number" name="quantity" min="1" x-model.number="quantity" class="{{ $input }} mt-1" required>
                                </label>
                                <p class="mt-1.5 text-xs text-slate-400">
                                    Queda en
                                    <span class="tabular-nums text-white" x-text="countAfter.toLocaleString('es-CO')"></span>
                                    <span x-text="unit(countAfter)"></span>
                                    <span x-show="jumps" class="text-amber-300/90"> · pasa a <span x-text="metalAfter?.label"></span></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-500">Valor total <span x-text="per"></span>
                                    <input type="number" name="agreed_amount" min="0" step="1" x-model="agreed" @input="touched = true" class="{{ $input }} mt-1 tabular-nums" required>
                                </label>
                                <p class="mt-1.5 text-xs">
                                    <button type="button" class="text-cyan-300 tabular-nums hover:underline" @click="resetSuggested()" x-text="'Sugerido ' + money(suggested)"></button>
                                    <span class="ml-2 tabular-nums" :class="delta > 0 ? 'text-emerald-300' : (delta < 0 ? 'text-rose-300' : 'text-slate-500')"
                                        x-text="(delta >= 0 ? '+' : '−') + money(Math.abs(delta)) + ' vs hoy'"></span>
                                </p>
                            </div>
                        </div>

                        <label class="block text-[11px] text-slate-500">Motivo del acuerdo
                            <input type="text" name="expand_note" maxlength="255" required value="{{ old('expand_note') }}"
                                placeholder="Ej. acuerdo comercial con gerencia"
                                class="{{ $input }} mt-1">
                        </label>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-3">
                            <x-admin.apply-when :period-end="$company->package_ends_at" />
                            <x-ui.button type="submit" size="sm" x-bind:disabled="!line || add < 1">Ampliar</x-ui.button>
                        </div>
                    </form>
                @endif

                <div>
                    <p class="mb-2 text-xs font-medium text-slate-400">Límites de puestos y empleados <span class="font-normal text-slate-600">(sin cobro automático)</span></p>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <form method="POST" action="{{ route('admin.companies.quotas.update', $company) }}" class="space-y-2 rounded-lg border border-slate-800 p-3">
                            @csrf
                            @method('PUT')
                            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-300">
                                Toda la empresa
                                <x-ui.help-tip text="Topes globales. Empleados adicionales: suma al máximo de empleados de la empresa (plan + extras). Puestos por instalación: máximo de puestos de servicio (Supervisión) en cada instalación; lo da el plan más alto o este valor. Empleados por puesto: vigilantes asignables a cada puesto. No genera cobro; el cobro va en «Ampliar producto del plan»." />
                            </p>
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
                            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-300">
                                Excepción por instalación
                                <x-ui.help-tip text="Misma regla que «puestos por instalación», pero solo para una instalación. Ejemplo: el tope global es 10 y La Estación puede tener 17. Vacío quita la excepción. No cambia el cupo contratado de Accesos ni de Supervisión." />
                            </p>
                            <div class="text-[11px] text-slate-500">Instalación
                                <x-ui.search-select name="installation_id" required placeholder="Escribe para buscar instalación…"
                                    :options="collect($q['installations'])->map(fn ($row) => ['value' => $row['id'], 'label' => $row['label'], 'hint' => $row['posts'].'/'.$row['limit'].($row['override'] !== null ? ' · excepción' : '')])" />
                            </div>
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
                            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-300">
                                Excepción por puesto
                                <x-ui.help-tip text="Misma regla que «empleados por puesto», pero solo para un puesto de servicio. Ejemplo: el tope global es 10 y este puesto admite 15 vigilantes. Vacío quita la excepción. No genera cobro." />
                            </p>
                            <div class="text-[11px] text-slate-500">Puesto
                                <x-ui.search-select name="supervisor_post_id" required placeholder="Escribe para buscar puesto…"
                                    :options="collect($q['posts'])->map(fn ($row) => ['value' => $row['id'], 'label' => $row['label'], 'hint' => $row['assigned'].'/'.$row['limit'].($row['override'] !== null ? ' · excepción' : '')])" />
                            </div>
                            <label class="block text-[11px] text-slate-500">Empleados asignables (vacío = quitar excepción)
                                <input type="number" min="0" name="max_employees" class="{{ $input }}">
                            </label>
                            <label class="block text-[11px] text-slate-500">Nota
                                <input type="text" maxlength="255" name="note" class="{{ $input }}">
                            </label>
                            <x-ui.button type="submit" size="sm" :disabled="$q['posts'] === []">Guardar</x-ui.button>
                        </form>
                    </div>
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
