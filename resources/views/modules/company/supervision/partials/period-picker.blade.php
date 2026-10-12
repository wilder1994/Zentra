<div x-data="supervisionPeriodPicker({ from: @js($from), to: @js($to) })" class="shrink-0">
    <input type="hidden" name="from" value="{{ $from }}" :value="from">
    <input type="hidden" name="to" value="{{ $to }}" :value="to">
    <span class="block text-xs text-slate-500">Periodo</span>
    <button type="button" @click="show()"
            class="mt-1 inline-flex h-9 items-center gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white hover:bg-slate-800">
        <svg class="h-4 w-4 text-amber-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
            <path d="M3 10h18M8 3v4M16 3v4"></path>
        </svg>
        <span class="whitespace-nowrap" x-text="label"></span>
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-24 sm:items-center sm:pt-4"
             @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-slate-950/70" @click="close()"></div>
            <div class="relative w-full rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-2xl"
                 :class="range ? 'max-w-[38rem]' : 'max-w-[20rem]'">
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" @click="preset('today')" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-800">Hoy</button>
                    <button type="button" @click="preset('yesterday')" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-800">Ayer</button>
                    <button type="button" @click="preset('month')" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-800">Este mes</button>
                    <button type="button" @click="preset('lastMonth')" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-800">Mes anterior</button>
                    <button type="button" @click="preset('year')" class="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-800">Este año</button>
                </div>

                <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-200">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-amber-400 focus:ring-amber-400/40"
                           :checked="range" @change="toggleRange()">
                    Rango
                    <span class="text-xs text-slate-500" x-show="range" x-text="picking ? 'Elija el día final' : 'Elija desde y hasta'"></span>
                </label>

                <div class="mt-3 flex items-center justify-between">
                    <button type="button" @click="shift(-1)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Mes anterior">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="shift(1)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Mes siguiente">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="-mt-7 grid gap-5" :class="range ? 'sm:grid-cols-2' : ''">
                    <template x-for="(m, index) in months" :key="m.year + '-' + m.month">
                        <div :class="index > 0 ? 'hidden sm:block' : ''">
                            <p class="text-center text-sm font-semibold text-white" x-text="title(m)"></p>
                            <div class="mt-3 grid grid-cols-7 gap-0.5 text-center text-[11px] text-slate-500">
                                <template x-for="(w, wi) in weekdays" :key="wi"><span x-text="w"></span></template>
                            </div>
                            <div class="mt-1 grid grid-cols-7 gap-0.5 text-center text-sm">
                                <template x-for="cell in cells(m)" :key="cell.key">
                                    <button type="button" class="h-8 rounded-md tabular-nums" :disabled="!cell.day"
                                            :class="dayClass(cell.iso)" @click="pick(cell.iso)" x-text="cell.day || ''"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="close()" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
                    <button type="button" @click="apply()" class="h-9 rounded-lg bg-amber-400 px-4 text-sm font-semibold text-slate-950 hover:bg-amber-300">Aplicar</button>
                </div>
            </div>
        </div>
    </template>
</div>
