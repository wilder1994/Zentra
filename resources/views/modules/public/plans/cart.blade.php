@extends('layouts.public')

@section('content')
    <div class="space-y-6" x-data="planCart(@js(['catalog' => $planCatalog]))">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <p class="text-xs text-cyan-400 uppercase tracking-widest">Planes</p>
                <h2 class="text-2xl font-bold text-white mt-1">Tu carrito</h2>
                <p class="text-sm text-slate-400 mt-1">Ajusta cantidades; si cruzas un rango, el producto cambia de plan solo.</p>
            </div>
            <a href="{{ route('planes.index') }}" class="text-sm text-cyan-300 hover:text-cyan-200">← Seguir armando</a>
        </div>

        <template x-if="keys.length === 0">
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-6 text-center space-y-3">
                <p class="text-sm text-slate-300">Tu carrito está vacío.</p>
                <a href="{{ route('planes.index') }}" class="inline-flex items-center justify-center h-9 px-4 rounded-lg text-sm font-medium bg-cyan-600 text-white hover:bg-cyan-500">Ver planes</a>
            </div>
        </template>

        <template x-if="keys.length > 0">
            <div class="grid gap-4 lg:grid-cols-3">
                <article class="plan-metal lg:col-span-2 h-fit rounded-xl border p-4 space-y-3" :data-metal="identity.key">
                    <div class="plan-metal-bar -mx-4 -mt-4 mb-1 rounded-t-xl"></div>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="plan-metal-title text-2xl font-bold tracking-wide" x-text="identity.name"></h3>
                            <p class="text-xs text-slate-500" x-show="identity.key === 'personalizado'">Cada producto lleva el precio del plan de su cantidad.</p>
                        </div>
                        <template x-if="topMetal">
                            <div class="text-right text-xs text-slate-400">
                                <p>Hasta <span class="text-slate-200" x-text="Number(topMetal.employees).toLocaleString('es-CO')"></span> empleados</p>
                                <p><span class="text-slate-200" x-text="topMetal.posts"></span> puestos por instalación</p>
                                <p>Asigna hasta <span class="text-slate-200" x-text="topMetal.per_post"></span> empleados a cada puesto</p>
                            </div>
                        </template>
                    </div>

                    <p class="rounded-lg border border-emerald-700/40 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-300" x-show="notice" x-text="notice" x-cloak></p>

                    <ul class="divide-y divide-slate-800 rounded-lg border border-slate-800">
                        <template x-for="key in keys" :key="key">
                            <li class="p-3 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white flex flex-wrap items-center gap-1.5">
                                        <span x-text="labels[key]"></span>
                                        <span class="plan-name-chip" :data-metal="metalOf(key)?.key" x-show="identity.key === 'personalizado'" x-text="metalOf(key)?.label"></span>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        <span class="line-through" x-text="money(listUnit(key))"></span>
                                        <span class="text-slate-300" x-text="money(netUnit(key))"></span>
                                        <span class="text-emerald-400" x-text="'(−' + Math.round((line(key)?.discount || 0) * 100) + '% ' + (metalOf(key)?.label || '') + ')'"></span>
                                        por <span x-text="unitName(key)"></span>
                                    </p>
                                    <p class="mt-1 inline-flex items-center gap-1 rounded-md bg-emerald-500/15 px-1.5 py-0.5 text-xs font-semibold text-emerald-300 tabular-nums" x-show="line(key)?.combo"
                                        x-text="'Combinado −' + Math.round(quote.comboPct * 100) + '%: ' + money(netUnit(key, true)) + ' por ' + unitName(key)"></p>
                                    <p class="text-xs text-sky-300" x-show="!line(key)?.combo && keys.length > 1" x-text="'Precio completo · los demás −' + Math.round(quote.comboPct * 100) + '%'"></p>
                                    <p class="text-xs text-amber-300" x-show="metalOf(key) && !metalOf(key).available" x-cloak>Este plan no está a la venta.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="inline-flex h-8 overflow-hidden rounded-lg border border-slate-700 bg-slate-950 focus-within:border-cyan-600">
                                        <input type="number" min="0" inputmode="numeric"
                                            class="w-20 border-0 bg-transparent px-2 text-right text-sm text-white tabular-nums focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            :value="items[key]" @change="setQty(key, $event.target.value, $event.target)"
                                            @keydown.arrow-up.prevent="step(key, 1)" @keydown.arrow-down.prevent="step(key, -1)"
                                            :aria-label="(key === 'indexing' ? 'Empleados de ' : 'Instalaciones de ') + labels[key]">
                                        <div class="flex w-6 flex-col border-l border-slate-700">
                                            <button type="button" class="flex flex-1 items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white" @click="step(key, 1)" aria-label="Aumentar">
                                                <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 3l4 5H2z"/></svg>
                                            </button>
                                            <button type="button" class="flex flex-1 items-center justify-center border-t border-slate-700 text-slate-300 hover:bg-slate-800 hover:text-white" @click="step(key, -1)" aria-label="Disminuir">
                                                <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 9L2 4h8z"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <span class="w-20 text-xs text-slate-400" x-text="unitName(key, items[key])"></span>
                                </div>
                                <div class="sm:w-32 text-right">
                                    <p class="text-sm font-semibold text-white tabular-nums" x-text="money(lineAmount(key))"></p>
                                    <button type="button" class="text-xs text-rose-300 hover:text-rose-200" @click="remove(key)">Quitar</button>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <p class="text-xs text-slate-400">
                        Cupo: <span class="text-slate-200" x-text="capacity + ' instalaciones'"></span>
                        <span x-show="items.indexing" x-text="' · Indexación ' + Number(items.indexing || 0).toLocaleString('es-CO') + ' empleados'"></span>
                    </p>
                </article>

                <aside class="rounded-xl border border-cyan-800/40 bg-slate-900/80 p-4 space-y-3 h-fit">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500 uppercase tracking-wide">Tu plan</span>
                        <span class="plan-name-chip" :data-metal="identity.key" x-text="identity.name"></span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="h-9 rounded-lg text-sm font-medium border"
                            :class="cycle === 'monthly' ? 'bg-cyan-600 border-cyan-600 text-white' : 'border-slate-700 text-slate-300'"
                            @click="setCycle('monthly')">Mensual</button>
                        <button type="button" class="h-9 rounded-lg text-sm font-medium border"
                            :class="cycle === 'annual' ? 'bg-cyan-600 border-cyan-600 text-white' : 'border-slate-700 text-slate-300'"
                            @click="setCycle('annual')">Anual (−{{ round($annualDiscount * 100) }}%)</button>
                    </div>
                    <dl class="text-sm space-y-1.5">
                        <div class="flex justify-between gap-2"><dt class="text-slate-400">Total sin descuento</dt><dd class="tabular-nums text-slate-300" x-text="money(quote.listAmount)"></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate-400">Descuentos por plan</dt><dd class="tabular-nums text-emerald-400" x-text="'−' + money(quote.planSavings)"></dd></div>
                        <div class="flex justify-between gap-2" x-show="quote.comboSavings > 0"><dt class="text-slate-400">Descuento por combinar</dt><dd class="tabular-nums text-emerald-400" x-text="'−' + money(quote.comboSavings)"></dd></div>
                        <div class="flex justify-between gap-2" x-show="cycle === 'annual'"><dt class="text-slate-400">Pago anual −{{ round($annualDiscount * 100) }}%</dt><dd class="tabular-nums text-emerald-400" x-text="'−' + money(quote.annualSavings)"></dd></div>
                        <div class="flex justify-between gap-2 border-t border-slate-800 pt-2"><dt class="font-medium text-white" x-text="cycle === 'annual' ? 'Total anual' : 'Total mensual'"></dt><dd class="text-lg font-bold text-white tabular-nums" x-text="money(quote.amount)"></dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-emerald-300">Ahorras</dt><dd class="tabular-nums text-emerald-300 font-medium" x-text="money(quote.savings)"></dd></div>
                    </dl>
                    <p class="text-xs text-amber-300" x-show="unavailable.length" x-cloak x-text="'No disponible: ' + unavailable.join(', ')"></p>
                    <a class="inline-flex items-center justify-center w-full h-10 px-3 rounded-lg text-sm font-semibold bg-cyan-500 text-slate-950 hover:bg-cyan-400"
                        :class="canCheckout ? '' : 'opacity-50 pointer-events-none'"
                        :href="checkoutUrl(@js(route('signup.create')))">Contratar</a>
                    <p class="text-xs text-slate-500">Los descuentos se aplican en cadena: plan, combinar y anual.</p>
                </aside>
            </div>
        </template>
    </div>
@endsection
