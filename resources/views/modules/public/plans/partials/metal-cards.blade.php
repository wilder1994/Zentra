{{-- Tarjetas de metal. $scope null = carrito público; con valor = formulario propio (p. ej. cambio de plan del superadmin). --}}
@php
    $rows = array_values(array_filter([
        'access',
        $planCatalog['hardware'] ? 'hardware' : null,
        'supervision',
        'indexing',
        'observatory',
    ]));
    $rowLabels = [
        'access' => 'Accesos',
        'hardware' => 'Accesos con hardware',
        'supervision' => 'Supervisión',
        'indexing' => 'Indexación',
        'observatory' => 'Observatorio',
    ];
    $rowModules = fn (string $row) => $modules[$row === 'hardware' ? 'access' : $row] ?? [];
    $scope = $scope ?? null;
    $gridClass = $gridClass ?? 'grid gap-4 lg:grid-cols-2';
@endphp
        <div class="{{ $gridClass }}">
            @foreach ($metals as $metal)
                <article class="plan-metal rounded-xl border p-4 space-y-3" data-metal="{{ $metal->value }}" :class="{ 'plan-metal-glow': glow }"
                    x-data="planCard(@js(['catalog' => $planCatalog, 'metal' => $metal->value, 'rows' => $rows, 'modules' => $modules, 'scope' => $scope]))">
                    <div class="plan-metal-bar -mx-4 -mt-4 mb-1 rounded-t-xl"></div>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="plan-metal-title text-xl font-bold tracking-wide">{{ $metal->label() }}</h3>
                            <p class="text-xs text-slate-500">{{ $metal->rangeLabelFor(\App\Enums\CommercialProduct::Access) }} por producto</p>
                            <p class="text-xs text-slate-500">Indexación: {{ $metal->rangeLabelFor(\App\Enums\CommercialProduct::Indexing) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-400">
                            <p>Hasta <span class="text-slate-200">{{ number_format($metal->employeeCap(), 0, ',', '.') }}</span> empleados</p>
                            <p><span class="text-slate-200">{{ $metal->postsPerInstallation() }}</span> puestos por instalación</p>
                            <p>Asigna hasta <span class="text-slate-200">{{ $metal->employeesPerPost() }}</span> empleados a cada puesto</p>
                        </div>
                    </div>

                    <p class="rounded-lg border border-emerald-700/40 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-300" x-show="notice" x-text="notice" x-cloak></p>

                    <ul class="divide-y divide-slate-800 rounded-lg border border-slate-800">
                        @foreach ($rows as $row)
                            <li class="p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <label class="flex items-start gap-2 text-sm text-slate-200">
                                        <input type="checkbox" class="mt-0.5 rounded border-slate-600 bg-slate-900"
                                            :checked="isOn('{{ $row }}')" @change="toggle('{{ $row }}', $event.target)">
                                        <span>
                                            {{ $rowLabels[$row] }}
                                            <span class="block text-xs text-slate-500"
                                                :class="{ 'text-cyan-300': moduleInfo('{{ $row }}').mode === 'new', 'italic': moduleInfo('{{ $row }}').mode === 'none' }"
                                                x-text="moduleInfo('{{ $row }}').text">{{ implode(', ', $rowModules($row)) }}</span>
                                        </span>
                                    </label>
                                    <div class="text-right shrink-0 space-y-1.5">
                                        <p class="text-sm font-semibold text-white tabular-nums" x-text="money(unit('{{ $row }}')) + '/mes'"></p>
                                        <p class="text-xs text-slate-500">
                                            por <span x-text="unitName('{{ $row }}')"></span>
                                            <span class="line-through" x-text="money(listUnit('{{ $row }}'))"></span>
                                            <span class="text-emerald-400" x-text="'−' + discountPct('{{ $row }}') + '%'"></span>
                                        </p>
                                        <p class="text-xs text-amber-300" x-show="elsewhere('{{ $row }}')" x-cloak
                                            x-text="'Ya está en tu plan como ' + elsewhere('{{ $row }}')"></p>
                                        <template x-if="!elsewhere('{{ $row }}')">
                                            <div class="space-y-1.5">
                                                <p class="inline-flex items-center gap-1 rounded-md bg-emerald-500/15 px-1.5 py-0.5 text-xs font-semibold text-emerald-300 tabular-nums"
                                                    x-show="comboState('{{ $row }}') === 'discount'"
                                                    x-text="(isOn('{{ $row }}') ? 'Combinado −' : 'Si lo combinas −') + comboPctLabel + '%: ' + money(comboUnit('{{ $row }}')) + '/mes'"></p>
                                                <p class="text-xs text-sky-300" x-show="comboState('{{ $row }}') === 'top'"
                                                    x-text="isOn('{{ $row }}') ? 'Precio completo · los demás −' + comboPctLabel + '%' : 'Si lo combinas, los demás −' + comboPctLabel + '%'"></p>
                                            </div>
                                        </template>
                                        <div class="flex items-center justify-end gap-2" x-show="isOn('{{ $row }}')" x-cloak>
                                            <span class="text-xs tabular-nums text-slate-300">
                                                <span class="mr-1 text-slate-500 line-through" x-show="comboState('{{ $row }}') === 'discount'" x-text="money(unit('{{ $row }}') * qty('{{ $row }}'))"></span>
                                                <span x-text="money(rowTotal('{{ $row }}')) + '/mes'"></span>
                                            </span>
                                            <div class="inline-flex h-8 overflow-hidden rounded-lg border border-slate-700 bg-slate-950 focus-within:border-cyan-600">
                                                <input type="number" inputmode="numeric"
                                                    class="{{ $row === 'indexing' ? 'w-20' : 'w-14' }} border-0 bg-transparent px-2 text-right text-sm text-white tabular-nums focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    min="0" :value="qty('{{ $row }}')"
                                                    @change="commit('{{ $row }}', $event.target.value, $event.target)" @keydown.arrow-up.prevent="step('{{ $row }}', 1)" @keydown.arrow-down.prevent="step('{{ $row }}', -1)"
                                                    aria-label="{{ $row === 'indexing' ? 'Empleados' : 'Instalaciones' }} de {{ $rowLabels[$row] }}">
                                                <div class="flex w-6 flex-col border-l border-slate-700">
                                                    <button type="button" class="flex flex-1 items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white" @click="step('{{ $row }}', 1)" aria-label="Aumentar">
                                                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 3l4 5H2z"/></svg>
                                                    </button>
                                                    <button type="button" class="flex flex-1 items-center justify-center border-t border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-white" @click="step('{{ $row }}', -1)" aria-label="Disminuir">
                                                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 9L2 4h8z"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <p class="text-xs text-slate-300" x-text="gets"></p>
                    <div class="flex items-center justify-between gap-2" x-show="selected.length > 0" x-cloak>
                        <p class="text-sm text-slate-400">
                            En {{ $metal->label() }}
                            <span class="text-slate-500 line-through tabular-nums" x-show="combined < monthly - 0.5" x-text="money(monthly)"></span>
                            <span class="text-white font-semibold tabular-nums" x-text="money(combined) + '/mes'"></span>
                        </p>
                        <p class="text-xs font-medium text-emerald-300" x-show="monthly - combined > 0.5" x-text="'Ahorras ' + money(monthly - combined) + '/mes al combinar'"></p>
                    </div>
                </article>
            @endforeach
        </div>
