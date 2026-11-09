@php
    $hubConfig = [
        'groups' => $groups,
        'openGroup' => $openGroup,
        'openCatalog' => $openCatalog,
        'panelUrl' => $panelUrl,
    ];
@endphp

<x-company-layout title="Ajustes">
    <div
        class="relative min-h-[70vh]"
        x-data="settingsHub(@js($hubConfig))"
        @keydown.escape.window="if (layer !== 'hub') back()"
    >
        <div class="mb-6 max-w-2xl">
            <p class="text-sm text-slate-400">
                Configura catálogos de la empresa, del sitio y de la app de campo. Elige un bloque y abre el ajuste que necesites.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3" x-show="layer === 'hub'" x-cloak>
            @foreach ($groups as $key => $group)
                <button
                    type="button"
                    @click="openGroup('{{ $key }}', $event)"
                    class="settings-hub-card group text-left rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 p-5 shadow-lg shadow-black/20 transition duration-300 hover:-translate-y-0.5 hover:border-indigo-500/40 hover:shadow-indigo-950/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-indigo-300/80">{{ $loop->iteration < 10 ? '0'.$loop->iteration : $loop->iteration }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-white">{{ $group['label'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $group['blurb'] }}</p>
                        </div>
                        <span class="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-700 text-slate-400 transition group-hover:border-indigo-400 group-hover:text-indigo-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 6l6 6-6 6"/></svg>
                        </span>
                    </div>
                    <p class="mt-5 text-xs text-slate-500">{{ count($group['items']) }} ajustes</p>
                </button>
            @endforeach
        </div>

        <div
            x-show="layer !== 'hub'"
            x-cloak
            class="fixed inset-0 z-40"
            @click.self="back()"
        >
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-[2px]" @click="back()"></div>

            <div
                x-ref="stage"
                class="settings-hub-stage absolute overflow-hidden border border-slate-700 bg-slate-900 shadow-2xl shadow-black/50"
                :class="layer === 'catalog' ? 'settings-hub-stage-catalog' : 'settings-hub-stage-group'"
                :style="stageStyle()"
            >
                <div class="flex items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
                    <div class="min-w-0">
                        <button type="button" @click="back()" class="text-[11px] font-semibold uppercase tracking-wide text-indigo-300 hover:text-indigo-200">
                            <span x-text="layer === 'catalog' ? ('← ' + groupLabel()) : '← Ajustes'"></span>
                        </button>
                        <h3 class="truncate text-base font-semibold text-white" x-text="layer === 'catalog' ? catalogLabel() : groupLabel()"></h3>
                    </div>
                    <button type="button" @click="closeAll()" class="rounded-lg px-2 py-1 text-xs text-slate-400 hover:bg-slate-800 hover:text-white">Cerrar</button>
                </div>

                <div class="max-h-[min(78vh,760px)] overflow-y-auto p-4" x-show="layer === 'group'">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="item in items()" :key="item.key">
                            <button
                                type="button"
                                class="settings-hub-mini rounded-xl border border-slate-800 bg-slate-950/70 p-4 text-left transition hover:border-indigo-500/50 hover:bg-slate-950"
                                @click="openCatalog(item.key, $event)"
                            >
                                <p class="text-sm font-semibold text-white" x-text="item.label"></p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500" x-text="item.blurb"></p>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="max-h-[min(78vh,760px)] overflow-y-auto p-4" x-show="layer === 'catalog'" x-cloak>
                    <div x-show="loading" class="py-16 text-center text-sm text-slate-500">Cargando…</div>
                    <div x-show="error" class="rounded-lg border border-rose-800/50 bg-rose-950/30 px-3 py-2 text-sm text-rose-200" x-text="error"></div>
                    <div x-ref="panel" x-show="!loading && !error" x-html="panelHtml"></div>
                </div>
            </div>
        </div>
    </div>
</x-company-layout>
