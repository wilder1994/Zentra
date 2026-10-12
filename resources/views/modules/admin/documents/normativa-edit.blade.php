<x-admin-layout title="Editar normativa">
    <div class="flex flex-col flex-1 min-h-0 gap-3 max-w-3xl">
        <div class="flex items-center justify-between gap-3 shrink-0">
            <div>
                <p class="text-xs text-slate-500">Documentos · Normoteca · Versionar</p>
                <h2 class="text-sm font-semibold text-white mt-1">{{ $document->title }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Versión actual {{ $document->version }}
                    @if ($document->package_sku)
                        · {{ $document->planLabel() }}
                    @else
                        · documento global
                    @endif
                </p>
            </div>
            <x-ui.button variant="secondary" :href="route('admin.documents.normativa')" size="sm">← Normativa</x-ui.button>
        </div>

        <p class="text-xs text-amber-300/90 rounded-lg border border-amber-800/50 bg-amber-900/20 px-3 py-2 shrink-0">
            Al guardar se publica una <strong>nueva versión</strong> y se archiva la vigente. Los expedientes con aceptación previa conservan el texto congelado.
        </p>

        <form method="POST" action="{{ route('admin.documents.normativa.publish', $document) }}" class="flex-1 min-h-0 flex flex-col gap-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4">
            @csrf
            @method('PUT')
            <div>
                <x-ui.label for="title">Título</x-ui.label>
                <x-ui.input id="title" name="title" accent="platform" value="{{ old('title', $document->title) }}" required />
                <x-ui.field-error name="title" />
            </div>
            <div>
                <x-ui.label for="effective_from">Vigente desde</x-ui.label>
                <x-ui.input id="effective_from" name="effective_from" type="date" accent="platform" value="{{ old('effective_from', now()->toDateString()) }}" />
                <x-ui.field-error name="effective_from" />
            </div>
            <div class="flex-1 min-h-0 flex flex-col">
                <x-ui.label for="content">Contenido</x-ui.label>
                <textarea
                    id="content"
                    name="content"
                    rows="18"
                    required
                    class="mt-1 w-full flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 font-mono leading-relaxed focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >{{ old('content', $document->content) }}</textarea>
                <x-ui.field-error name="content" />
            </div>
            <div class="flex justify-end gap-2 shrink-0">
                <x-ui.button type="submit" variant="platform">Publicar nueva versión</x-ui.button>
            </div>
        </form>
    </div>
</x-admin-layout>
