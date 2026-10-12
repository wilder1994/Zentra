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
@endphp

@extends('layouts.public')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <p class="text-xs text-cyan-400 uppercase tracking-widest">Planes</p>
                <h2 class="text-2xl font-bold text-white mt-1">Arma tu plan</h2>
                <p class="text-sm text-slate-400 mt-2 max-w-2xl">
                    Elige los productos y cuántas instalaciones quieres de cada uno; Indexación se cobra por empleado.
                    La cantidad define el plan (Bronce 1–5, Plata 6–15, Oro 16–40, Platino 41 o más instalaciones;
                    en Indexación 1–1.000, 1.001–2.000, 2.001–3.000 y 3.001 o más empleados) y su descuento aplica a todas.
                    Si combinas productos, cada uno después del de mayor valor tiene −{{ round($comboDiscount * 100) }}%.
                    Pagando anual ahorras {{ round($annualDiscount * 100) }}% más.
                </p>
            </div>
            <a href="{{ route('planes.cart') }}" class="inline-flex items-center justify-center h-9 px-4 rounded-lg text-sm font-medium border border-cyan-700 text-cyan-200 hover:bg-cyan-900/30">
                Ver carrito
            </a>
        </div>

        @if ($metals === [])
            <p class="rounded-lg border border-amber-800/50 bg-amber-950/30 px-4 py-3 text-sm text-amber-100">
                Por ahora no hay planes a la venta. Escríbenos para una propuesta.
            </p>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($metals as $metal)
                <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-3"
                    x-data="planCard(@js(['catalog' => $planCatalog, 'metal' => $metal->value, 'rows' => $rows]))">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ $metal->label() }}</h3>
                            <p class="text-xs text-slate-500">{{ $metal->rangeLabelFor(\App\Enums\CommercialProduct::Access) }} por producto</p>
                            <p class="text-xs text-slate-500">Indexación: {{ $metal->rangeLabelFor(\App\Enums\CommercialProduct::Indexing) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-400">
                            <p>Hasta <span class="text-slate-200">{{ number_format($metal->employeeCap(), 0, ',', '.') }}</span> empleados</p>
                            <p><span class="text-slate-200">{{ $metal->postsPerInstallation() }}</span> puestos por instalación</p>
                            <p>Asigna hasta <span class="text-slate-200">{{ $metal->employeesPerPost() }}</span> empleados a cada puesto</p>
                        </div>
                    </div>

                    <ul class="divide-y divide-slate-800 rounded-lg border border-slate-800">
                        @foreach ($rows as $row)
                            <li class="p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <label class="flex items-start gap-2 text-sm text-slate-200">
                                        <input type="checkbox" class="mt-0.5 rounded border-slate-600 bg-slate-900" x-model="rows['{{ $row }}'].on">
                                        <span>
                                            {{ $rowLabels[$row] }}
                                            <span class="block text-xs text-slate-500">{{ implode(', ', $rowModules($row)) }}</span>
                                        </span>
                                    </label>
                                    <div class="text-right shrink-0 space-y-1.5">
                                        <p class="text-sm font-semibold text-white tabular-nums" x-text="money(unit('{{ $row }}')) + '/mes'"></p>
                                        <p class="text-xs text-slate-500">
                                            por <span x-text="unitName('{{ $row }}')"></span>
                                            <span class="line-through" x-text="money(listUnit('{{ $row }}'))"></span>
                                            <span class="text-emerald-400" x-text="'−' + discountPct('{{ $row }}') + '%'"></span>
                                        </p>
                                        <div class="flex items-center justify-end gap-2" x-show="rows['{{ $row }}'].on" x-cloak>
                                            <span class="text-xs tabular-nums text-slate-300" x-text="money(unit('{{ $row }}') * rows['{{ $row }}'].qty) + '/mes'"></span>
                                            <div class="inline-flex h-8 overflow-hidden rounded-lg border border-slate-700 bg-slate-950 focus-within:border-cyan-600">
                                                <input type="number" inputmode="numeric"
                                                    class="{{ $row === 'indexing' ? 'w-20' : 'w-14' }} border-0 bg-transparent px-2 text-right text-sm text-white tabular-nums focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    :min="min('{{ $row }}')" :max="max('{{ $row }}')" x-model.number="rows['{{ $row }}'].qty"
                                                    @change="clamp('{{ $row }}')" @keydown.arrow-up.prevent="step('{{ $row }}', 1)" @keydown.arrow-down.prevent="step('{{ $row }}', -1)"
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
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm text-slate-400">Subtotal <span class="text-white font-semibold tabular-nums" x-text="money(monthly) + '/mes'"></span></span>
                        <button type="button" class="inline-flex items-center justify-center h-9 px-4 rounded-lg text-sm font-medium bg-cyan-600 text-white hover:bg-cyan-500"
                            @click="addToCart()">Agregar al carrito</button>
                    </div>
                    <p class="text-xs text-emerald-300" x-show="notice" x-text="notice" x-cloak></p>
                </article>
            @endforeach
        </div>

        <p class="text-xs text-slate-500">
            El cupo de instalaciones de tu empresa es la mayor cantidad entre tus productos y se reparte entre tus clientes.
            Si combinas planes distintos, manda el más alto para empleados y puestos. Más empleados o puestos: «Solicitar ampliación» desde Facturación.
        </p>
    </div>
@endsection
