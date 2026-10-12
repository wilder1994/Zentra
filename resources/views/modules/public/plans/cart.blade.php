@extends('layouts.public')

@section('content')
    <div class="space-y-6" x-data="planCart(@js(['catalog' => $planCatalog]))">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <p class="text-xs text-cyan-400 uppercase tracking-widest">Planes</p>
                <h2 class="text-2xl font-bold text-white mt-1">Tu carrito</h2>
                <p class="text-sm text-slate-400 mt-1">Ajusta cantidades; si cruzas un rango, el producto cambia de plan.</p>
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
                <section class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900/80 divide-y divide-slate-800">
                    <template x-for="key in keys" :key="key">
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white">
                                    <span x-text="labels[key]"></span>
                                    <span class="ui-chip ui-chip-accent" x-text="metalOf(key)?.label"></span>
                                </p>
                                <p class="text-xs text-slate-500">
                                    <span x-text="money(line(key)?.unit * (1 - (line(key)?.discount || 0)))"></span>/mes por instalación
                                    <span class="text-emerald-400" x-text="'(−' + Math.round((line(key)?.discount || 0) * 100) + '% plan)'"></span>
                                    <span class="text-sky-300" x-show="line(key)?.combo" x-text="'· combinado −' + Math.round(quote.comboPct * 100) + '%'"></span>
                                </p>
                                <p class="text-xs text-amber-300" x-show="metalOf(key) && !metalOf(key).available" x-cloak>Este plan no está a la venta.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="h-8 w-8 rounded-lg border border-slate-700 text-slate-200 hover:bg-slate-800" @click="step(key, -1)" aria-label="Menos">−</button>
                                <input type="number" min="0" inputmode="numeric" class="h-8 w-20 rounded-lg border border-slate-700 bg-slate-950 px-2 text-center text-sm text-white"
                                    :value="items[key]" @change="setQty(key, $event.target.value)" :aria-label="'Instalaciones de ' + labels[key]">
                                <button type="button" class="h-8 w-8 rounded-lg border border-slate-700 text-slate-200 hover:bg-slate-800" @click="step(key, 1)" aria-label="Más">+</button>
                            </div>
                            <div class="sm:w-32 text-right">
                                <p class="text-sm font-semibold text-white tabular-nums" x-text="money(lineAmount(key))"></p>
                                <button type="button" class="text-xs text-rose-300 hover:text-rose-200" @click="remove(key)">Quitar</button>
                            </div>
                        </div>
                    </template>
                    <div class="p-4 text-xs text-slate-400">
                        Cupo: <span class="text-slate-200" x-text="capacity + ' instalaciones'"></span>
                        <template x-if="topMetal">
                            <span>
                                · plan <span class="text-slate-200" x-text="topMetal.label"></span>:
                                <span x-text="Number(topMetal.employees).toLocaleString('es-CO') + ' empleados, ' + topMetal.posts + ' puestos por instalación, hasta ' + topMetal.per_post + ' empleados por puesto'"></span>
                            </span>
                        </template>
                    </div>
                </section>

                <aside class="rounded-xl border border-cyan-800/40 bg-slate-900/80 p-4 space-y-3 h-fit">
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
