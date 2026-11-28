@php
    /** @var \App\Support\Observatory\ObservatoryFilters $filters */
    $vista = $vista ?? 'tablero';
    $exportUrl = ($exportRoute ?? null) ? $exportRoute.'?'.http_build_query($filters->query()) : null;
@endphp
<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-2" id="observatory-filter">
    <input type="hidden" name="vista" value="{{ $vista }}">
    @include('modules.company.supervision.partials.period-picker', ['from' => $filters->from, 'to' => $filters->to])
    @include('modules.observatory.partials.filters-panel', [
        'filters' => $filters,
        'filterOptions' => $filterOptions,
        'vista' => $vista,
        'isCompany' => $isCompany ?? false,
    ])
    @if ($vista === 'tablero')
        @include('modules.observatory.partials.share-modal')
        @if ($exportUrl)
            <a href="{{ $exportUrl }}" data-pptx class="obs-pptx inline-flex items-center h-9 px-3 text-sm rounded-lg border border-amber-500/40 text-amber-200 hover:bg-amber-500/10">
                <span class="obs-pptx-ring" aria-hidden="true"></span>
                <span data-pptx-label>Descargar PPTX</span>
            </a>
        @endif
    @endif
</form>
@once
    <style>
        .obs-pptx { position: relative; }
        .obs-pptx-ring { display: none; pointer-events: none; }
        .obs-pptx[data-busy="1"] { pointer-events: none; }
        .obs-pptx[data-busy="1"] .obs-pptx-ring {
            display: block;
            position: absolute;
            inset: -3px;
            border-radius: 11px;
            padding: 2px;
            background: conic-gradient(from 0deg, transparent 0 72%, #0f766e 88%, #99f6e4 100%);
            animation: obs-pptx-spin 0.8s linear infinite;
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
        }
        @keyframes obs-pptx-spin { to { transform: rotate(360deg); } }
    </style>
    <script>
        document.querySelectorAll('[data-pptx]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                if (button.dataset.busy === '1') {
                    return;
                }
                const label = button.querySelector('[data-pptx-label]');
                const idle = label.textContent;
                button.dataset.busy = '1';
                button.setAttribute('aria-busy', 'true');
                label.textContent = 'Descargando';
                try {
                    const start = await fetch(button.href, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!start.ok) {
                        throw new Error('start');
                    }
                    const job = await start.json();
                    const file = await waitForPptx(job);
                    if (!file.ok) {
                        throw new Error('file');
                    }
                    const blob = await file.blob();
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = pptxName(file);
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                } catch (error) {
                    label.textContent = 'No se pudo descargar';
                    await new Promise((resolve) => setTimeout(resolve, 1600));
                } finally {
                    label.textContent = idle;
                    button.dataset.busy = '0';
                    button.removeAttribute('aria-busy');
                }
            });
        });

        async function waitForPptx(job) {
            if (job.status === 'ready') {
                return fetch(job.file_url, { credentials: 'same-origin' });
            }
            for (let attempt = 0; attempt < 40; attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 1500));
                const res = await fetch(job.status_url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.status === 'ready') {
                    return fetch(job.file_url, { credentials: 'same-origin' });
                }
                if (data.status === 'failed') {
                    throw new Error(data.message || 'failed');
                }
            }
            throw new Error('timeout');
        }

        function pptxName(response) {
            const header = response.headers.get('Content-Disposition') || '';
            const match = header.match(/filename="?([^"]+)"?/);
            return match ? match[1] : 'Informe_Observatorio.pptx';
        }
    </script>
@endonce
