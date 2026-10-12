@php
    $fmt = fn (float $n) => '$'.number_format($n, 0, ',', '.');
    $period = $cycle->value === 'annual' ? '/año' : '/mes';
    $byMetal = fn (array $rows) => collect($rows)->keyBy('metal');
    $supByMetal = $byMetal($supervisionMatrix);
    $idxByMetal = $byMetal($indexingMatrix);
    $idxAddonByMetal = $byMetal($indexingAddonMatrix);
    $obsByMetal = $byMetal($observatoryMatrix);
@endphp

@extends('layouts.public')

@section('content')
    <div class="space-y-8">
        <div>
            <p class="text-xs text-cyan-400 uppercase tracking-widest">Comercial</p>
            <h2 class="text-2xl font-bold text-white mt-1">Planes Bronce, Plata, Oro y Platino</h2>
            <p class="text-sm text-slate-400 mt-2">
                Pagas por instalación. La cantidad define tu plan y el descuento aplica a todas:
                Bronce 1–5, Plata 6–15, Oro 16–40, Platino 41 o más. Accesos y Supervisión se contratan por separado.
            </p>
        </div>

        <section class="rounded-xl border border-cyan-800/40 bg-slate-900/80 p-4 space-y-3"
            x-data="planPicker(@js([
                'catalog' => $planCatalog,
                'manual' => 5,
                'hardware' => 0,
                'supervision' => 0,
                'cycle' => $cycle->value,
            ]))">
            <h3 class="font-semibold text-white">Calcula tu plan</h3>
            <x-plans.seat-fields :cycle-options="\App\Enums\BillingCycle::options()" id-prefix="calc" />
            <a class="inline-flex items-center justify-center w-full h-9 px-3 rounded-lg text-sm font-medium bg-cyan-600 text-white hover:bg-cyan-500"
               :class="canSubmit ? '' : 'opacity-50 pointer-events-none'"
               :href="signupUrl(@js(route('signup.create')))">
                Contratar este plan
            </a>
        </section>

        <div class="flex flex-wrap gap-2">
            <x-ui.button :variant="$cycle->value === 'monthly' ? 'platform' : 'secondary'" :href="route('planes.index', ['cycle' => 'monthly'])" size="sm">Mensual</x-ui.button>
            <x-ui.button :variant="$cycle->value === 'annual' ? 'platform' : 'secondary'" :href="route('planes.index', ['cycle' => 'annual'])" size="sm">Anual (−{{ number_format($annualDiscount * 100, 0) }}%)</x-ui.button>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($accessMatrix as $row)
                @php
                    $sup = $supByMetal[$row['metal']] ?? null;
                    $idxAddon = $idxAddonByMetal[$row['metal']] ?? null;
                    $idx = $idxByMetal[$row['metal']] ?? null;
                    $obs = $obsByMetal[$row['metal']] ?? null;
                @endphp
                <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-white">{{ $row['label'] }} · Accesos</h3>
                        <span class="ui-chip ui-chip-success">−{{ number_format($row['discount'] * 100, 0) }}%</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ $row['range'] }} · hasta {{ number_format($row['employees']) }} empleados</p>
                    <p class="text-2xl font-bold text-white tabular-nums">{{ $fmt((float) $row['unit_amount']) }}<span class="text-sm font-normal text-slate-500">{{ $period }} por instalación</span></p>
                    <p class="text-xs text-slate-500">Desde {{ $fmt((float) $row['from_amount']) }}{{ $period }} con {{ $row['min'] }} instalación{{ $row['min'] === 1 ? '' : 'es' }}</p>
                    <ul class="text-xs text-slate-400 space-y-1">
                        <li>Lleva <span class="text-slate-200">Mi empresa, clientes, instalaciones, pánicos, empleados, ajustes</span>.</li>
                        @if ($sup)
                            <li>Supervisión {{ $row['label'] }}: {{ $fmt((float) $sup['unit_amount']) }}{{ $period }} por cliente (campo y APK).</li>
                        @endif
                        @if ($idxAddon && $idx)
                            <li>Indexación add-on {{ $fmt((float) $idxAddon['unit_amount']) }}{{ $period }} por instalación (sola {{ $fmt((float) $idx['unit_amount']) }}).</li>
                        @endif
                        @if ($obs)
                            <li>Observatorio {{ $fmt((float) $obs['amount']) }}{{ $period }} con Accesos {{ $row['label'] }}.</li>
                        @endif
                    </ul>
                    <a class="inline-flex items-center justify-center w-full h-9 px-3 rounded-lg text-sm font-medium bg-cyan-600 text-white hover:bg-cyan-500"
                       href="{{ route('signup.create', ['cycle' => $cycle->value, 'access' => $row['min']]) }}">
                        Contratar Accesos {{ $row['label'] }}
                    </a>
                </article>
            @endforeach
        </div>

        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-white">Supervisión</h3>
            <p class="text-sm text-slate-400">Se contrata sola o con Accesos. Mismos planes por cantidad de clientes supervisados.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($supervisionMatrix as $row)
                    <a href="{{ route('signup.create', ['cycle' => $cycle->value, 'supervision' => $row['min']]) }}" class="rounded-xl border border-slate-800 bg-slate-900/60 p-3 hover:border-amber-700/60">
                        <p class="font-medium text-white">{{ $row['label'] }}</p>
                        <p class="text-xs text-slate-500">{{ $row['range'] }}</p>
                        <p class="mt-1 font-semibold text-amber-200 tabular-nums">{{ $fmt((float) $row['unit_amount']) }}{{ $period }} <span class="text-xs font-normal text-slate-500">por cliente</span></p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="space-y-3">
            <h3 class="text-lg font-semibold text-white">Indexación</h3>
            <p class="text-sm text-slate-400">Sola = cupo + carpetas. Con Accesos o Supervisión = solo carpetas, más económica.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($indexingMatrix as $row)
                    <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                        <p class="font-medium text-white">{{ $row['label'] }}</p>
                        <p class="text-xs text-slate-500">Sola {{ $fmt((float) $row['unit_amount']) }}{{ $period }} por instalación</p>
                        @if ($idxAddonByMetal[$row['metal']] ?? null)
                            <p class="text-xs text-emerald-400">Add-on {{ $fmt((float) $idxAddonByMetal[$row['metal']]['unit_amount']) }}{{ $period }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
