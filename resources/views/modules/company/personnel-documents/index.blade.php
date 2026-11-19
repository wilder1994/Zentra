<x-company-layout title="Documentos">
    <div
        class="space-y-4"
        x-data="documentBrowser(@js([
            'q' => $q,
            'tenure' => $tenure,
            'indexing' => $indexing,
            'url' => route('company.personnel-documents.index'),
        ]))"
    >
        <form method="get" action="{{ route('company.personnel-documents.index') }}" class="flex flex-wrap items-center gap-2" @submit.prevent="run()">
            <div class="min-w-[16rem] flex-1">
                <x-ui.input name="q" x-model="q" placeholder="Nombre o cédula" autocomplete="off" @input="schedule()" />
            </div>
            <div class="relative">
                <button type="button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-700 px-3 text-sm text-slate-200 hover:bg-slate-800" @click="open = !open" :class="tenure || indexing ? 'border-indigo-500 text-white' : ''">
                    Filtros
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 p-3 shadow-xl">
                    <label class="block text-xs text-slate-400">
                        Antigüedad
                        <select x-model="tenure" @change="run()" class="mt-1 h-9 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 text-sm text-white">
                            <option value="">Todas</option>
                            <option value="lt1">Menos de 1 año</option>
                            <option value="y1_3">De 1 a 3 años</option>
                            <option value="y3_5">De 3 a 5 años</option>
                            <option value="gt5">Más de 5 años</option>
                            <option value="none">Sin fecha de ingreso</option>
                        </select>
                    </label>
                    <label class="mt-3 block text-xs text-slate-400">
                        Indexación
                        <select x-model="indexing" @change="run()" class="mt-1 h-9 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 text-sm text-white">
                            <option value="">Todas</option>
                            <option value="on">Activo</option>
                            <option value="off">Desactivado</option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="shrink-0 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5">
                <p class="text-xs uppercase tracking-wide text-slate-500 leading-none">Ocupado</p>
                <p class="mt-1 text-sm font-semibold text-white leading-none">{{ $storageLabel }}</p>
            </div>
            @if ($canUpload ?? false)
                <x-ui.button type="button" size="sm" onclick="window.dispatchEvent(new CustomEvent('open-parafiscal-import'))">Carga masiva planilla</x-ui.button>
            @endif
        </form>

        <div x-ref="results">
            @include('modules.company.personnel-documents.partials.results')
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('documentBrowser', (config) => ({
                q: config.q || '',
                tenure: config.tenure || '',
                indexing: config.indexing || '',
                open: false,
                token: 0,
                timer: null,
                schedule() {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.run(), 250);
                },
                async run() {
                    const current = ++this.token;
                    const url = new URL(config.url, window.location.origin);
                    const term = this.q.trim();
                    if (term !== '') url.searchParams.set('q', term);
                    if (this.tenure) url.searchParams.set('tenure', this.tenure);
                    if (this.indexing) url.searchParams.set('indexing', this.indexing);
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                        credentials: 'same-origin',
                    });
                    if (current !== this.token || !res.ok) return;
                    this.$refs.results.innerHTML = await res.text();
                    window.history.replaceState({}, '', url);
                },
            }));
        });
    </script>
</x-company-layout>
