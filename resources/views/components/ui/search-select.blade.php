@props([
    'name',
    'options' => [],
    'placeholder' => 'Escribe para buscar…',
    'required' => false,
    'empty' => 'Sin resultados',
])

@php
    $items = collect($options)->map(fn ($o) => [
        'value' => (string) $o['value'],
        'label' => (string) $o['label'],
        'hint' => isset($o['hint']) ? (string) $o['hint'] : '',
    ])->values()->all();
@endphp

<div
    class="relative"
    x-data="{
        options: @js($items),
        query: '',
        value: '',
        open: false,
        active: 0,
        get matches() {
            const q = this.query.trim().toLowerCase();
            if (q === '') return [];
            return this.options.filter(o => (o.label + ' ' + o.hint).toLowerCase().includes(q)).slice(0, 50);
        },
        type() {
            this.value = '';
            this.active = 0;
            this.open = this.query.trim() !== '';
            this.validate();
        },
        pick(o) {
            this.value = o.value;
            this.query = o.label;
            this.open = false;
            this.validate();
        },
        move(step) {
            if (! this.open) { this.open = this.query.trim() !== ''; return; }
            const n = this.matches.length;
            if (n > 0) this.active = (this.active + step + n) % n;
        },
        enter() {
            if (this.open && this.matches[this.active]) this.pick(this.matches[this.active]);
        },
        validate() {
            this.$refs.search.setCustomValidity(@js($required) && this.value === '' ? 'Elige una opción de la lista.' : '');
        },
    }"
    x-init="validate()"
    @click.outside="open = false"
>
    <input type="hidden" name="{{ $name }}" :value="value">
    <input
        type="text"
        x-ref="search"
        x-model="query"
        @input="type()"
        @keydown.arrow-down.prevent="move(1)"
        @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter.prevent="enter()"
        @keydown.escape="if (open) { open = false; $event.stopPropagation() }"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        role="combobox"
        :aria-expanded="open"
        @required($required)
        {{ $attributes->merge(['class' => 'w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600']) }}
    >
    <ul
        x-show="open"
        x-cloak
        class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-700 bg-slate-900 py-1 shadow-xl"
        role="listbox"
    >
        <template x-for="(o, i) in matches" :key="o.value">
            <li
                role="option"
                :aria-selected="i === active"
                @mousedown.prevent="pick(o)"
                @mouseenter="active = i"
                :class="i === active ? 'bg-slate-800 text-white' : 'text-slate-300'"
                class="flex cursor-pointer items-baseline justify-between gap-2 px-3 py-1.5 text-sm"
            >
                <span x-text="o.label" class="truncate"></span>
                <span x-show="o.hint" x-text="o.hint" class="shrink-0 text-[11px] text-slate-500 tabular-nums"></span>
            </li>
        </template>
        <li x-show="matches.length === 0" class="px-3 py-1.5 text-sm text-slate-500">{{ $empty }}</li>
    </ul>
</div>
