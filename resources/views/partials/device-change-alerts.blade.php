<div x-data="deviceChangeAlerts" x-init="pollUrl = @js(route('company.devices.pending')); csrf = @js(csrf_token()); init()">
    <template x-teleport="body">
        <div x-show="requests.length" x-cloak class="fixed bottom-4 right-4 z-[70] w-full max-w-sm space-y-2">
            <template x-for="row in requests" :key="row.id">
                <div class="rounded-xl border border-amber-500/60 bg-slate-950 p-4 shadow-2xl">
                    <p class="text-[11px] uppercase tracking-widest text-amber-400">Cambio de dispositivo</p>
                    <p class="mt-1 text-sm font-semibold text-white" x-text="row.supervisor"></p>
                    <p class="text-xs text-slate-400" x-text="row.username"></p>
                    <p class="mt-2 text-xs text-slate-300">
                        <span x-text="row.previous_device"></span> → <span class="text-white" x-text="row.device"></span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Solicitado <span x-text="row.requested_at"></span> · vence en <span x-text="left(row)"></span>
                    </p>
                    <p x-show="row.error" class="mt-2 text-xs text-red-400" x-text="row.error"></p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="flex-1 h-9 rounded-lg bg-emerald-600 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60"
                                :disabled="row.busy" @click="resolve(row, true)">Aceptar</button>
                        <button type="button" class="flex-1 h-9 rounded-lg border border-slate-700 text-sm text-slate-200 hover:bg-slate-800 disabled:opacity-60"
                                :disabled="row.busy" @click="resolve(row, false)">Rechazar</button>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
