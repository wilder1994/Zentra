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
        .obs-pptx {
            position: relative;
            overflow: visible;
        }
        .obs-pptx-ring {
            display: none;
            pointer-events: none;
        }
        .obs-pptx[data-busy="1"] {
            pointer-events: none;
            border-color: transparent !important;
            background-color: rgb(15 23 42);
        }
        .obs-pptx[data-busy="1"] .obs-pptx-ring {
            display: block;
            position: absolute;
            inset: -2px;
            border-radius: 10px;
            z-index: 0;
            background: conic-gradient(from 0deg, transparent 0 62%, #b45309 74%, #f59e0b 82%, #fde68a 90%, #99f6e4 97%, transparent 100%);
            animation: obs-pptx-spin 0.7s linear infinite;
        }
        .obs-pptx[data-busy="1"] .obs-pptx-ring::after {
            content: '';
            position: absolute;
            inset: 2px;
            border-radius: 8px;
            background-color: rgb(15 23 42);
        }
        .obs-pptx[data-busy="1"] [data-pptx-label] {
            position: relative;
            z-index: 1;
        }
        @keyframes obs-pptx-spin { to { transform: rotate(360deg); } }
    </style>
    <script>
        document.querySelectorAll('[data-pptx]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
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
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    const type = start.headers.get('Content-Type') || '';
                    if (!start.ok || !type.includes('application/json')) {
                        throw new Error('start');
                    }
                    const job = await start.json();
                    if (!job.status_url || !job.file_url) {
                        throw new Error('payload');
                    }
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
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    throw new Error('status');
                }
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
            const match = header.match(/filename\*?=(?:UTF-8''|")?([^\";]+)"?/i);
            return match ? decodeURIComponent(match[1]) : 'Informe_Observatorio.pptx';
        }
    </script>
@endonce
