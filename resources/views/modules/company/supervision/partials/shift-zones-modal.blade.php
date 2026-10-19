<div x-data="shiftZones()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-slate-950/70" @click="open = false"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-800 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Zonas del turno</h3>
                        <p class="mt-0.5 text-xs text-slate-400" x-text="user"></p>
                    </div>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                </div>
                <div class="space-y-3 px-4 py-3">
                    <p x-show="loading" class="text-sm text-slate-400">Cargando…</p>
                    <template x-if="!loading">
                        <div class="space-y-3">
                            <div class="rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Zona con la que abrió turno</p>
                                <p class="mt-0.5 font-semibold text-white" x-text="base ? base.name : 'Sin zona'"></p>
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-slate-400">Habilitar además (solo este turno):</p>
                                <div class="max-h-64 space-y-1 overflow-auto">
                                    <template x-for="zone in options" :key="'sz'+zone.id">
                                        <label class="flex items-start gap-2 rounded-md px-2 py-1.5 hover:bg-slate-800">
                                            <input type="checkbox" :checked="picked.includes(Number(zone.id))" @change="toggle(zone.id)" class="mt-0.5 rounded border-slate-600 bg-slate-950 text-indigo-600">
                                            <span class="min-w-0">
                                                <span class="block text-sm text-slate-100" x-text="zone.name"></span>
                                                <span class="block text-[11px] text-slate-500" x-show="addedInfo(zone.id)" x-text="addedInfo(zone.id)"></span>
                                            </span>
                                        </label>
                                    </template>
                                    <p x-show="!options.length" class="text-xs text-slate-500">No hay otras zonas activas.</p>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-500">El supervisor ve los clientes de estas zonas en la app al abrir Revista. Al cerrar el turno se quitan solas.</p>
                        </div>
                    </template>
                    <p x-show="error" class="text-xs text-rose-300" x-text="error"></p>
                    <p x-show="notice" class="text-xs text-emerald-300" x-text="notice"></p>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-800 px-4 py-3">
                    <button type="button" @click="open = false" class="h-9 rounded-lg bg-slate-800 px-3 text-xs font-semibold text-slate-300">Cerrar</button>
                    <button type="button" @click="save()" :disabled="loading || saving"
                            class="h-9 rounded-lg bg-indigo-600 px-4 text-xs font-semibold text-white disabled:opacity-40"
                            x-text="saving ? 'Guardando…' : 'Guardar'"></button>
                </div>
            </div>
        </div>
    </template>
</div>
