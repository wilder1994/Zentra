<x-admin-layout title="Comercial">
    <p class="text-sm text-slate-400 mb-6">
        Documentos comerciales de Zentra (W Codex): propuesta PDF, cotización PDF y presentación PowerPoint con capturas reales.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-5xl">
        <a
            href="{{ route('admin.commercial.brochure.create') }}"
            class="group rounded-lg border border-slate-800 bg-slate-900/80 p-5 hover:border-violet-500/50 hover:bg-slate-900 transition"
        >
            <p class="text-[10px] uppercase tracking-wide text-violet-400 font-semibold">PDF</p>
            <h3 class="mt-1 text-lg font-semibold text-white group-hover:text-violet-200">Propuesta comercial</h3>
            <p class="mt-2 text-sm text-slate-400">
                Presentación con los cuatro planes (Bronce, Plata, Oro, Platino), textos editables y cierre del ejecutivo.
            </p>
            <span class="mt-4 inline-flex text-sm text-violet-400 group-hover:text-violet-300">Abrir formulario →</span>
        </a>

        <a
            href="{{ route('admin.commercial.quotes.create') }}"
            class="group rounded-lg border border-slate-800 bg-slate-900/80 p-5 hover:border-emerald-500/50 hover:bg-slate-900 transition"
        >
            <p class="text-[10px] uppercase tracking-wide text-emerald-400 font-semibold">PDF</p>
            <h3 class="mt-1 text-lg font-semibold text-white group-hover:text-emerald-200">Cotización</h3>
            <p class="mt-2 text-sm text-slate-400">
                Plan personalizado (cantidades + ciclo), precio de catálogo como referencia y total comercial editable.
            </p>
            <span class="mt-4 inline-flex text-sm text-emerald-400 group-hover:text-emerald-300">Abrir formulario →</span>
        </a>

        <a
            href="{{ route('admin.commercial.deck.create') }}"
            class="group rounded-lg border border-slate-800 bg-slate-900/80 p-5 hover:border-sky-500/50 hover:bg-slate-900 transition"
        >
            <p class="text-[10px] uppercase tracking-wide text-sky-400 font-semibold">PPTX</p>
            <h3 class="mt-1 text-lg font-semibold text-white group-hover:text-sky-200">Presentación comercial</h3>
            <p class="mt-2 text-sm text-slate-400">
                PowerPoint con capturas reales de Zentra, planes, por qué Zentra y próximos pasos. Textos editables.
            </p>
            <span class="mt-4 inline-flex text-sm text-sky-400 group-hover:text-sky-300">Abrir formulario →</span>
        </a>
    </div>
</x-admin-layout>
