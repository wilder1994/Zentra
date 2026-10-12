@props([
    'cycleOptions' => null,
    'withSupervision' => true,
    'inputClass' => 'w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white',
])

{{-- Requiere un ancestro con x-data="planPicker(...)". --}}
<div class="space-y-3">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label for="{{ $attributes->get('id-prefix', 'plan') }}_manual" class="block text-xs font-medium text-slate-400 mb-1">Instalaciones Accesos</label>
            <input type="number" min="0" step="1" name="manual_seats" id="{{ $attributes->get('id-prefix', 'plan') }}_manual"
                x-model.number="manual" class="{{ $inputClass }}" inputmode="numeric">
            <x-ui.field-error :messages="$errors->get('manual_seats')" />
        </div>
        <div x-show="hardwareEnabled || Number(hardware) > 0" x-cloak>
            <label for="{{ $attributes->get('id-prefix', 'plan') }}_hardware" class="block text-xs font-medium text-slate-400 mb-1">Con hardware</label>
            <input type="number" min="0" step="1" name="hardware_seats" id="{{ $attributes->get('id-prefix', 'plan') }}_hardware"
                x-model.number="hardware" class="{{ $inputClass }}" inputmode="numeric">
            <x-ui.field-error :messages="$errors->get('hardware_seats')" />
        </div>
        @if ($withSupervision)
            <div>
                <label for="{{ $attributes->get('id-prefix', 'plan') }}_supervision" class="block text-xs font-medium text-slate-400 mb-1">Clientes Supervisión</label>
                <input type="number" min="0" step="1" name="supervision_seats" id="{{ $attributes->get('id-prefix', 'plan') }}_supervision"
                    x-model.number="supervision" class="{{ $inputClass }}" inputmode="numeric">
                <x-ui.field-error :messages="$errors->get('supervision_seats')" />
            </div>
        @endif
        @if ($cycleOptions)
            <div>
                <label for="{{ $attributes->get('id-prefix', 'plan') }}_cycle" class="block text-xs font-medium text-slate-400 mb-1">Ciclo</label>
                <select name="billing_cycle" id="{{ $attributes->get('id-prefix', 'plan') }}_cycle" x-model="cycle" class="{{ $inputClass }}">
                    @foreach ($cycleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-ui.field-error :messages="$errors->get('billing_cycle')" />
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-950/50 p-3 text-xs space-y-1.5">
        <template x-if="accessSeats > 0">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-slate-400">
                    Accesos <span class="ui-chip ui-chip-accent" x-text="accessMetal?.label"></span>
                    <span x-text="accessSeats"></span> × <span class="tabular-nums" x-text="money(accessUnit)"></span>
                    <span class="text-emerald-400" x-text="'(−' + Math.round((accessMetal?.discounts?.access || 0) * 100) + '%)'"></span>
                </span>
                <span class="tabular-nums text-slate-200" x-text="money(accessMonthly) + '/mes'"></span>
            </div>
        </template>
        @if ($withSupervision)
            <template x-if="Number(supervision) > 0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-slate-400">
                        Supervisión <span class="ui-chip ui-chip-warning" x-text="supervisionMetal?.label"></span>
                        <span x-text="supervision"></span> × <span class="tabular-nums" x-text="money(supervisionUnit)"></span>
                        <span class="text-emerald-400" x-text="'(−' + Math.round((supervisionMetal?.discounts?.supervision || 0) * 100) + '%)'"></span>
                    </span>
                    <span class="tabular-nums text-slate-200" x-text="money(supervisionMonthly) + '/mes'"></span>
                </div>
            </template>
        @endif
        <div class="flex items-center justify-between gap-2 border-t border-slate-800 pt-1.5">
            <span class="text-slate-300 font-medium" x-text="cycle === 'annual' ? 'Total anual' : 'Total mensual'"></span>
            <span class="text-sm font-semibold text-white tabular-nums" x-text="money(total)"></span>
        </div>
        <p class="text-slate-500" x-show="nextPlanHint(accessSeats, 'access')" x-text="nextPlanHint(accessSeats, 'access')"></p>
        <p class="text-amber-300" x-show="unavailable.length" x-cloak x-text="'No disponible: ' + unavailable.join(', ')"></p>
        <p class="text-amber-300" x-show="isEmpty" x-cloak>Indica instalaciones de Accesos o clientes de Supervisión.</p>
    </div>
</div>
