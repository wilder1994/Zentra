@php
    $canUpload = $canUpload ?? false;
    $canDownload = $canDownload ?? false;
    $cargar = $cargar ?? false;
    $indexRoute = $indexRoute ?? route('company.personnel-documents.index');
    $previewRoute = $previewRoute ?? 'company.personnel-documents.preview';
    $downloadRoute = $downloadRoute ?? 'company.personnel-documents.download';
    $destroyRoute = $destroyRoute ?? 'company.personnel-documents.destroy';
    $batchCreateRoute = $batchCreateRoute ?? route('company.personnel-documents.batch.create', $employee);
@endphp

<div class="space-y-6 personnel-docs">
    <article class="rounded-xl border border-slate-800 bg-slate-900/60 px-5 py-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-500">Carpeta del empleado</p>
            <h2 class="text-2xl font-semibold text-white">{{ $employee->fullName() }}</h2>
            <p class="text-sm text-slate-400">{{ $employee->document_type }} {{ $employee->document_number }}</p>
        </div>
        <div class="flex gap-2">
            <x-ui.button variant="secondary" size="sm" :href="$indexRoute">Volver</x-ui.button>
            @if ($canUpload)
                <button class="inline-flex h-9 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-500" type="button" data-open-upload>Cargar documentos</button>
            @endif
        </div>
    </article>

    <section>
        <p class="text-xs uppercase tracking-wider text-slate-500 mb-3">Carpetas</p>
        <div class="folder-grid">
            @foreach ($indexedChecklists as $block)
                <article class="folder-card">
                    <div class="folder-card-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24">
                            <path fill="#818cf8" d="M3 7.25A1.75 1.75 0 0 1 4.75 5.5H9l1.7 1.7h8.55A1.75 1.75 0 0 1 21 8.95v9.3A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25v-11Z"/>
                            <path fill="#a5b4fc" d="M3 9.5h18v8.75A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25V9.5Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-medium text-white">{{ $block['label'] ?? $block['folder']->label() }}</p>
                        <p class="text-xs text-slate-400">{{ \App\Support\Personnel\FolderChecklist::countLabel($block['summary']) }}</p>
                    </div>
                    <button class="folder-link" type="button" data-open-folder="{{ $block['panel'] }}">Abrir</button>
                </article>
            @endforeach
        </div>
    </section>
</div>

<div class="preview-layer" id="folder-layer" hidden>
    @foreach ($indexedChecklists as $block)
        @php
            $isCourse = $block['folder'] === \App\Enums\DocumentFolder::Cursos;
            $isOther = $block['folder'] === \App\Enums\DocumentFolder::Otros;
            $isParafiscal = $block['folder'] === \App\Enums\DocumentFolder::Parafiscales;
            $files = array_values(array_filter($block['rows'], fn ($row) => $row['status'] === 'loaded' && $row['document']));
            $pending = array_values(array_filter(
                $block['rows'],
                fn ($row) => $row['status'] !== 'loaded' && ! (method_exists($row['type'], 'isRepeatable') && $row['type']->isRepeatable()),
            ));
        @endphp
        <div class="folder-frame" data-folder-pane="{{ $block['panel'] }}" hidden>
            <div class="preview-bar">
                <span class="folder-modal-title">
                    {{ $block['label'] ?? $block['folder']->label() }}
                    <span class="text-slate-400 font-normal"> · {{ \App\Support\Personnel\FolderChecklist::countLabel($block['summary']) }}</span>
                </span>
                <button class="folder-link" type="button" data-close-folder>Cerrar</button>
            </div>
            <div class="folder-modal-body">
                <input type="search" class="folder-search" placeholder="Buscar documento" data-folder-search>
                <div class="folder-doc-list">
                    @forelse ($files as $row)
                        @php $doc = $row['document']; @endphp
                        <div class="folder-doc-row" data-doc-search="{{ mb_strtolower($doc->label().' '.$row['type']->label()) }}">
                            <span class="drop-icon {{ $isParafiscal ? 'drop-icon-xlsx' : 'drop-icon-pdf' }}" aria-hidden="true">{{ $isParafiscal ? 'XLS' : 'PDF' }}</span>
                            <div class="min-w-0">
                                <p class="text-white">{{ $doc->label() }}</p>
                                @if ($isCourse)
                                    <p class="text-xs text-slate-400">{{ $doc->provider ?: '—' }} · {{ $doc->taken_on?->format('d/m/Y') ?: '—' }}</p>
                                @elseif ($isParafiscal)
                                    <p class="text-xs text-slate-400">{{ $doc->taken_on?->format('m/Y') ?: '—' }}</p>
                                @endif
                            </div>
                            <div class="folder-doc-actions">
                                <button class="folder-link" type="button" data-preview="{{ route($previewRoute, $doc) }}" data-name="{{ $doc->label() }}">Ver</button>
                                @if ($canDownload)
                                    <a class="folder-link" href="{{ route($downloadRoute, $doc) }}">Descargar</a>
                                @endif
                                @if ($canUpload && $doc->canDelete())
                                    <form method="post" action="{{ route($destroyRoute, $doc) }}" onsubmit="return confirm({{ $isParafiscal ? '\'¿Eliminar este archivo? Solo puede hacerlo durante 12 horas.\'' : '\'¿Eliminar este PDF? Solo puede hacerlo durante 12 horas.\'' }});">
                                        @csrf
                                        @method('DELETE')
                                        <button class="folder-link is-danger" type="submit">Eliminar</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400" data-empty-files>{{ $isParafiscal ? 'No hay planillas en esta carpeta.' : 'No hay PDF en esta carpeta.' }}</p>
                    @endforelse
                </div>
                @if ($canUpload && ! $isOther && ! $isParafiscal && count($pending))
                    <p class="text-xs uppercase tracking-wider text-slate-500 mt-4 mb-2">Pendientes</p>
                    @foreach ($pending as $row)
                        <div class="folder-doc-row is-pending" data-doc-search="{{ mb_strtolower($row['type']->label()) }}">
                            <div>
                                <p class="text-white">{{ $row['type']->label() }}</p>
                                <p class="text-xs text-slate-400">{{ $row['status'] === 'na' ? 'N/A' : 'Falta' }} · {{ $row['type']->requirement()->label() }}</p>
                            </div>
                            @if ($row['status'] !== 'na' && $block['na'])
                                <form method="post" action="{{ route($block['na'], ['employee' => $employee, 'folder' => $block['folder']->value]) }}">
                                    @csrf
                                    <input type="hidden" name="document_type" value="{{ $row['type']->value }}">
                                    <button class="folder-link" type="submit">No aplica</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endforeach
</div>

@if ($canUpload)
<div class="preview-layer" id="upload-layer" hidden @if ($cargar) data-open @endif>
    <div class="upload-frame">
        <div class="preview-bar">
            <span>Cargar documentos</span>
            <button class="folder-link" type="button" data-close-upload>Cerrar</button>
        </div>
        <div class="upload-body">
            <p class="text-sm text-slate-400 mb-3">Un PDF por lote. En el indexador elige carpeta y tipo por grupo de páginas.</p>
            <form class="drop-card" method="post" action="{{ $batchCreateRoute }}" enctype="multipart/form-data" data-dropzone data-accept=".pdf,application/pdf" data-max="51200">
                @csrf
                <p class="drop-card-title">Indexar lote</p>
                <p class="text-xs text-slate-400">Arrastre, pegue o seleccione un PDF.</p>
                <input class="drop-input" type="file" name="file" accept=".pdf" required>
                <div class="drop-empty">
                    <span class="drop-icon drop-icon-pdf" aria-hidden="true">PDF</span>
                    <p>Arrastre, pegue o seleccione un PDF</p>
                </div>
                <div class="drop-ready" hidden>
                    <div class="drop-file">
                        <span class="drop-icon drop-icon-pdf" data-file-icon aria-hidden="true">PDF</span>
                        <div>
                            <p data-file-name></p>
                            <p class="text-xs text-slate-400" data-file-size></p>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-rose-400 drop-error" hidden></p>
                <div class="drop-actions">
                    <button class="folder-link" type="button" data-drop-clear hidden>Quitar</button>
                    <button class="inline-flex h-9 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white disabled:opacity-40" type="submit" disabled>Indexar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

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
                @if ($canDownload)
                    <a class="folder-link" id="preview-download" href="#">Descargar</a>
                @endif
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
