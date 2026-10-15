<div x-data="geoRequestAlerts" x-init="pollUrl = @js(route('company.supervision.geo.pending')); onReviewPage = @js(request()->routeIs('company.supervision.geo.index')); init()">
    <template x-teleport="body">
        <div x-show="visible.length" x-cloak class="fixed bottom-4 right-4 z-[69] w-full max-w-sm space-y-2">
            <template x-for="row in visible" :key="row.id">
                <div class="rounded-xl border border-teal-500/60 bg-slate-950 p-4 shadow-2xl">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] uppercase tracking-widest text-teal-400">Solicitud de georreferencia</p>
                        <button type="button" class="text-xs text-slate-500 hover:text-slate-300" @click="dismiss(row)" aria-label="Ocultar">✕</button>
                    </div>
                    <p class="mt-1 text-sm font-semibold text-white" x-text="row.installation"></p>
                    <p class="text-xs text-slate-400" x-text="row.client"></p>
                    <p class="mt-2 text-xs text-slate-300">
                        <span x-text="row.supervisor"></span> · <span x-text="row.requested_at"></span>
                        <span x-show="row.distance_m !== null"> · a <span x-text="distance(row)"></span> del punto actual</span>
                    </p>
                    <a :href="row.review_url" class="mt-3 flex h-9 items-center justify-center rounded-lg bg-teal-600 text-sm font-medium text-white hover:bg-teal-500">Revisar</a>
                </div>
            </template>
        </div>
    </template>
</div>
