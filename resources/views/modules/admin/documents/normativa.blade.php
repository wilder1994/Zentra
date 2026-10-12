<x-admin-layout title="Normativa">
    <div class="flex flex-col flex-1 min-h-0 gap-3">
        <div class="flex items-center justify-between gap-3 shrink-0">
            <div>
                <p class="text-xs text-slate-500">Documentos · Normoteca</p>
                <p class="text-xs text-slate-500 mt-1">
                    Globales + contrato por plan (SKU). Publicar nueva versión no altera expedientes ya aceptados.
                </p>
            </div>
            <x-ui.button variant="secondary" :href="route('admin.documents.index')" size="sm">← Documentos</x-ui.button>
        </div>

        <div class="flex-1 min-h-0 space-y-6 overflow-y-auto">
            <section class="space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Documentos globales</h2>
                @forelse ($globals as $type => $versions)
                    @php $current = $versions->first(fn ($v) => $v->isCurrent()) ?? $versions->first(); @endphp
                    <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-white">{{ $current->type->label() }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Versión {{ $current->version }} · vigente desde {{ $current->effective_from->format('d/m/Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded bg-violet-600/20 text-violet-300 border border-violet-500/30">
                                    Vigente
                                </span>
                                @can('platform.documents.manage')
                                    <x-ui.button variant="secondary" size="sm" :href="route('admin.documents.normativa.edit', $current)">
                                        Editar / versionar
                                    </x-ui.button>
                                @endcan
                            </div>
                        </div>
                        <div class="mt-3 rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-sm text-slate-300 whitespace-pre-line leading-relaxed max-h-40 overflow-y-auto">
                            {{ $current->content }}
                        </div>
                        <p class="mt-2 text-[10px] text-slate-600 font-mono">SHA-256: {{ $current->content_hash }}</p>
                        @if ($versions->count() > 1)
                            <details class="mt-3 text-xs text-slate-500">
                                <summary class="cursor-pointer hover:text-slate-300">Historial ({{ $versions->count() }} versiones)</summary>
                                <ul class="mt-2 space-y-1 pl-2 border-l border-slate-700">
                                    @foreach ($versions as $version)
                                        <li>
                                            v{{ $version->version }}
                                            @if ($version->superseded_at)
                                                — sustituida {{ $version->superseded_at->format('d/m/Y') }}
                                            @else
                                                — vigente
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </section>
                @empty
                    <p class="text-sm text-slate-500">Sin documentos globales. Ejecuta el seeder de plataforma.</p>
                @endforelse
            </section>

            <section class="space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Contratos por plan</h2>
                @forelse ($contracts as $sku => $versions)
                    @php
                        $current = $versions->first(fn ($v) => $v->isCurrent()) ?? $versions->first();
                        $planLabel = $current->planLabel();
                    @endphp
                    <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-white">{{ $current->title }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $planLabel ?? $sku }}
                                    · v{{ $current->version }} · desde {{ $current->effective_from->format('d/m/Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded bg-violet-600/20 text-violet-300 border border-violet-500/30">
                                    Vigente
                                </span>
                                @can('platform.documents.manage')
                                    <x-ui.button variant="secondary" size="sm" :href="route('admin.documents.normativa.edit', $current)">
                                        Editar / versionar
                                    </x-ui.button>
                                @endcan
                            </div>
                        </div>
                        <div class="mt-3 rounded-lg border border-slate-800 bg-slate-950/60 p-3 text-sm text-slate-300 whitespace-pre-line leading-relaxed max-h-40 overflow-y-auto">
                            {{ $current->content }}
                        </div>
                        <p class="mt-2 text-[10px] text-slate-600 font-mono">SHA-256: {{ $current->content_hash }}</p>
                    </section>
                @empty
                    <p class="text-sm text-slate-500">Sin contratos por SKU. Ejecuta el seeder de plataforma.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-admin-layout>
