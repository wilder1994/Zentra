@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'allLabel' => 'Todos',
    'placeholder' => 'Escribe para filtrar…',
    'empty' => 'Sin resultados',
])

{{-- Selector con búsqueda para filtros de header: lista al abrir, filtra al escribir, envía el form al elegir. --}}
<div class="relative shrink-0"
     x-data="filterPicker({ options: @js(array_values($options)), value: @js($value), allLabel: @js($allLabel) })"
     @click.outside="close()"
     @keydown.escape.window="close()">
    <input type="hidden" name="{{ $name }}" :value="value">
    <span class="block text-xs text-slate-500">{{ $label }}</span>
    <button type="button" @click="open ? close() : show()" @keydown.arrow-down.prevent="move(1)"
            class="mt-1 inline-flex h-9 min-w-[14rem] max-w-[20rem] items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white hover:bg-slate-800"
            :aria-expanded="open" aria-haspopup="listbox">
        <span class="flex min-w-0 items-center gap-2">
            <svg class="h-4 w-4 shrink-0 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5"></circle><path stroke-linecap="round" d="M20 20l-4.2-4.2"></path>
            </svg>
            <span class="truncate" x-text="current">{{ collect($options)->firstWhere('value', $value)['label'] ?? $allLabel }}</span>
        </span>
        <svg class="h-4 w-4 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
    </button>

    <div x-show="open" x-cloak
         class="absolute right-0 z-40 mt-1 w-[min(22rem,90vw)] rounded-lg border border-slate-700 bg-slate-900 p-2 shadow-2xl">
        <input type="search" x-ref="search" x-model="query" @input="type()"
               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="enter()"
               placeholder="{{ $placeholder }}" autocomplete="off" role="combobox" :aria-expanded="open"
               class="w-full h-9 px-3 text-sm rounded-md border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
        <ul x-ref="list" class="mt-2 max-h-72 overflow-y-auto" role="listbox">
            <template x-for="(o, i) in matches" :key="o.value || '__all'">
                <li role="option" :aria-selected="o.value === value"
                    @mousedown.prevent="pick(o)" @mouseenter="active = i"
                    :class="i === active ? 'bg-slate-800 text-white' : 'text-slate-300'"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-3 py-1.5 text-sm">
                    <span class="truncate" :class="o.value === '' ? 'italic text-slate-400' : ''" x-text="o.label"></span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span x-show="o.hint" x-text="o.hint" class="text-[11px] text-slate-500"></span>
                        <svg x-show="o.value === value" class="h-4 w-4 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    </span>
                </li>
            </template>
            <li x-show="matches.length === 0" class="px-3 py-1.5 text-sm text-slate-500">{{ $empty }}</li>
        </ul>
    </div>
</div>
