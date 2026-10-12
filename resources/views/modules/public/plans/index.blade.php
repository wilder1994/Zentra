@extends('layouts.public')

@section('content')
    <div class="space-y-6 pb-24">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3" x-data="planHelp">
            <div>
                <p class="text-xs text-cyan-400 uppercase tracking-widest">Planes</p>
                <h2 class="text-2xl font-bold text-white mt-1">Selecciona tu plan o ármalo a tu gusto</h2>
                <p class="text-sm text-slate-400 mt-1">Marca productos y ajusta cantidades; el plan se acomoda solo.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-lg text-sm font-medium border border-slate-700 text-slate-200 hover:bg-slate-800" @click="show()">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M10 9v5" stroke-linecap="round"/><circle cx="10" cy="6" r="0.6" fill="currentColor"/></svg>
                    ¿Cómo funciona?
                </button>
                <a href="{{ route('planes.cart') }}" class="inline-flex items-center justify-center h-9 px-4 rounded-lg text-sm font-medium border border-cyan-700 text-cyan-200 hover:bg-cyan-900/30">
                    Ver carrito
                </a>
            </div>

            <template x-teleport="body">
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="open" x-cloak
                    x-transition.opacity @keydown.escape.window="close()" role="dialog" aria-modal="true" aria-labelledby="plan-help-title">
                    <div class="absolute inset-0 backdrop-blur-sm" style="background: rgb(2 6 23 / 0.6)" @click="close()"></div>
                    <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl border border-cyan-800/50 bg-slate-900 p-5 shadow-2xl space-y-4" x-transition.scale.95>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs text-cyan-400 uppercase tracking-widest">Guía rápida</p>
                                <h3 id="plan-help-title" class="text-lg font-bold text-white">Cómo armar tu plan</h3>
                            </div>
                            <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white" @click="close()" aria-label="Cerrar">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>
                            </button>
                        </div>

                        <ol class="space-y-3 text-sm text-slate-300">
                            <li class="flex gap-3"><span class="plan-help-step">1</span><span><strong class="text-white">Marca un producto</strong> en cualquier tarjeta: Accesos, Supervisión, Indexación u Observatorio.</span></li>
                            <li class="flex gap-3"><span class="plan-help-step">2</span><span><strong class="text-white">Ajusta la cantidad</strong> con ▲▼ o escribiéndola. Se cuentan instalaciones por producto; Indexación se cuenta por empleado.</span></li>
                            <li class="flex gap-3"><span class="plan-help-step">3</span>
                                <div class="flex-1 space-y-2">
                                    <p><strong class="text-white">El plan se acomoda solo.</strong> Si te pasas del rango, el producto salta a la tarjeta siguiente.</p>
                                    <table class="w-full text-xs">
                                        <thead class="text-slate-500"><tr><th class="text-left font-medium pb-1">Plan</th><th class="text-left font-medium pb-1">Instalaciones</th><th class="text-left font-medium pb-1">Indexación</th></tr></thead>
                                        <tbody class="divide-y divide-slate-800">
                                            @foreach ($metals as $metal)
                                                <tr>
                                                    <td class="py-1"><span class="plan-name-chip" data-metal="{{ $metal->value }}">{{ $metal->label() }}</span></td>
                                                    <td class="py-1 text-slate-300">{{ $metal->rangeLabelFor(\App\Enums\CommercialProduct::Access) }}</td>
                                                    <td class="py-1 text-slate-300">{{ $metal->rangeLabelFor(\App\Enums\CommercialProduct::Indexing) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </li>
                            <li class="flex gap-3"><span class="plan-help-step">4</span><span><strong class="text-white">Combina y ahorra.</strong> El producto de mayor valor va a precio completo; los demás llevan −{{ round($comboDiscount * 100) }}%.</span></li>
                            <li class="flex gap-3"><span class="plan-help-step">5</span><span><strong class="text-white">Plan personalizado.</strong> Si tus productos quedan en planes distintos, tu plan se llama «Plan personalizado».</span></li>
                            <li class="flex gap-3"><span class="plan-help-step">6</span><span><strong class="text-white">Paga anual</strong> y ahorras {{ round($annualDiscount * 100) }}% más.</span></li>
                            <li class="flex gap-3"><span class="plan-help-step">7</span><span><strong class="text-white">Ver carrito → Contratar.</strong> Revisas el total y creas tu cuenta.</span></li>
                        </ol>

                        <button type="button" class="w-full h-10 rounded-lg text-sm font-semibold bg-cyan-500 text-slate-950 hover:bg-cyan-400" @click="close()">Entendido, armar mi plan</button>
                    </div>
                </div>
            </template>
        </div>

        @if ($metals === [])
            <p class="rounded-lg border border-amber-800/50 bg-amber-950/30 px-4 py-3 text-sm text-amber-100">
                Por ahora no hay planes a la venta. Escríbenos para una propuesta.
            </p>
        @endif

        @include('modules.public.plans.partials.metal-cards')

        <p class="text-xs text-slate-500">
            El cupo de instalaciones de tu empresa es la mayor cantidad entre tus productos y se reparte entre tus clientes.
            Si combinas planes distintos, tu plan es personalizado y manda el más alto para empleados y puestos. Más empleados o puestos: «Solicitar ampliación» desde Facturación.
        </p>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-800 bg-slate-950/90 backdrop-blur"
        x-data="planSummary(@js(['catalog' => $planCatalog]))" x-show="count > 0" x-cloak
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="plan-name-chip" :data-metal="identity.key" x-text="identity.name"></span>
                <span class="text-slate-400" x-text="count + (count === 1 ? ' producto' : ' productos')"></span>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-sm text-slate-400">
                        <span class="text-slate-500 line-through tabular-nums" x-show="quote.savings > 0" x-text="money(quote.listAmount)"></span>
                        <span class="text-lg font-bold text-white tabular-nums" x-text="money(quote.amount) + '/mes'"></span>
                    </p>
                    <p class="text-xs font-medium text-emerald-300" x-show="quote.savings > 0" x-text="'Ahorras ' + money(quote.savings) + '/mes'"></p>
                </div>
                <a href="{{ route('planes.cart') }}" class="inline-flex items-center justify-center h-10 px-5 rounded-lg text-sm font-semibold bg-cyan-500 text-slate-950 hover:bg-cyan-400">Ver carrito</a>
            </div>
        </div>
    </div>
@endsection
