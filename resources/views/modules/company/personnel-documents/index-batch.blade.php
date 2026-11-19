<x-company-layout :title="'Indexar expediente · '.$employee->fullName()">
    <x-slot:header>
        <p class="truncate text-base font-semibold text-white">
            Indexar expediente
            <span class="font-normal text-slate-500"> · </span>{{ $employee->fullName() }}
            <span class="font-normal text-slate-500"> · </span><span class="font-normal text-slate-300">{{ $employee->document_type }} {{ $employee->document_number }}</span>
        </p>
        <p class="truncate mt-0.5 text-xs text-slate-400">
            {{ $batch->original_name }}
            <span> · </span><span id="pages-remaining">{{ $batch->page_count }} página{{ $batch->page_count === 1 ? '' : 's' }}</span>
        </p>
    </x-slot:header>

    <section class="index-workspace personnel-docs">
        <form method="post" action="{{ $storeUrl }}" id="index-form" class="index-side">
            @csrf
            <article class="index-criteria rounded-xl border border-slate-800 bg-slate-900/60 px-5 py-4">
                <div class="space-y-3">
                    <p class="text-sm text-slate-400" id="page-hint">Ninguna página seleccionada.</p>
                    @if ($errors->any())
                        <p class="text-sm text-rose-400">{{ $errors->first() }}</p>
                    @endif
                    <label class="block text-sm text-slate-300">Carpeta
                        <select id="slice-folder" class="mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 text-white px-3 text-sm"></select>
                    </label>
                    <label class="block text-sm text-slate-300" id="type-field">Tipo para la selección
                        <select id="slice-type" class="mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 text-white px-3 text-sm"></select>
                    </label>
                    <label class="block text-sm text-slate-300" id="tipo-field" hidden>Tipo
                        <input id="slice-tipo" maxlength="80" placeholder="Ej. RUT Cámara de Comercio" class="mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 text-white px-3 text-sm">
                    </label>
                    <label class="block text-sm text-slate-300">Nombre
                        <input id="slice-name" required class="mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 text-white px-3 text-sm">
                    </label>
                    <div id="course-fields" hidden class="space-y-3">
                        <label class="block text-sm text-slate-300">Fecha del curso
                            <input type="date" id="slice-taken-on" class="mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 text-white px-3 text-sm">
                        </label>
                        <label class="block text-sm text-slate-300">Entidad que dicta el curso
                            <input id="slice-provider" class="mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 text-white px-3 text-sm">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-700 px-4 text-sm text-slate-200 hover:bg-slate-800" type="button" id="add-slice">Agregar a la lista</button>
                        <button class="inline-flex h-9 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-500" type="submit">Guardar indexación</button>
                        <a class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-700 px-4 text-sm text-slate-200 hover:bg-slate-800" href="{{ route('company.personnel-documents.folder', $employee) }}">Cancelar</a>
                    </div>
                </div>
            </article>

            <section class="index-history rounded-xl border border-slate-800 bg-slate-900/60 px-5 py-4" aria-label="Documentos en la lista">
                <p class="text-xs uppercase tracking-wider text-slate-500">En la lista</p>
                <div id="slice-rows" class="index-slice-list"></div>
            </section>
        </form>

        <article class="index-pages rounded-xl border border-slate-800 bg-slate-900/60 px-5 py-4 min-h-0">
            <p class="text-xs uppercase tracking-wider text-slate-500 mb-3">Páginas por indexar</p>
            <div class="index-load" id="index-load" hidden>
                <div class="index-load-track" id="index-load-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Carga del PDF">
                    <div class="index-load-bar" id="index-load-bar"></div>
                </div>
                <p class="index-load-label" id="index-load-label">Cargando PDF…</p>
            </div>
            <div class="index-pages-scroll">
                <div class="page-thumbs" id="page-thumbs">
                    @for ($page = 1; $page <= $batch->page_count; $page++)
                        <div class="page-thumb" data-page="{{ $page }}">
                            <button type="button" class="page-thumb-hit" data-page="{{ $page }}" aria-pressed="false" title="Página {{ $page }}">
                                <canvas width="220" height="286" aria-hidden="true"></canvas>
                                <span class="page-thumb-fallback">{{ $page }}</span>
                            </button>
                            <div class="page-thumb-bar">
                                <span>{{ $page }}</span>
                                <button class="folder-link" type="button" data-preview="{{ $previewUrl }}#page={{ $page }}" data-name="Página {{ $page }} · {{ $batch->original_name }}">Ampliar</button>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </article>
    </section>

    <div class="preview-layer" id="preview-layer" hidden>
        <div class="preview-frame">
            <div class="preview-bar">
                <div class="preview-tools">
                    <button class="preview-burger" type="button" id="preview-pages" aria-pressed="false" aria-label="Páginas del documento">
                        <span></span><span></span><span></span>
                    </button>
                    <span id="preview-page-label">1</span>
                    <button class="folder-link" type="button" id="preview-zoom-out" aria-label="Reducir">−</button>
                    <button class="folder-link" type="button" id="preview-zoom-fit" aria-label="Ajustar al ancho">Ajustar</button>
                    <button class="folder-link" type="button" id="preview-zoom-in" aria-label="Aumentar">+</button>
                    <span id="preview-zoom-label">100%</span>
                </div>
                <span id="preview-title">Documento</span>
                <div class="flex gap-2">
                    <a class="folder-link" id="preview-download" href="#">Descargar</a>
                    <button class="folder-link" type="button" id="preview-close">Cerrar</button>
                </div>
            </div>
            <div class="preview-body">
                <aside class="preview-pages" id="preview-pages-panel" hidden>
                    <div id="preview-thumbs"></div>
                </aside>
                <div class="preview-stage" id="preview-stage">
                    <p class="preview-status" id="preview-status" hidden>Cargando la página…</p>
                    <canvas id="preview-canvas" hidden></canvas>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="history-meta">@json($historyMeta)</script>
</x-company-layout>
