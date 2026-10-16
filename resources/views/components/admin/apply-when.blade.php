@props(['periodEnd' => null, 'value' => 'now', 'date' => null])

<div class="inline-flex items-center gap-2"
    x-data="{
        when: @js(old('apply_when', $value)),
        date: @js(old('effective_on', $date) ?? ''),
        pick() { const d = this.$refs.date; d.showPicker ? d.showPicker() : d.focus(); },
        label() { if (!this.date) return 'Elige la fecha'; const [y, m, d] = this.date.split('-'); return `${d}/${m}/${y}`; },
    }">
    <select name="apply_when" x-model="when" aria-label="Cuándo aplica"
        class="h-8 rounded-lg border border-slate-700 bg-slate-950 pl-2 pr-8 text-xs text-white"
        @change="if (when === 'on_date' && !date) $nextTick(() => pick())">
        <option value="now">Aplicar ya</option>
        <option value="on_date">Aplicar el día…</option>
        <option value="period_end" @disabled(! $periodEnd)>Aplicar al corte{{ $periodEnd ? ' ('.$periodEnd->format('d/m/Y').')' : '' }}</option>
    </select>
    <span x-show="when === 'on_date'" x-cloak class="relative inline-flex items-center gap-1.5">
        <button type="button" @click="pick()" title="Elegir fecha" aria-label="Elegir fecha"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-950 text-slate-300 hover:text-white">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>
            </svg>
        </button>
        <button type="button" class="text-xs tabular-nums" :class="date ? 'text-white' : 'text-amber-300'" @click="pick()" x-text="label()"></button>
        <input type="date" x-ref="date" name="effective_on" x-model="date" :disabled="when !== 'on_date'" min="{{ now()->toDateString() }}"
            class="pointer-events-none absolute bottom-0 left-0 h-px w-px opacity-0" tabindex="-1" aria-hidden="true">
    </span>
</div>
