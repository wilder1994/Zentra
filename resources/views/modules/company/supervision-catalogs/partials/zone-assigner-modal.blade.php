<template x-teleport="body">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6" @keydown.escape.window="open && close()">
        <div class="absolute inset-0 bg-slate-950/70" @click="close()"></div>
        <div class="relative flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-800 px-4 py-3">
                <div>
                    <h3 class="text-sm font-semibold text-white">Asignar clientes a zonas</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Zona principal: a ella cuentan sus revistas. Compartido: otra zona también lo ve.</p>
                </div>
                <button type="button" @click="close()" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
            </div>

            <div class="space-y-2 border-b border-slate-800 px-4 py-3">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" x-ref="search" x-model="query" placeholder="Buscar cliente…" autocomplete="off"
                           class="h-9 w-full rounded-lg border border-slate-700 bg-slate-950 pl-9 pr-3 text-sm text-white placeholder:text-slate-500">
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-indigo-600 text-white border-indigo-500' : 'border-slate-700 text-slate-300 hover:bg-slate-800'"
                            class="rounded-full border px-2.5 py-1">Todos <span class="opacity-70" x-text="countFor('all')"></span></button>
                    <button type="button" @click="filter = 'none'" :class="filter === 'none' ? 'bg-rose-600 text-white border-rose-500' : 'border-rose-500/40 text-rose-300 hover:bg-rose-500/10'"
                            class="rounded-full border px-2.5 py-1">Sin zona <span class="opacity-70" x-text="countFor('none')"></span></button>
                    <template x-for="zone in zones" :key="'fz'+zone.id">
                        <button type="button" @click="filter = 'zone:' + zone.id" :class="filter === 'zone:' + zone.id ? 'bg-indigo-600 text-white border-indigo-500' : 'border-slate-700 text-slate-300 hover:bg-slate-800'"
                                class="rounded-full border px-2.5 py-1"><span x-text="zone.name"></span> <span class="opacity-70" x-text="countFor('zone:' + zone.id)"></span></button>
                    </template>
                </div>
            </div>

            <div x-show="selected.length" x-cloak class="flex flex-wrap items-center gap-2 border-b border-slate-800 bg-indigo-500/10 px-4 py-2 text-xs text-slate-200">
                <span class="font-semibold" x-text="selected.length + (selected.length === 1 ? ' seleccionado' : ' seleccionados')"></span>
                <span class="text-slate-500">·</span>
                <label class="inline-flex items-center gap-1.5">Asignar a
                    <select x-model="bulkZone" class="h-8 rounded-md border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                        <option value="">Zona…</option>
                        <template x-for="zone in zones" :key="'bz'+zone.id"><option :value="String(zone.id)" x-text="zone.name"></option></template>
                    </select>
                </label>
                <button type="button" @click="applyBulkZone()" :disabled="bulkZone === ''" class="h-8 rounded-md bg-indigo-600 px-2.5 font-semibold text-white disabled:opacity-40">Aplicar</button>
                <span class="text-slate-500">·</span>
                <div class="relative">
                    <button type="button" @click="bulkSharedOpen = !bulkSharedOpen" class="h-8 rounded-md border border-slate-700 px-2.5 hover:bg-slate-800">Compartir con…</button>
                    <div x-show="bulkSharedOpen" x-cloak @click.outside="bulkSharedOpen = false" class="absolute left-0 top-9 z-10 w-52 rounded-lg border border-slate-700 bg-slate-950 p-2 shadow-xl">
                        <template x-for="zone in zones" :key="'bs'+zone.id">
                            <label class="flex items-center gap-2 rounded px-1.5 py-1 hover:bg-slate-800">
                                <input type="checkbox" :checked="bulkShared.includes(zone.id)" @change="toggleBulkShared(zone.id)" class="rounded border-slate-600 bg-slate-950 text-indigo-600">
                                <span x-text="zone.name"></span>
                            </label>
                        </template>
                        <button type="button" @click="applyBulkShared()" :disabled="!bulkShared.length" class="mt-1 h-8 w-full rounded-md bg-indigo-600 font-semibold text-white disabled:opacity-40">Compartir</button>
                    </div>
                </div>
                <button type="button" @click="selected = []" class="ml-auto text-slate-400 hover:text-white">Quitar selección</button>
            </div>

            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-[1] bg-slate-900">
                        <tr class="border-b border-slate-800 text-[11px] uppercase tracking-wide text-slate-500">
                            <th class="w-10 px-4 py-2 text-left"><input type="checkbox" :checked="allVisibleSelected" @change="toggleAllVisible()" class="rounded border-slate-600 bg-slate-950 text-indigo-600" title="Seleccionar visibles"></th>
                            <th class="py-2 pr-3 text-left font-medium">Cliente</th>
                            <th class="py-2 pr-3 text-left font-medium">Zona principal</th>
                            <th class="py-2 pr-3 text-left font-medium">Compartido con</th>
                            <th class="py-2 pr-4 text-left font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in filtered" :key="'zr'+row.id">
                            <tr class="border-b border-slate-800/70 align-middle" :class="isDirty(row) ? 'bg-amber-500/5' : ''">
                                <td class="px-4 py-2"><input type="checkbox" :checked="selected.includes(row.id)" @change="toggleSelect(row.id)" class="rounded border-slate-600 bg-slate-950 text-indigo-600"></td>
                                <td class="py-2 pr-3">
                                    <span class="text-slate-100" x-text="row.name"></span>
                                    <span x-show="isDirty(row)" class="ml-1 text-[10px] font-semibold uppercase text-amber-300">cambio</span>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        <select :value="row.zone_id === null ? '' : String(row.zone_id)" @change="setZone(row, $event.target.value)"
                                                class="h-8 w-36 rounded-md border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                                            <option value="">Sin zona</option>
                                            <template x-for="zone in zones" :key="'rz'+row.id+'-'+zone.id">
                                                <option :value="String(zone.id)" x-text="zone.name" :selected="row.zone_id === zone.id"></option>
                                            </template>
                                        </select>
                                        <button type="button" x-show="row.zone_id === null && contextZone" @click="setZone(row, String(contextZone))"
                                                class="whitespace-nowrap text-[11px] font-semibold text-indigo-300 hover:text-indigo-200"
                                                x-text="'+ ' + zoneName(contextZone)"></button>
                                    </div>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="relative">
                                        <button type="button" @click="openShared = openShared === row.id ? null : row.id" :disabled="row.zone_id === null"
                                                class="h-8 max-w-[14rem] truncate rounded-md border border-slate-700 bg-slate-950 px-2 text-left text-xs text-slate-200 disabled:opacity-40"
                                                x-text="sharedLabel(row)"></button>
                                        <template x-if="openShared === row.id">
                                            <div @click.outside="openShared = null"
                                                 class="absolute left-0 top-9 z-10 w-48 rounded-lg border border-slate-700 bg-slate-950 p-2 shadow-xl">
                                                <template x-for="zone in zones.filter((z) => z.id !== row.zone_id)" :key="'rs'+row.id+'-'+zone.id">
                                                    <label class="flex items-center gap-2 rounded px-1.5 py-1 text-xs text-slate-200 hover:bg-slate-800">
                                                        <input type="checkbox" :checked="row.shared.includes(zone.id)" @change="toggleShared(row, zone.id)" class="rounded border-slate-600 bg-slate-950 text-indigo-600">
                                                        <span x-text="zone.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                                <td class="py-2 pr-4">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                          :class="{
                                              'bg-rose-500/15 text-rose-300': status(row).tone === 'none',
                                              'bg-sky-500/15 text-sky-300': status(row).tone === 'shared',
                                              'bg-emerald-500/15 text-emerald-300': status(row).tone === 'own',
                                          }"
                                          x-text="status(row).label"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filtered.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Sin clientes con ese filtro.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-4 py-3">
                <p class="text-xs" :class="error ? 'text-rose-300' : 'text-slate-400'"
                   x-text="error || (dirtyCount ? dirtyCount + (dirtyCount === 1 ? ' cambio sin guardar' : ' cambios sin guardar') : 'Sin cambios')"></p>
                <div class="flex gap-2">
                    <button type="button" @click="close()" class="h-9 rounded-lg bg-slate-800 px-3 text-xs font-semibold text-slate-300">Cancelar</button>
                    <button type="button" @click="save()" :disabled="!dirtyCount || saving"
                            class="h-9 rounded-lg bg-indigo-600 px-4 text-xs font-semibold text-white disabled:opacity-40"
                            x-text="saving ? 'Guardando…' : 'Guardar'"></button>
                </div>
            </div>
        </div>
    </div>
</template>
