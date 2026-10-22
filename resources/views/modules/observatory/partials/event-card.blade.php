@php
    $canUpdateStatus = $canUpdateStatus ?? false;
    $canMerge = $canMerge ?? false;
    $canDetach = $canDetach ?? false;
    $statusAction = $statusAction ?? null;
    $mergeAction = $mergeAction ?? null;
    $detachRoute = $detachRoute ?? 'client.observatory.events.reports.detach';
    $mergeCandidates = $mergeCandidates ?? collect();
    $folioMap = $folioMap ?? null;
    $isNuevo = $event->status === \App\Enums\ObservatoryEventStatus::Nuevo;
    $isOpen = $event->status === \App\Enums\ObservatoryEventStatus::EnAtencion;
    $isClosed = $event->status === \App\Enums\ObservatoryEventStatus::Cerrado;
    $tone = match ($event->status?->value) {
        'nuevo' => 'text-amber-300 border-amber-700/50 bg-amber-950/40',
        'en_atencion' => 'text-indigo-300 border-indigo-700/50 bg-indigo-950/40',
        'cerrado' => 'text-emerald-300 border-emerald-700/50 bg-emerald-950/40',
        default => 'text-slate-300 border-slate-700 bg-slate-900',
    };
@endphp
<div class="space-y-4" x-data="{
    noteOpen: {{ $errors->has('note') && old('status', '') !== 'cerrado' ? 'true' : 'false' }},
    closeOpen: {{ $errors->has('note') && old('status') === 'cerrado' ? 'true' : 'false' }}
}">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="relative h-[28rem] min-h-0 overflow-hidden">
            @if ($folioMap)
                @include('modules.observatory.partials.map', [
                    'map' => $folioMap,
                    'mapCanvasClass' => 'h-full min-h-0',
                ])
                <div class="absolute top-14 left-2 z-10 w-40 max-h-32 overflow-y-auto rounded-lg border border-slate-700/80 bg-slate-950/90 shadow-lg">
                    @include('modules.observatory.partials.pin-legend', ['map' => $folioMap])
                </div>
            @else
                <div class="flex h-full items-center rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-sm text-slate-500">Sin mapa para este folio.</p>
                </div>
            @endif
        </div>

        <div class="flex h-[28rem] min-h-0 flex-col overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80 p-4 sm:p-5">
            <div class="min-h-0 flex-1 overflow-y-auto sidebar-scroll pr-1">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-xs text-indigo-300">{{ $event->folio() }}</p>
                        <h3 class="mt-1 text-xl font-semibold text-white">{{ $event->installation?->name }}</h3>
                        <p class="mt-1 text-sm text-slate-400">{{ $event->client?->name }} · {{ $event->kindLabel() }}</p>
                        @if ($event->installation?->addressLine())
                            <p class="mt-1 text-xs text-slate-500">{{ $event->installation->addressLine() }}</p>
                        @endif
                        <p class="mt-2 text-sm text-slate-300">{{ $event->title }}</p>
                    </div>
                    <span class="inline-flex h-8 shrink-0 items-center rounded-lg border px-3 text-xs font-semibold {{ $tone }}">{{ $event->statusLabel() }}</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-slate-500">Abierto</dt>
                        <dd class="mt-0.5 text-slate-200 tabular-nums">{{ $event->opened_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-slate-500">Cerrado</dt>
                        <dd class="mt-0.5 text-slate-200 tabular-nums">{{ $event->closed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-slate-500">Cerró</dt>
                        <dd class="mt-0.5 text-slate-200">{{ $event->closedBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] uppercase tracking-wide text-slate-500">Reportes</dt>
                        <dd class="mt-0.5 text-slate-200">{{ $event->reports->count() }}</dd>
                    </div>
                </dl>
            </div>
            @if ($canUpdateStatus && $statusAction && ! $isClosed)
                <div class="mt-3 flex shrink-0 flex-wrap gap-2 border-t border-slate-800 pt-3">
                    <button type="button" class="h-9 px-4 rounded-lg bg-indigo-600 text-xs font-semibold text-white"
                            @click="noteOpen = true">
                        {{ $isNuevo ? 'Pasar a en atención' : 'Agregar' }}
                    </button>
                    @if ($isOpen)
                        <button type="button" class="h-9 px-4 rounded-lg border border-emerald-700 text-xs font-semibold text-emerald-200 hover:bg-emerald-950/40"
                                @click="closeOpen = true">
                            Cerrar folio
                        </button>
                    @endif
                </div>
            @elseif (! $canUpdateStatus)
                <p class="mt-3 shrink-0 border-t border-slate-800 pt-3 text-xs text-slate-500">Los estados los cierra el admin de instalaciones de esa instalación.</p>
            @endif
        </div>

        <div class="flex h-80 min-h-0 flex-col overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="shrink-0 text-sm font-medium text-white">Novedades reportadas</p>
            <x-ui.field-error :messages="$errors->get('report')" />
            <div class="mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto sidebar-scroll pr-1">
                @forelse ($event->reports as $report)
                    <article class="rounded-lg border border-slate-800 bg-slate-950/50 p-3 space-y-1.5">
                        <p class="text-xs text-slate-500">
                            {{ $report->kindLabel() }} · {{ $report->originLabel() }}
                            · {{ $report->created_at?->format('d/m/Y H:i') }}
                        </p>
                        <p class="text-sm text-slate-200 whitespace-pre-line">{{ $report->body }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $report->reporterLabel() }}
                            @if (! $report->is_anonymous && $report->reporter_phone)
                                · {{ $report->reporter_phone }}
                            @endif
                            @if (! $report->is_anonymous && $report->reportedBy)
                                · usuario {{ $report->reportedBy->name }}
                            @endif
                        </p>
                        @if ($report->hasCoordinates())
                            <p class="text-[11px] text-slate-500">Pin {{ number_format((float) $report->latitude, 5) }}, {{ number_format((float) $report->longitude, 5) }}</p>
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
                        @if ($canDetach && $event->reports->count() > 1)
                            <form method="POST" action="{{ route($detachRoute, [$event, $report]) }}" class="pt-2">
                                @csrf
                                <button type="submit" class="h-8 px-2 rounded-md border border-slate-700 text-[11px] font-semibold text-slate-300 hover:bg-slate-800">
                                    Sacar a folio nuevo
                                </button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Sin reportes.</p>
                @endforelse
            </div>
        </div>

        <div class="flex h-80 min-h-0 flex-col overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="shrink-0 text-sm font-medium text-white">Bitácora</p>
            <div class="mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto sidebar-scroll pr-1">
                @forelse ($event->statusLogs as $log)
                    <article class="rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2.5 space-y-1">
                        <p class="text-xs text-slate-400">
                            @if ($log->from_status === $log->to_status)
                                Observación
                            @else
                                {{ $log->from_status?->label() }} → {{ $log->to_status?->label() }}
                            @endif
                            · {{ $log->user?->name ?? '—' }}
                            · {{ $log->created_at?->format('d/m/Y H:i') }}
                        </p>
                        @if (filled($log->note))
                            <p class="text-sm text-slate-200 whitespace-pre-line">{{ $log->note }}</p>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Aún no hay observaciones ni cambios de estado.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($canMerge && $mergeAction && $mergeCandidates->isNotEmpty())
        <form method="POST" action="{{ $mergeAction }}" class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-3">
            @csrf
            <p class="text-sm font-medium text-white">Unir folio</p>
            <p class="text-xs text-slate-500">Trae los reportes de otro evento de este colegio a este folio. El otro folio se elimina.</p>
            <select name="source_event_id" required class="w-full h-11 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                <option value="">Elegir folio…</option>
                @foreach ($mergeCandidates as $candidate)
                    <option value="{{ $candidate->id }}">
                        {{ $candidate->folio() }} · {{ $candidate->title }} · {{ $candidate->statusLabel() }} · {{ $candidate->reports_count }} reporte{{ (int) $candidate->reports_count === 1 ? '' : 's' }}
                    </option>
                @endforeach
            </select>
            <x-ui.field-error :messages="$errors->get('source_event_id')" />
            <button type="submit" class="h-9 px-3 rounded-lg bg-indigo-600 text-xs font-semibold text-white">Unir aquí</button>
        </form>
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
                              placeholder="Qué se está haciendo…">{{ old('note') }}</textarea>
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
                                  placeholder="Cómo se cerró…">{{ old('note') }}</textarea>
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
