<x-company-layout title="Revisar planilla">
    <x-slot:actions>
        <form method="POST" action="{{ route('company.personnel-documents.parafiscales.cancel') }}" id="parafiscal-cancel">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm">Cancelar</x-ui.button>
        </form>
        <form method="POST" action="{{ route('company.personnel-documents.parafiscales.commit') }}" id="parafiscal-commit" data-conflict="{{ ($conflicts ?? 0) > 0 ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="mode" value="">
            <x-ui.button type="submit" size="sm" id="parafiscal-commit-btn">Aceptar y cargar</x-ui.button>
        </form>
    </x-slot:actions>

    @php
        $matched = $preview['matched'] ?? [];
        $skipped = $preview['skipped'] ?? [];
        $pension = $preview['pension_period'] ?? null;
        $salud = $preview['salud_period'] ?? null;
    @endphp

    <div class="space-y-4">
        <p class="text-sm text-slate-400">Revisión temporal. Solo se guardará un xlsx recortado por colaborador encontrado. La planilla completa no se almacena. Las cédulas que no estén en Empleados se omiten y no bloquean la carga.</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">Periodo pensión</p>
                <p class="mt-1 text-lg font-semibold text-white">{{ $pension ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">Periodo salud</p>
                <p class="mt-1 text-lg font-semibold text-white">{{ $salud ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-emerald-900/50 bg-emerald-950/20 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-emerald-500">En empleados</p>
                <p class="mt-1 text-lg font-semibold text-emerald-300 tabular-nums">{{ count($matched) }}</p>
            </div>
            <div class="rounded-lg border border-amber-900/50 bg-amber-950/20 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-amber-500">Fuera (aviso)</p>
                <p class="mt-1 text-lg font-semibold text-amber-300 tabular-nums">{{ count($skipped) }}</p>
            </div>
        </div>

        @if (count($skipped) > 0)
            <p class="text-sm text-amber-200">{{ count($skipped) }} cédula(s) no están en Empleados. No se crea ficha. Puede aceptar igual.</p>
        @endif

        @if (count($matched) === 0)
            <p class="text-sm text-slate-300">Ninguna cédula coincide con Empleados. Puede aceptar: no se guardará ningún recorte.</p>
        @endif

        <div class="rounded-lg border border-slate-800 overflow-hidden">
            <p class="px-3 py-2 text-xs uppercase tracking-wide text-slate-500 bg-slate-900/80">Se recortará</p>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left">Cédula</th>
                        <th class="px-3 py-3 text-left">Empleado</th>
                        <th class="px-3 py-3 text-left">En planilla</th>
                        <th class="px-3 py-3 text-right">Filas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($matched as $row)
                        <tr class="hover:bg-slate-900/40">
                            <td class="px-3 py-3 text-slate-300">{{ $row['document'] }}</td>
                            <td class="px-3 py-3 text-white">{{ $row['employee_name'] ?? $row['name'] }}</td>
                            <td class="px-3 py-3 text-slate-400">{{ $row['name'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-right tabular-nums text-slate-300">{{ $row['rows_count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-slate-500">Nadie de esta planilla está en Empleados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (count($skipped) > 0)
            <div class="rounded-lg border border-amber-900/40 overflow-hidden">
                <p class="px-3 py-2 text-xs uppercase tracking-wide text-amber-500/80 bg-amber-950/20">No están en Empleados</p>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-3 text-left">Cédula</th>
                            <th class="px-3 py-3 text-left">Nombre en planilla</th>
                            <th class="px-3 py-3 text-right">Filas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($skipped as $row)
                            <tr class="hover:bg-slate-900/40">
                                <td class="px-3 py-3 text-slate-300">{{ $row['document'] }}</td>
                                <td class="px-3 py-3 text-white">{{ $row['name'] ?: '—' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-slate-300">{{ $row['rows_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div id="parafiscal-choice" class="load-block" hidden>
        <div class="load-block-card">
            <p class="text-sm font-semibold text-white">Ya hay documentos con estas fechas.</p>
            <p class="mt-1 text-xs text-slate-400">¿Desea reemplazarlos o crear un archivo nuevo? Reemplazar borra solo este periodo. Un archivo nuevo se guarda al lado, como v2, v3.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-700 px-4 text-sm font-medium text-slate-200 hover:bg-slate-800" id="parafiscal-replace">Reemplazar</button>
                <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-500" id="parafiscal-version">Crear archivo nuevo</button>
                <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg px-3 text-sm text-slate-400 hover:text-white" id="parafiscal-choice-close">Volver</button>
            </div>
        </div>
    </div>

    <div id="parafiscal-load" class="load-block" hidden>
        <div class="load-block-card">
            <p class="text-sm font-semibold text-white" id="parafiscal-load-title">Procesando planilla</p>
            <p class="mt-1 text-xs text-slate-400" id="parafiscal-load-msg">Preparando la plantilla original…</p>
            <div class="load-block-bar"><span id="parafiscal-load-fill"></span></div>
            <p class="mt-2 text-lg font-semibold tabular-nums text-indigo-300" id="parafiscal-load-pct">0%</p>
        </div>
    </div>
    <script>
        (function () {
            const form = document.getElementById('parafiscal-commit');
            if (! form) return;
            const overlay = document.getElementById('parafiscal-load');
            const fill = document.getElementById('parafiscal-load-fill');
            const pct = document.getElementById('parafiscal-load-pct');
            const msg = document.getElementById('parafiscal-load-msg');
            const btn = document.getElementById('parafiscal-commit-btn');
            const cancelBtn = document.querySelector('#parafiscal-cancel button');
            const token = form.querySelector('[name="_token"]').value;
            const tickUrl = @json(route('company.personnel-documents.parafiscales.tick'));
            const indexUrl = @json(route('company.personnel-documents.index'));

            function show(percent, text) {
                overlay.hidden = false;
                fill.style.width = percent + '%';
                pct.textContent = percent + '%';
                if (text) msg.textContent = text;
            }

            const choice = document.getElementById('parafiscal-choice');
            const mode = form.querySelector('[name="mode"]');
            const choose = function (value) {
                if (mode) mode.value = value;
                if (choice) choice.hidden = true;
                form.requestSubmit();
            };
            document.getElementById('parafiscal-replace')?.addEventListener('click', function () { choose('replace'); });
            document.getElementById('parafiscal-version')?.addEventListener('click', function () { choose('version'); });
            document.getElementById('parafiscal-choice-close')?.addEventListener('click', function () {
                if (choice) choice.hidden = true;
            });

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (form.dataset.conflict === '1' && mode && mode.value === '') {
                    if (choice) choice.hidden = false;
                    return;
                }
                if (btn) btn.disabled = true;
                if (cancelBtn) cancelBtn.disabled = true;
                show(0, 'Preparando la plantilla original…');
                try {
                    const started = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: new FormData(form),
                    });
                    const startJson = await started.json();
                    if (! started.ok) {
                        throw new Error(startJson.message || 'No se pudo iniciar la carga.');
                    }
                    show(1, startJson.message || 'Guardando recortes…');
                    let done = false;
                    while (! done) {
                        const tick = await fetch(tickUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({ limit: 5 }),
                        });
                        const body = await tick.json();
                        if (! tick.ok) {
                            throw new Error(body.message || 'Falló un lote del recorte.');
                        }
                        show(body.percent || 0, body.current + ' / ' + body.total + ' · ' + (body.message || ''));
                        done = !! body.done;
                    }
                    window.location.href = indexUrl;
                } catch (error) {
                    overlay.hidden = true;
                    if (btn) btn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                    alert(error.message || 'No se pudo cargar la planilla.');
                }
            });
        })();
    </script>
</x-company-layout>
