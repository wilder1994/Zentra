@php
    $fmt = fn (float $n) => '$'.number_format($n, 0, ',', '.');
    $period = $cycle->value === 'annual' ? '/año' : '/mes';
    $pct = fn (float $d) => number_format($d * 100, 0).'%';
    $availability = $catalog['availability'] ?? [];
    $byMetal = collect($accessMatrix)->keyBy('metal');
    $accessUnit = (float) ($catalog['units']['access'] ?? 0);
    $example = function (int $seats) use ($byMetal) {
        $metal = \App\Enums\CommercialMetal::fromSeats($seats);
        $row = $byMetal[$metal->value];

        return ['seats' => $seats, 'label' => $row['label'], 'discount' => $row['discount'], 'unit' => (float) $row['unit_net'], 'total' => (float) $row['unit_net'] * $seats];
    };
    $examples = [$example(5), $example(6), $example(16), $example(120)];
@endphp

<x-admin-layout title="Planes">
    <x-slot:actions>
        <x-ui.button
            :variant="$cycle->value === 'monthly' ? 'platform' : 'secondary'"
            :href="route('admin.pricing.edit', ['cycle' => 'monthly'])"
            size="sm">Mensual</x-ui.button>
        <x-ui.button
            :variant="$cycle->value === 'annual' ? 'platform' : 'secondary'"
            :href="route('admin.pricing.edit', ['cycle' => 'annual'])"
            size="sm">Anual (−{{ number_format($annualDiscount * 100, 0) }}%)</x-ui.button>
        <x-ui.button type="button" variant="secondary" size="sm" onclick="document.getElementById('plans-help').showModal()" title="¿Cómo se calcula?">?</x-ui.button>
        @can('platform.companies.manage')
            <x-ui.button type="button" variant="secondary" size="sm" onclick="document.getElementById('increase-modal').showModal()">
                Incremento anual
            </x-ui.button>
            <x-ui.button type="button" variant="platform" size="sm" onclick="document.getElementById('catalog-modal').showModal()">
                Editar planes
            </x-ui.button>
        @endcan
    </x-slot:actions>

    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2 text-sm text-slate-400">
            <span>Bronce 1–5 · Plata 6–15 · Oro 16–40 · Platino 41–100 (más de 100 a precio Platino).</span>
            <span>Cada instalación = unidad × (1 − descuento del plan).</span>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Enums\CommercialMetal::cases() as $metal)
                <span class="ui-chip {{ ($availability[$metal->value] ?? true) ? 'ui-chip-success' : 'ui-chip-danger' }}">
                    {{ $metal->label() }} · {{ ($availability[$metal->value] ?? true) ? 'Disponible' : 'No disponible' }}
                </span>
            @endforeach
            <span class="ui-chip {{ ($availability['hardware'] ?? false) ? 'ui-chip-success' : 'ui-chip-danger' }}">
                Hardware · {{ ($availability['hardware'] ?? false) ? 'Disponible' : 'No disponible' }}
            </span>
            @if ($lockedCount > 0)
                <span class="ui-chip ui-chip-accent">{{ $lockedCount }} con precio pactado</span>
            @endif
            @if ($pendingCount > 0)
                <span class="ui-chip ui-chip-caution">{{ $pendingCount }} con nuevo valor programado</span>
            @endif
        </div>

        @foreach ([
            ['Accesos', $accessMatrix, 'Por instalación. Hardware: unidad '.$fmt((float) ($catalog['units']['access_hardware'] ?? 0)).' con el mismo descuento.'],
            ['Supervisión', $supervisionMatrix, 'Por cliente supervisado. Se puede contratar sola.'],
            ['Indexación (sola)', $indexingMatrix, 'Por instalación, sin Accesos ni Supervisión.'],
            ['Indexación (add-on)', $indexingAddonMatrix, 'Con Accesos o Supervisión. Solo carpetas.'],
        ] as [$title, $rows, $hint])
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-800">
                    <h3 class="text-sm font-semibold text-white">{{ $title }} · {{ $cycle->label() }}</h3>
                    <p class="text-xs text-slate-500">{{ $hint }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Plan</th>
                                <th class="px-4 py-2 text-left font-medium">Instalaciones</th>
                                <th class="px-4 py-2 text-left font-medium">Empleados</th>
                                <th class="px-4 py-2 text-left font-medium">Dto.</th>
                                <th class="px-4 py-2 text-right font-medium">Por instalación</th>
                                <th class="px-4 py-2 text-right font-medium">Desde</th>
                                <th class="px-4 py-2 text-right font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($rows as $row)
                                <tr @class(['opacity-60' => ! $row['available']])>
                                    <td class="px-4 py-2.5 font-medium text-slate-200">{{ $row['label'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-400">{{ $row['range'] }}</td>
                                    <td class="px-4 py-2.5 text-slate-400 tabular-nums">{{ number_format($row['employees']) }}</td>
                                    <td class="px-4 py-2.5 text-emerald-400">−{{ $pct($row['discount']) }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium text-white tabular-nums">{{ $fmt((float) $row['unit_amount']) }}{{ $period }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-400 tabular-nums">{{ $fmt((float) $row['from_amount']) }}{{ $period }} ({{ $row['min'] }})</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="ui-chip {{ $row['available'] ? 'ui-chip-success' : 'ui-chip-danger' }}">{{ $row['available'] ? 'Disponible' : 'No disponible' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <section class="rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Observatorio · un módulo</h3>
                <p class="text-xs text-slate-500">Solo con Accesos. El precio depende del plan de Accesos.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Si Accesos es…</th>
                            <th class="px-4 py-2 text-right font-medium">Observatorio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($observatoryMatrix as $row)
                            <tr>
                                <td class="px-4 py-2.5 text-slate-200">{{ $row['label'] }}</td>
                                <td class="px-4 py-2.5 text-right font-medium text-white tabular-nums">{{ $fmt((float) $row['amount']) }}{{ $period }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <dialog id="plans-help" class="w-full max-w-2xl rounded-xl border border-slate-700 bg-slate-950 p-0 text-slate-200 backdrop:bg-slate-950/70">
        <div class="max-h-[90vh] overflow-y-auto p-5 space-y-4 text-sm">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-base font-semibold text-white">¿Cómo se calcula un plan?</h2>
                <button type="button" class="ui-chip-link" onclick="document.getElementById('plans-help').close()">Cerrar</button>
            </div>
            <ol class="list-decimal pl-5 space-y-1.5 text-slate-300">
                <li>La empresa dice cuántas instalaciones quiere (Accesos) y cuántos clientes supervisa (Supervisión). Son independientes.</li>
                <li>La cantidad define el plan: Bronce 1–5, Plata 6–15, Oro 16–40, Platino 41–100. Más de 100 sigue a precio Platino.</li>
                <li>Cada instalación cuesta la unidad ({{ $fmt($accessUnit) }}) menos el descuento del plan.</li>
                <li>Al cruzar un rango, <strong>todas</strong> las instalaciones pasan al nuevo precio, no solo las nuevas.</li>
                <li>Anual = mensual × 12 − {{ number_format($annualDiscount * 100, 0) }}%.</li>
            </ol>
            <div class="rounded-lg border border-slate-800 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Accesos</th>
                            <th class="px-3 py-2 text-left font-medium">Plan</th>
                            <th class="px-3 py-2 text-right font-medium">Cada una</th>
                            <th class="px-3 py-2 text-right font-medium">Total / mes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($examples as $ex)
                            <tr>
                                <td class="px-3 py-2 tabular-nums">{{ $ex['seats'] }}</td>
                                <td class="px-3 py-2">{{ $ex['label'] }} (−{{ $pct($ex['discount']) }})</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($ex['unit']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-white">{{ $fmt($ex['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-400">
                Ejemplo: pasar de {{ $examples[0]['seats'] }} a {{ $examples[1]['seats'] }} instalaciones cambia de {{ $examples[0]['label'] }} a {{ $examples[1]['label'] }}:
                las {{ $examples[1]['seats'] }} quedan a {{ $fmt($examples[1]['unit']) }} cada una.
            </p>
            <ul class="list-disc pl-5 space-y-1 text-xs text-slate-400">
                <li><strong class="text-slate-300">Disponible / No disponible:</strong> lo no disponible no aparece en la web, el registro ni la facturación de empresas. Quien ya lo tiene lo conserva hasta que cambie.</li>
                <li><strong class="text-slate-300">Guardar:</strong> «Solo para nuevas» no toca a nadie. «Aplicar a las actuales» cambia el valor desde la próxima facturación de cada empresa.</li>
                <li><strong class="text-slate-300">Precio pactado:</strong> se marca en la ficha de la empresa; esa empresa no recibe cambios ni incrementos.</li>
                <li><strong class="text-slate-300">Incremento anual:</strong> sube el catálogo un % y lo programa a las actuales desde la fecha elegida.</li>
            </ul>
        </div>
    </dialog>

    @can('platform.companies.manage')
    <dialog id="catalog-modal" class="w-full max-w-4xl rounded-xl border border-slate-700 bg-slate-950 p-0 text-slate-200 backdrop:bg-slate-950/70">
        <form method="POST" action="{{ route('admin.pricing.update') }}" class="max-h-[90vh] overflow-y-auto p-5 space-y-5">
            @csrf
            @method('PUT')
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-white">Editar planes</h2>
                    <p class="text-xs text-slate-500">Unidades al 100%. Descuento por plan. Módulos por producto.</p>
                </div>
                <button type="button" class="ui-chip-link" onclick="document.getElementById('catalog-modal').close()">Cerrar</button>
            </div>

            <div class="rounded-lg border border-slate-800 p-3 space-y-2">
                <p class="text-sm font-medium text-white">Disponibilidad</p>
                <div class="flex flex-wrap gap-4">
                    @foreach ([...array_map(fn ($m) => [$m->value, $m->label()], \App\Enums\CommercialMetal::cases()), ['hardware', 'Hardware']] as [$key, $label])
                        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                            <input type="hidden" name="availability[{{ $key }}]" value="0">
                            <input type="checkbox" name="availability[{{ $key }}]" value="1" class="rounded border-slate-600 bg-slate-900"
                                @checked(old('availability.'.$key, $availability[$key] ?? ($key !== 'hardware')))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500">Lo no disponible se oculta en la web, registro y facturación de empresas. El súper admin lo sigue viendo.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['access', 'Unidad Accesos'],
                    ['access_hardware', 'Unidad Accesos con hardware'],
                    ['supervision', 'Unidad Supervisión'],
                    ['indexing', 'Unidad Indexación (sola)'],
                    ['indexing_addon', 'Unidad Indexación (add-on)'],
                ] as [$key, $label])
                    <div>
                        <x-ui.label :for="'unit_'.$key">{{ $label }}</x-ui.label>
                        <x-ui.input accent="platform" type="number" step="1000" min="0" :name="'units['.$key.']'" :id="'unit_'.$key"
                            :value="old('units.'.$key, (int) ($catalog['units'][$key] ?? 0))" />
                        <x-ui.field-error name="units.{{ $key }}" />
                    </div>
                @endforeach
            </div>

            @foreach ([
                ['access', 'Accesos'],
                ['supervision', 'Supervisión'],
                ['indexing', 'Indexación'],
            ] as [$key, $label])
                <div class="rounded-lg border border-slate-800 p-3 space-y-2">
                    <p class="text-sm font-medium text-white">{{ $label }} · % descuento por plan</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach (['bronce','plata','oro','platino'] as $metal)
                            <div>
                                <x-ui.label :for="'d_'.$key.'_'.$metal">{{ ucfirst($metal) }} %</x-ui.label>
                                <x-ui.input accent="platform" type="number" step="0.5" min="0" max="90"
                                    :name="'discounts['.$key.']['.$metal.']'" :id="'d_'.$key.'_'.$metal"
                                    :value="old('discounts.'.$key.'.'.$metal, round(($catalog['discounts'][$key][$metal] ?? 0) * 100, 2))" />
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-500">Módulos de {{ $label }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($moduleOptions as $mod => $modLabel)
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-300">
                                <input type="checkbox" name="modules[{{ $key }}][]" value="{{ $mod }}"
                                    class="rounded border-slate-600 bg-slate-900"
                                    @checked(in_array($mod, old('modules.'.$key, $catalog['modules'][$key] ?? []), true))>
                                {{ $modLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="rounded-lg border border-slate-800 p-3 space-y-2">
                <p class="text-sm font-medium text-white">Observatorio · precio según plan de Accesos</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach (['bronce','plata','oro','platino'] as $metal)
                        <div>
                            <x-ui.label :for="'obs_'.$metal">{{ ucfirst($metal) }}</x-ui.label>
                            <x-ui.input accent="platform" type="number" step="1000" min="0" :name="'observatory['.$metal.']'" :id="'obs_'.$metal"
                                :value="old('observatory.'.$metal, (int) ($catalog['observatory'][$metal] ?? 0))" />
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($moduleOptions as $mod => $modLabel)
                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-300">
                            <input type="checkbox" name="modules[observatory][]" value="{{ $mod }}"
                                class="rounded border-slate-600 bg-slate-900"
                                @checked(in_array($mod, old('modules.observatory', $catalog['modules']['observatory'] ?? []), true))>
                            {{ $modLabel }}
                        </label>
                    @endforeach
                </div>
            </div>

            <fieldset class="rounded-lg border border-amber-800/40 bg-amber-950/10 p-3 space-y-2">
                <legend class="px-1 text-sm font-medium text-white">¿A quién aplica?</legend>
                <label class="flex items-start gap-2 text-sm text-slate-200">
                    <input type="radio" name="apply_scope" value="new" class="mt-1" @checked(old('apply_scope', 'new') === 'new')>
                    <span>Solo para nuevas empresas <span class="block text-xs text-slate-500">Las actuales conservan su valor.</span></span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-200">
                    <input type="radio" name="apply_scope" value="current" class="mt-1" @checked(old('apply_scope') === 'current')>
                    <span>Aplicar también a las actuales desde su próxima facturación
                        <span class="block text-xs text-slate-500">Se avisa en la facturación de cada empresa. Las de precio pactado ({{ $lockedCount }}) quedan igual.</span>
                    </span>
                </label>
                <x-ui.field-error name="apply_scope" />
            </fieldset>

            <x-ui.button type="submit" variant="platform" size="md" class="w-full">Guardar planes</x-ui.button>
        </form>
    </dialog>

    <dialog id="increase-modal" class="w-full max-w-3xl rounded-xl border border-slate-700 bg-slate-950 p-0 text-slate-200 backdrop:bg-slate-950/70">
        <form method="POST" action="{{ route('admin.pricing.increase.apply') }}" class="max-h-[90vh] overflow-y-auto p-5 space-y-4"
            x-data="{
                percent: @js((float) old('percent', 5)),
                effectiveOn: @js(old('effective_on', now()->addMonth()->startOfMonth()->format('Y-m-d'))),
                loading: false,
                preview: null,
                error: '',
                money(n) { return '$' + Math.round(Number(n) || 0).toLocaleString('es-CO') },
                async load() {
                    this.loading = true; this.error = '';
                    try {
                        const res = await fetch(@js(route('admin.pricing.increase.preview')), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                            body: JSON.stringify({ percent: this.percent, effective_on: this.effectiveOn }),
                        });
                        const data = await res.json();
                        if (! res.ok) { this.error = data.message || 'Revisa el % y la fecha.'; this.preview = null; return; }
                        this.preview = data;
                    } catch (e) { this.error = 'No se pudo calcular.'; } finally { this.loading = false; }
                },
            }">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-white">Incremento anual</h2>
                    <p class="text-xs text-slate-500">Sube todas las unidades y el Observatorio. Las empresas actuales lo ven desde su primera facturación a partir de la fecha. Precio pactado queda igual.</p>
                </div>
                <button type="button" class="ui-chip-link" onclick="document.getElementById('increase-modal').close()">Cerrar</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div>
                    <x-ui.label for="inc_percent">Incremento %</x-ui.label>
                    <input id="inc_percent" type="number" name="percent" step="0.1" min="0.1" max="100" x-model.number="percent" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <x-ui.field-error name="percent" />
                </div>
                <div>
                    <x-ui.label for="inc_date">Desde</x-ui.label>
                    <input id="inc_date" type="date" name="effective_on" x-model="effectiveOn" min="{{ now()->format('Y-m-d') }}" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <x-ui.field-error name="effective_on" />
                </div>
                <x-ui.button type="button" variant="secondary" size="sm" @click="load()" x-bind:disabled="loading">
                    <span x-text="loading ? 'Calculando…' : 'Vista previa'"></span>
                </x-ui.button>
            </div>
            <p class="text-xs text-rose-300" x-show="error" x-text="error" x-cloak></p>

            <template x-if="preview">
                <div class="space-y-2">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="ui-chip ui-chip-caution" x-text="preview.affected + ' empresa(s) suben'"></span>
                        <span class="ui-chip ui-chip-success" x-text="preview.locked + ' con precio pactado'"></span>
                        <span class="ui-chip" x-text="'Hoy ' + money(preview.current_total) + ' → ' + money(preview.new_total)"></span>
                    </div>
                    <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-800">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-900 text-xs uppercase text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Empresa</th>
                                    <th class="px-3 py-2 text-left font-medium">Plan</th>
                                    <th class="px-3 py-2 text-right font-medium">Hoy</th>
                                    <th class="px-3 py-2 text-right font-medium">Nuevo</th>
                                    <th class="px-3 py-2 text-right font-medium">Desde</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <template x-for="row in preview.rows" :key="row.id">
                                    <tr>
                                        <td class="px-3 py-2 text-slate-200" x-text="row.name"></td>
                                        <td class="px-3 py-2 text-xs text-slate-400" x-text="row.plan"></td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-400" x-text="money(row.current)"></td>
                                        <td class="px-3 py-2 text-right tabular-nums text-white" x-text="row.locked ? 'Pactado' : money(row.new)"></td>
                                        <td class="px-3 py-2 text-right text-xs text-slate-400" x-text="row.effective_at || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <x-ui.button type="submit" variant="platform" size="md" class="w-full" x-bind:disabled="! preview">Aplicar incremento</x-ui.button>
        </form>
    </dialog>

    @if ($errors->hasAny(['percent', 'effective_on']))
        <script>document.getElementById('increase-modal')?.showModal()</script>
    @elseif ($errors->any())
        <script>document.getElementById('catalog-modal')?.showModal()</script>
    @endif
    @endcan
</x-admin-layout>
