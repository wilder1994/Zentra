@props([
    'cycleOptions' => null,
    'inputClass' => 'w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white',
])

@php
    $prefix = $attributes->get('id-prefix', 'plan');
    $fields = [
        ['manual', 'manual_seats', 'Accesos'],
        ['supervision', 'supervision_seats', 'Supervisión'],
        ['indexing', 'indexing_seats', 'Indexación (empleados)'],
        ['observatory', 'observatory_seats', 'Observatorio'],
    ];
@endphp

{{-- Requiere un ancestro con x-data="planPicker(...)". Cantidades en instalaciones; Indexación en empleados. --}}
<div class="space-y-3">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach ($fields as [$model, $name, $label])
            <div>
                <label for="{{ $prefix }}_{{ $model }}" class="block text-xs font-medium text-slate-400 mb-1">{{ $label }}</label>
                <input type="number" min="0" step="1" name="{{ $name }}" id="{{ $prefix }}_{{ $model }}"
                    x-model.number="{{ $model }}" class="{{ $inputClass }}" inputmode="numeric">
                <x-ui.field-error :messages="$errors->get($name)" />
            </div>
        @endforeach
        <div x-show="hardwareEnabled || Number(hardware) > 0" x-cloak>
            <label for="{{ $prefix }}_hardware" class="block text-xs font-medium text-slate-400 mb-1">Accesos con hardware</label>
            <input type="number" min="0" step="1" name="hardware_seats" id="{{ $prefix }}_hardware"
                x-model.number="hardware" class="{{ $inputClass }}" inputmode="numeric">
            <x-ui.field-error :messages="$errors->get('hardware_seats')" />
        </div>
        @if ($cycleOptions)
            <div>
                <label for="{{ $prefix }}_cycle" class="block text-xs font-medium text-slate-400 mb-1">Ciclo</label>
                <select name="billing_cycle" id="{{ $prefix }}_cycle" x-model="cycle" class="{{ $inputClass }}">
                    @foreach ($cycleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-ui.field-error :messages="$errors->get('billing_cycle')" />
            </div>
        @endif
    </div>
    <p class="text-xs text-slate-500">Cantidades en instalaciones (Indexación en empleados). El cupo de instalaciones es la mayor de ellas.</p>

    <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3 text-xs space-y-1.5">
        <template x-for="line in quote.lines" :key="line.key">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-slate-400">
                    <span x-text="line.label"></span>
                    <span class="ui-chip ui-chip-accent" x-text="line.metal?.label"></span>
                    <span x-text="Number(line.seats).toLocaleString('es-CO') + ' ' + line.unitLabel"></span> × <span class="tabular-nums" x-text="money(line.unit)"></span>
                    <span class="text-emerald-400" x-text="'(−' + Math.round(line.discount * 100) + '%)'"></span>
                    <span class="text-sky-300" x-show="line.combo" x-text="'combinado −' + Math.round(quote.comboPct * 100) + '%'"></span>
                </span>
                <span class="tabular-nums text-slate-200" x-text="money(line.monthly) + '/mes'"></span>
            </div>
        </template>
        <div class="flex items-center justify-between gap-2 border-t border-slate-800 pt-1.5" x-show="quote.savings > 0" x-cloak>
            <span class="text-slate-500">Sin descuento <span class="line-through tabular-nums" x-text="money(quote.listAmount)"></span></span>
            <span class="text-emerald-400 tabular-nums" x-text="'Ahorras ' + money(quote.savings)"></span>
        </div>
        <div class="flex items-center justify-between gap-2 border-t border-slate-800 pt-1.5">
            <span class="text-slate-300 font-medium" x-text="cycle === 'annual' ? 'Total anual' : 'Total mensual'"></span>
            <span class="text-sm font-semibold text-white tabular-nums" x-text="money(total)"></span>
        </div>
        <p class="text-slate-500" x-show="nextPlanHint(accessSeats, 'access')" x-text="nextPlanHint(accessSeats, 'access')"></p>
        <p class="text-amber-300" x-show="unavailable.length" x-cloak x-text="'No disponible: ' + unavailable.join(', ')"></p>
        <p class="text-amber-300" x-show="isEmpty" x-cloak>Elige al menos un producto.</p>
    </div>
</div>
