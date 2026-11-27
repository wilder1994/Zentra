@php
    $canUpdateStatus = $canUpdateStatus ?? false;
    $canMove = $canMove ?? false;
    $statusAction = $statusAction ?? null;
    $moveRoute = $moveRoute ?? 'client.observatory.events.reports.move';
    $moveOptions = $moveOptions ?? [];
    $folioMap = $folioMap ?? null;
    $isNuevo = $event->status === \App\Enums\ObservatoryEventStatus::Nuevo;
    $isOpen = $event->status === \App\Enums\ObservatoryEventStatus::EnAtencion;
    $isClosed = $event->status === \App\Enums\ObservatoryEventStatus::Cerrado;
    $comunaName = trim((string) ($folioMap['comuna_name'] ?? ''));
    $version = $event->version();
    $statusError = $errors->has('note') && old('status') !== null;
    $toneColor = match ($event->status?->value) {
        'nuevo' => '#ef4444',
        'en_atencion' => '#f59e0b',
        'cerrado' => '#10b981',
        default => 'var(--ui-accent)',
    };
    $moveBase = str_replace('999999999', '__ID__', route($moveRoute, [$event, 999999999]));
@endphp
<style>
    .obs-folio { display: grid; grid-template-columns: 1fr; gap: 0.6rem; }
    .obs-folio > * { height: 20rem; min-height: 0; min-width: 0; }
    @media (min-width: 768px) {
        .obs-folio { grid-template-columns: 1fr 1fr; }
        .obs-folio > * { height: 18rem; }
    }
    @media (min-width: 768px) and (min-height: 640px) {
        .obs-folio {
            height: calc(100dvh - 8.5rem);
            grid-template-rows: minmax(0, 1fr) minmax(0, 1fr);
        }
        .obs-folio > * { height: auto; min-height: 0; }
    }
    .obs-pane {
        display: flex;
        min-height: 0;
        flex-direction: column;
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid var(--folio-tone);
        background: color-mix(in srgb, var(--ui-accent) 9%, var(--ui-surface));
        padding: 0.55rem 0.7rem 0.6rem;
    }
    .obs-pane-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.35rem;
        padding-bottom: 0.3rem;
        border-bottom: 1px solid color-mix(in srgb, var(--ui-accent) 35%, transparent);
        color: var(--ui-accent-hover);
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .obs-facts {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto minmax(0, 1fr);
        gap: 0.18rem 0.55rem;
        align-items: baseline;
    }
    .obs-facts dt {
        color: var(--ui-accent);
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .obs-facts dd { color: var(--ui-text); font-size: 0.75rem; line-height: 1.25; }
    .obs-facts .wide { grid-column: 2 / -1; }
    .obs-pill {
        display: inline-flex;
        height: 1.35rem;
        align-items: center;
        border-radius: 999px;
        border: 1px solid color-mix(in srgb, var(--folio-tone) 70%, transparent);
        background: color-mix(in srgb, var(--folio-tone) 18%, transparent);
        color: var(--folio-tone);
        padding: 0 0.5rem;
        font-size: 0.68rem;
        font-weight: 700;
    }
    .obs-item {
        border-radius: 0.5rem;
        border: 1px solid color-mix(in srgb, var(--ui-accent) 28%, var(--ui-border));
        border-left: 3px solid var(--ui-accent);
        background: color-mix(in srgb, var(--ui-surface) 82%, var(--ui-accent));
        padding: 0.35rem 0.5rem;
    }
    .obs-item-log { border-left-color: var(--ui-caution); }
    .obs-pass {
        border: 0;
        background: none;
        padding: 0;
        color: var(--ui-accent-hover);
        font-size: 0.68rem;
        font-weight: 700;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    .obs-pass:hover { color: var(--ui-accent); }
    .obs-pane-info { border-top-width: 3px; }
    .obs-folio-map {
        border-radius: 0.75rem;
        border: 1px solid var(--folio-tone);
        overflow: hidden;
    }
</style>
<div class="obs-folio" style="--folio-tone: {{ $toneColor }}" x-data="{
    noteOpen: {{ $statusError && old('status', '') !== 'cerrado' ? 'true' : 'false' }},
    closeOpen: {{ $statusError && old('status') === 'cerrado' ? 'true' : 'false' }},
    versionOpen: false,
    moves: @js($moveOptions),
    codes: @js($event->reports->mapWithKeys(fn ($report) => [(string) $report->id => $report->code()])),
    moveBase: @js($moveBase),
    moveOpen: {{ (int) old('report_id', 0) }},
    siteId: @js((string) old('installation_id', (string) $event->installation_id)),
    dest: @js((string) old('destination', 'new')),
    folios() {
        const row = this.moves.find((item) => String(item.id) === String(this.siteId));
        return row ? row.folios : [];
    },
    openMove(id) {
        this.moveOpen = id;
        this.siteId = @js((string) $event->installation_id);
        this.dest = 'new';
    }
}">
    <div class="relative flex min-h-0 flex-col overflow-hidden">
        @if ($folioMap)
            @include('modules.observatory.partials.map', [
                'map' => $folioMap,
                'mapCanvasClass' => 'h-full min-h-0',
                'folioFocus' => true,
            ])
        @else
            <div class="flex h-full items-center rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                <p class="text-sm text-slate-500">Sin mapa para este folio.</p>
            </div>
        @endif
    </div>

    <div class="obs-pane obs-pane-info">
        <div class="min-h-0 flex-1 overflow-y-auto sidebar-scroll pr-1">
            <div class="obs-pane-head">
                <span class="font-mono normal-case tracking-normal" style="color: var(--ui-text)">{{ $event->folio() }}</span>
                <span class="obs-pill">{{ $event->statusLabel() }}</span>
            </div>
            <p class="mb-1.5 truncate text-sm font-semibold" style="color: var(--ui-text)">{{ $event->installation?->name }}</p>
            <dl class="obs-facts">
                <dt>Dirección</dt>
                <dd class="wide">{{ $event->installation?->addressLine() ?: '—' }}</dd>
                <dt>Comuna</dt>
                <dd>{{ $comunaName !== '' ? $comunaName : 'Fuera de comuna' }}</dd>
                <dt>DANE</dt>
                <dd>{{ $event->installation?->dane_code ?: '—' }}</dd>
                <dt>Cliente</dt>
                <dd>{{ $event->client?->name ?: '—' }}</dd>
                <dt>Abierto</dt>
                <dd class="tabular-nums">{{ $event->opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                @if ($isClosed)
                    <dt>Cerró</dt>
                    <dd>{{ $event->closedBy?->name ?? '—' }}</dd>
                    <dt>Cerrado</dt>
                    <dd class="tabular-nums">{{ $event->closed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                @endif
                <dt>Versión</dt>
                <dd class="relative">
                    <button type="button" class="obs-pass"
                            @click="versionOpen = !versionOpen"
                            @mouseenter="versionOpen = true"
                            @mouseleave="versionOpen = false">
                        {{ $version }} · por qué
                    </button>
                    <div x-show="versionOpen" x-cloak
                         class="absolute left-0 z-20 mt-1 w-64 rounded-lg p-2 text-[11px] shadow-lg"
                         style="border: 1px solid var(--ui-border-strong); background: var(--ui-surface); color: var(--ui-text)"
                         @mouseenter="versionOpen = true"
                         @mouseleave="versionOpen = false">
                        {{ $event->versionReason() }}
                    </div>
                </dd>
            </dl>
        </div>
        @if ($canUpdateStatus && $statusAction && ! $isClosed)
            <div class="mt-2 flex shrink-0 flex-wrap gap-1.5 border-t pt-2" style="border-color: color-mix(in srgb, var(--ui-accent) 35%, transparent)">
                <button type="button" class="ui-btn-primary h-7 rounded-md px-2.5 text-[11px] font-semibold"
                        @click="noteOpen = true">
                    {{ $isNuevo ? 'Pasar a en atención' : 'Agregar' }}
                </button>
                @if ($isOpen)
                    <button type="button" class="h-7 rounded-md border px-2.5 text-[11px] font-semibold"
                            style="border-color: var(--ui-success); color: var(--ui-success)"
                            @click="closeOpen = true">
                        Cerrar folio
                    </button>
                @endif
            </div>
        @elseif (! $canUpdateStatus)
            <p class="mt-2 shrink-0 border-t pt-2 text-[11px]" style="border-color: color-mix(in srgb, var(--ui-accent) 35%, transparent); color: var(--ui-muted)">Los estados los cierra el admin de instalaciones de esa instalación.</p>
        @endif
    </div>

    <div class="obs-pane">
        <p class="obs-pane-head">Novedades · {{ $event->reports->count() }}</p>
        <x-ui.field-error :messages="$errors->get('report')" />
        <div class="min-h-0 flex-1 space-y-1.5 overflow-y-auto sidebar-scroll pr-1">
            @forelse ($event->reports as $report)
                <article class="obs-item">
                    <p class="flex items-baseline justify-between gap-2">
                        <span class="font-mono text-[11px] font-semibold" style="color: var(--ui-accent-hover)">{{ $report->code() }}</span>
                        @if ($canMove)
                            <button type="button" class="obs-pass" @click="openMove({{ $report->id }})">Pasar reporte</button>
                        @endif
                    </p>
                    <p class="text-[11px]" style="color: var(--ui-muted)">
                        {{ $report->kindLabel() }} · {{ $report->originLabel() }}
                        · {{ $report->created_at?->format('d/m/Y H:i') }}
                    </p>
                    <p class="text-xs whitespace-pre-line" style="color: var(--ui-text)">{{ $report->body }}</p>
                    <p class="text-[11px]" style="color: var(--ui-text-secondary)">
                        {{ $report->reporterLabel() }}
                        @if (! $report->is_anonymous && $report->reporter_phone)
                            · {{ $report->reporter_phone }}
                        @endif
                        @if (! $report->is_anonymous && $report->reportedBy)
                            · usuario {{ $report->reportedBy->name }}
                        @endif
                    </p>
                    @if ($report->hasCoordinates())
                        <p class="text-[10px]" style="color: var(--ui-faint)">Pin {{ number_format((float) $report->latitude, 5) }}, {{ number_format((float) $report->longitude, 5) }}</p>
                    @endif
                    @if ($report->videoUrl())
                        <video src="{{ $report->videoUrl() }}" controls class="mt-2 max-h-48 w-full rounded-lg border border-slate-800"></video>
                    @endif
                    @if ($report->photoUrls() !== [])
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($report->photoUrls() as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener">
                                    <img src="{{ $url }}" alt="Evidencia" class="h-20 w-20 rounded-lg border border-slate-800 object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500">Sin reportes.</p>
            @endforelse
        </div>
    </div>

    <div class="obs-pane">
        <p class="obs-pane-head">Bitácora · versión {{ $version }}</p>
        <div class="min-h-0 flex-1 space-y-1.5 overflow-y-auto sidebar-scroll pr-1">
            @forelse ($event->statusLogs as $index => $log)
                <article class="obs-item obs-item-log">
                    <p class="text-[11px]" style="color: var(--ui-muted)">
                        <span class="font-semibold" style="color: var(--ui-caution)">Versión {{ $version - $index }}</span>
                        @if ($log->from_status !== $log->to_status)
                            · {{ $log->from_status?->label() }} → {{ $log->to_status?->label() }}
                        @endif
                        · {{ $log->user?->name ?? '—' }}
                        · {{ $log->created_at?->format('d/m/Y H:i') }}
                    </p>
                    @if (filled($log->note))
                        <p class="text-xs whitespace-pre-line" style="color: var(--ui-text)">{{ $log->note }}</p>
                    @endif
                </article>
            @empty
                <p class="text-xs" style="color: var(--ui-muted)">Versión 1 · Apertura del folio.</p>
            @endforelse
        </div>
    </div>

    @if ($canMove)
        <template x-teleport="body">
            <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="moveOpen = 0">
                <div class="absolute inset-0 bg-slate-950/70" @click="moveOpen = 0"></div>
                <form method="POST" class="relative w-full max-w-md space-y-2.5 rounded-xl p-4"
                      style="border: 1px solid color-mix(in srgb, var(--ui-accent) 50%, var(--ui-border)); background: var(--ui-surface)"
                      :action="moveBase.replace('__ID__', String(moveOpen))"
                      @click.stop>
                    @csrf
                    <input type="hidden" name="report_id" :value="moveOpen">
                    <h3 class="text-base font-semibold" style="color: var(--ui-text)">
                        Pasar <span class="font-mono" style="color: var(--ui-accent-hover)" x-text="codes[String(moveOpen)] || ''"></span>
                    </h3>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide" style="color: var(--ui-accent)">Instalación
                        <select name="installation_id" x-model="siteId" @change="dest = 'new'"
                                class="mt-1 h-9 w-full rounded-lg px-2 text-xs normal-case tracking-normal"
                                style="border: 1px solid var(--ui-border-strong); background: var(--ui-surface-muted); color: var(--ui-text)">
                            @foreach ($moveOptions as $site)
                                <option value="{{ $site['id'] }}">{{ $site['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide" style="color: var(--ui-accent)">Folio
                        <select name="destination" x-model="dest"
                                class="mt-1 h-9 w-full rounded-lg px-2 text-xs normal-case tracking-normal"
                                style="border: 1px solid var(--ui-border-strong); background: var(--ui-surface-muted); color: var(--ui-text)">
                            <option value="new">Folio nuevo</option>
                            <template x-for="folio in folios()" :key="folio.id">
                                <option :value="String(folio.id)" x-text="folio.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide" style="color: var(--ui-accent)">Por qué
                        <textarea name="note" rows="4" required minlength="10" maxlength="2000"
                                  class="mt-1 w-full rounded-lg px-2 py-1.5 text-xs normal-case tracking-normal"
                                  style="border: 1px solid var(--ui-border-strong); background: var(--ui-surface-muted); color: var(--ui-text)"
                                  placeholder="Por qué se pasa este reporte…">{{ old('report_id') ? old('note') : '' }}</textarea>
                    </label>
                    <x-ui.field-error :messages="$errors->get('note')" />
                    <x-ui.field-error :messages="$errors->get('destination')" />
                    <x-ui.field-error :messages="$errors->get('installation_id')" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="ui-btn-secondary h-8 rounded-md px-3 text-xs" @click="moveOpen = 0">Cancelar</button>
                        <button type="submit" class="ui-btn-primary h-8 rounded-md px-3 text-xs font-semibold">Pasar</button>
                    </div>
                </form>
            </div>
        </template>
    @endif

    @if ($canUpdateStatus && $statusAction && ! $isClosed)
        <template x-teleport="body">
            <div x-show="noteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="noteOpen = false">
                <div class="absolute inset-0 bg-slate-950/70" @click="noteOpen = false"></div>
                <form method="POST" action="{{ $statusAction }}" class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 p-5 space-y-3"
                      @click.stop>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="en_atencion">
                    <h3 class="text-lg font-semibold text-white">{{ $isNuevo ? 'Pasar a en atención' : 'Agregar observación' }}</h3>
                    <p class="text-sm text-slate-400">
                        {{ $isNuevo ? 'Describe cómo vas a atender el folio. Quedará en atención.' : 'El folio sigue en atención. La nota queda en la bitácora.' }}
                    </p>
                    <textarea name="note" rows="5" required minlength="10" maxlength="2000"
                              class="w-full px-3 py-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white"
                              placeholder="Qué se está haciendo…">{{ $statusError ? old('note') : '' }}</textarea>
                    <x-ui.field-error :messages="$errors->get('note')" />
                    <x-ui.field-error :messages="$errors->get('status')" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="h-9 px-4 rounded-lg border border-slate-700 text-sm text-slate-200" @click="noteOpen = false">Cancelar</button>
                        <button type="submit" class="h-9 px-4 rounded-lg bg-indigo-600 text-sm font-semibold text-white">Agregar</button>
                    </div>
                </form>
            </div>
        </template>
        @if ($isOpen)
            <template x-teleport="body">
                <div x-show="closeOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="closeOpen = false">
                    <div class="absolute inset-0 bg-slate-950/70" @click="closeOpen = false"></div>
                    <form method="POST" action="{{ $statusAction }}" class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 p-5 space-y-3"
                          @click.stop>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cerrado">
                        <h3 class="text-lg font-semibold text-white">Cerrar folio</h3>
                        <p class="text-sm text-slate-400">Cómo se resolvió. No se reabre.</p>
                        <textarea name="note" rows="5" required minlength="10" maxlength="2000"
                                  class="w-full px-3 py-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white"
                                  placeholder="Cómo se cerró…">{{ $statusError ? old('note') : '' }}</textarea>
                        <x-ui.field-error :messages="$errors->get('note')" />
                        <x-ui.field-error :messages="$errors->get('status')" />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="h-9 px-4 rounded-lg border border-slate-700 text-sm text-slate-200" @click="closeOpen = false">Cancelar</button>
                            <button type="submit" class="h-9 px-4 rounded-lg bg-emerald-600 text-sm font-semibold text-white">Cerrar folio</button>
                        </div>
                    </form>
                </div>
            </template>
        @endif
    @endif
</div>
