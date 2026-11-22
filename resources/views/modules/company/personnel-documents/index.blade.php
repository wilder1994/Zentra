<x-company-layout title="Documentos">
    <x-slot:header>
        <form method="get" action="{{ route('company.personnel-documents.index') }}" class="flex min-w-0 flex-wrap items-center gap-2" @submit.prevent="$store.documentBrowser.run()">
            <h2 class="shrink-0 text-sm font-normal text-white">Documentos</h2>
            <div class="min-w-48 flex-1">
                <x-ui.input name="q" x-model="$store.documentBrowser.q" placeholder="Nombre o cédula" autocomplete="off" @input="$store.documentBrowser.schedule()" />
            </div>
            <div class="relative">
                <button type="button" class="inline-flex h-9 items-center rounded-lg border border-slate-700 px-3 text-xs font-normal text-slate-300 hover:bg-slate-800" @click="$store.documentBrowser.open = !$store.documentBrowser.open" :class="$store.documentBrowser.tenure || $store.documentBrowser.indexing ? 'border-indigo-500 text-slate-100' : ''">
                    Filtros
                </button>
                <div x-show="$store.documentBrowser.open" x-cloak @click.outside="$store.documentBrowser.open = false" class="absolute left-0 z-20 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 p-3 shadow-xl">
                    <label class="block text-xs font-normal text-slate-400">
                        Antigüedad
                        <select x-model="$store.documentBrowser.tenure" @change="$store.documentBrowser.run()" class="mt-1 h-9 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 text-sm font-normal text-white">
                            <option value="">Todas</option>
                            <option value="lt1">Menos de 1 año</option>
                            <option value="y1_3">De 1 a 3 años</option>
                            <option value="y3_5">De 3 a 5 años</option>
                            <option value="gt5">Más de 5 años</option>
                            <option value="none">Sin fecha de ingreso</option>
                        </select>
                    </label>
                    <label class="mt-3 block text-xs font-normal text-slate-400">
                        Indexación
                        <select x-model="$store.documentBrowser.indexing" @change="$store.documentBrowser.run()" class="mt-1 h-9 w-full rounded-lg border border-slate-700 bg-slate-950 px-2 text-sm font-normal text-white">
                            <option value="">Todas</option>
                            <option value="on">Activo</option>
                            <option value="off">Desactivado</option>
                        </select>
                    </label>
                </div>
            </div>
            @if ($canUpload ?? false)
                <x-ui.button type="button" size="sm" onclick="window.dispatchEvent(new CustomEvent('open-parafiscal-import'))">Carga masiva planilla</x-ui.button>
            @endif
        </form>
    </x-slot:header>

    @php
        $tones = [
            'hv' => 'border-indigo-900/80 bg-indigo-950/25',
            'contratacion' => 'border-sky-900/80 bg-sky-950/25',
            'certificados' => 'border-emerald-900/80 bg-emerald-950/20',
            'cursos' => 'border-amber-900/80 bg-amber-950/20',
            'afiliaciones' => 'border-cyan-900/80 bg-cyan-950/20',
            'parafiscales' => 'border-violet-900/80 bg-violet-950/25',
            'otros' => 'border-slate-700 bg-slate-900/70',
            'all' => 'border-slate-600 bg-slate-900',
        ];
    @endphp

    <div class="flex flex-wrap gap-2">
        @foreach ($folderCards as $card)
            <button
                type="button"
                class="min-w-28 flex-1 rounded-lg border px-2.5 py-2 text-left font-normal transition {{ $tones[$card['key']] ?? 'border-slate-700 bg-slate-900' }}"
                :class="$store.documentBrowser.folder === '{{ $card['key'] }}' ? 'ring-1 ring-indigo-400/80' : ''"
                @click="$store.documentBrowser.pick('{{ $card['key'] }}')"
            >
                <p class="truncate text-[11px] leading-4 text-slate-400">{{ $card['name'] }}</p>
                <p class="mt-0.5 text-xs leading-4 text-slate-100">{{ $card['line'] }}</p>
                <p class="text-[11px] leading-4 text-slate-500">{{ $card['weight'] }}</p>
            </button>
        @endforeach
    </div>

    <div id="document-results" class="mt-4">
        @include('modules.company.personnel-documents.partials.results')
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('documentBrowser', {
                q: @js($q),
                tenure: @js($tenure),
                indexing: @js($indexing),
                folder: @js($folder),
                open: false,
                token: 0,
                timer: null,
                schedule() {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.run(), 250);
                },
                pick(key) {
                    this.folder = this.folder === key ? '' : key;
                    this.run();
                },
                async run() {
                    const current = ++this.token;
                    const url = new URL(@js(route('company.personnel-documents.index')), window.location.origin);
                    const term = this.q.trim();
                    if (term !== '') url.searchParams.set('q', term);
                    if (this.tenure) url.searchParams.set('tenure', this.tenure);
                    if (this.indexing) url.searchParams.set('indexing', this.indexing);
                    if (this.folder) url.searchParams.set('folder', this.folder);
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                        credentials: 'same-origin',
                    });
                    if (current !== this.token || !res.ok) return;
                    const target = document.getElementById('document-results');
                    if (target) target.innerHTML = await res.text();
                    window.history.replaceState({}, '', url);
                },
            });
        });
    </script>
</x-company-layout>
