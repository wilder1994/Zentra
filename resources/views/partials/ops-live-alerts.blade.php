@php
    $opsPoll = $opsPoll ?? null;
    $opsPanicUrl = $opsPanicUrl ?? '';
    $opsClaimUrl = $opsClaimUrl ?? '';
    $showPanic = (bool) ($showPanic ?? false);
@endphp
@if ($opsPoll)
<div
    x-data="opsLiveAlerts"
    x-init="pollUrl = @js($opsPoll); panicUrl = @js($opsPanicUrl); claimUrl = @js($opsClaimUrl); csrf = @js(csrf_token()); init()"
>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-red-950/80"></div>
            <div class="relative w-full max-w-lg rounded-2xl border border-red-500 bg-slate-950 p-6 text-center shadow-2xl ops-alert-pulse">
                <p class="text-xs uppercase tracking-widest text-red-400">Alerta</p>
                <h3 class="mt-2 text-2xl font-semibold text-white" x-text="title"></h3>
                <p class="mt-3 text-sm text-slate-200" x-text="body"></p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <button type="button" class="inline-flex h-10 items-center rounded-lg bg-red-600 px-5 text-sm font-medium text-white" @click="ack()">Enterado</button>
                    <button type="button" x-show="canAttend && type === 'panic'"
                            class="inline-flex h-10 items-center rounded-lg bg-white px-5 text-sm font-medium text-slate-900"
                            @click="attend()">Atender</button>
                </div>
            </div>
        </div>
    </template>
    <template x-teleport="body">
        <div class="fixed bottom-4 right-4 z-[70] flex w-full max-w-sm flex-col gap-2">
            <template x-for="row in toasts" :key="row.id">
                <article class="ops-obs-toast rounded-xl border bg-slate-950 p-3 text-sm text-slate-100 shadow-lg">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold" x-text="row.title"></p>
                        <button type="button" class="text-slate-400" @click="dismissToast(row.id)" aria-label="Cerrar">×</button>
                    </div>
                    <p class="mt-1 text-slate-300" x-text="row.body"></p>
                    <div class="mt-2 flex gap-3 text-xs font-semibold">
                        <a x-show="row.map_url" :href="row.map_url" class="text-teal-300" @click="stopReportAlarm()">Ver mapa</a>
                        <a x-show="row.can_attend && row.show_url" :href="row.show_url" class="text-amber-300" @click="stopReportAlarm()">Atender</a>
                    </div>
                </article>
            </template>
        </div>
    </template>

    @if ($showPanic)
        <button type="button" class="w-full mb-2 inline-flex items-center justify-center h-9 rounded-lg bg-red-700 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-60" :disabled="panicBusy" @click="sendPanic()">
            Pánico
        </button>
    @endif
</div>
@endif
