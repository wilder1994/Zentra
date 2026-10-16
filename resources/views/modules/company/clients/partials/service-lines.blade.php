@php
    $accessOn = (bool) old('has_access', $accessDefault ?? false);
    $proOn = (bool) old('has_supervision', $proDefault ?? false);
    $accessRemaining = (int) ($metrics['access_remaining'] ?? 0);
    $proRemaining = (int) ($metrics['supervision_remaining'] ?? 0);
    $accessMax = (int) ($metrics['max_clients'] ?? 0);
    $proMax = (int) ($metrics['max_supervision_clients'] ?? 0);
    $unlimited = (bool) ($metrics['supervision_unlimited'] ?? false);
    $supervisionCap = $unlimited ? 'Ilimitada' : $proRemaining.'/'.$proMax;
    $supervisionDisabled = (! $unlimited && ($proMax < 1 || ($proRemaining < 1 && ! $proOn)));
    $foldersOn = (bool) old('show_personnel_folders', $foldersDefault ?? false);
    $obsOn = (bool) old('has_observatory', $observatoryDefault ?? false);
    $obsRemaining = (int) ($metrics['observatory_remaining'] ?? 0);
    $obsMax = (int) ($metrics['max_observatory'] ?? 0);
    $obsDisabled = $obsMax < 1 || ($obsRemaining < 1 && ! $obsOn);
    $impact = $lineImpact ?? ['installations' => 0, 'doors' => 0, 'posts' => 0, 'events' => 0];
    $retentionDays = (int) config('retention.service_line_retention_days', 365);
@endphp

<div
    id="service-lines"
    data-impact='@json($impact)'
    data-retention-days="{{ $retentionDays }}"
    data-initial='@json(['access' => $accessOn, 'supervision' => $proOn, 'observatory' => $obsOn])'
>
    <p class="text-sm font-medium text-white">Líneas de servicio</p>
    <p class="text-xs text-slate-500 mt-0.5">Tu plan cuenta <strong>instalaciones activas</strong>: {{ $metrics['installations_remaining'] ?? 0 }} libres de {{ $metrics['installation_capacity'] ?? 0 }}. Cada línea activa en el cliente usa un cupo de esa línea por cada instalación.</p>
    @unless ($errorsListedAbove ?? false)
        <x-ui.field-error name="package" />
        <x-ui.field-error :messages="$errors->get('has_access')" />
        <x-ui.field-error :messages="$errors->get('has_supervision')" />
        <x-ui.field-error :messages="$errors->get('has_observatory')" />
    @endunless

    <input type="hidden" name="line_deactivate_mode_access" id="line_deactivate_mode_access" value="{{ old('line_deactivate_mode_access', '') }}">
    <input type="hidden" name="line_deactivate_mode_supervision" id="line_deactivate_mode_supervision" value="{{ old('line_deactivate_mode_supervision', '') }}">
    <input type="hidden" name="line_deactivate_mode_observatory" id="line_deactivate_mode_observatory" value="{{ old('line_deactivate_mode_observatory', '') }}">

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <label class="rounded-xl border px-4 py-3 cursor-pointer {{ $accessOn ? 'border-indigo-500 bg-indigo-950/30' : 'border-slate-800 bg-slate-950/40' }}">
            <input type="hidden" name="has_access" value="0">
            <input type="checkbox" name="has_access" value="1" data-line="access" class="js-service-line rounded border-slate-600 text-indigo-600" @checked($accessOn) @disabled(($accessMax < 1 || ($accessRemaining < 1 && ! $accessOn)))>
            <span class="ml-2 text-sm font-semibold text-white">Accesos</span>
            <p class="mt-1 text-[11px] text-slate-400 leading-relaxed">Portería, censo y puntos de acceso. Cupo {{ $accessRemaining }}/{{ $accessMax }}.</p>
        </label>
        <label class="rounded-xl border px-4 py-3 cursor-pointer {{ $proOn ? 'border-amber-500 bg-amber-950/20' : 'border-slate-800 bg-slate-950/40' }}">
            <input type="hidden" name="has_supervision" value="0">
            <input type="checkbox" name="has_supervision" value="1" data-line="supervision" class="js-service-line rounded border-slate-600 text-amber-500" @checked($proOn) @disabled($supervisionDisabled)>
            <span class="ml-2 text-sm font-semibold text-white">Supervisión</span>
            <p class="mt-1 text-[11px] text-slate-400 leading-relaxed">App, GPS y revista en puestos de Supervisión. Cupo {{ $supervisionCap }}.</p>
        </label>
        <label class="rounded-xl border px-4 py-3 cursor-pointer {{ $obsOn ? 'border-sky-500 bg-sky-950/20' : 'border-slate-800 bg-slate-950/40' }}">
            <input type="hidden" name="has_observatory" value="0">
            <input type="checkbox" name="has_observatory" value="1" data-line="observatory" class="js-service-line rounded border-slate-600 text-sky-500" @checked($obsOn) @disabled($obsDisabled)>
            <span class="ml-2 text-sm font-semibold text-white">Observatorio</span>
            <p class="mt-1 text-[11px] text-slate-400 leading-relaxed">Tablero y reportes del cliente. Cupo {{ $obsRemaining }}/{{ $obsMax }}.</p>
        </label>
    </div>

    <label id="personnel-folders-flag" class="mt-3 flex items-start gap-3 rounded-xl border px-4 py-3 {{ $accessOn ? '' : 'hidden' }} {{ $foldersOn ? 'border-indigo-500 bg-indigo-950/30' : 'border-slate-800 bg-slate-950/40' }}">
        <input type="hidden" name="show_personnel_folders" value="0">
        <input type="checkbox" name="show_personnel_folders" value="1" class="mt-0.5 rounded border-slate-600 text-indigo-600" @checked($foldersOn && $accessOn)>
        <span>
            <span class="text-sm font-semibold text-white">Mostrar indexación de carpetas</span>
            <p class="mt-1 text-[11px] text-slate-400 leading-relaxed">En Operar cliente, el cliente ve las carpetas de empleados asignados a un puesto. Solo lectura. Requiere Accesos.</p>
        </span>
    </label>

    <div id="service-line-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true" aria-labelledby="service-line-modal-title">
        <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl">
            <h3 id="service-line-modal-title" class="text-base font-semibold text-white">Desactivar línea</h3>
            <p id="service-line-modal-body" class="mt-2 text-sm text-slate-300 leading-relaxed"></p>
            <div class="mt-4 space-y-2">
                <button type="button" data-mode="temporary" class="js-line-mode w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2.5 text-left text-sm text-white hover:border-indigo-500">
                    <span class="font-semibold">Desactivar temporalmente</span>
                    <span class="mt-0.5 block text-[11px] text-slate-400">Libera cupo. Los datos se conservan. Para volver a operar y consultar necesitas cupo libre.</span>
                </button>
                <button type="button" data-mode="archive" class="js-line-mode w-full rounded-lg border border-amber-700/60 bg-amber-950/30 px-3 py-2.5 text-left text-sm text-white hover:border-amber-500">
                    <span class="font-semibold">Archivar información operativa</span>
                    <span class="mt-0.5 block text-[11px] text-amber-200/80">Archiva la operación de esta línea. Entra en retención ({{ $retentionDays }} días) y luego puede purgarse. No se borra al instante.</span>
                </button>
            </div>
            <button type="button" id="service-line-modal-cancel" class="mt-4 w-full rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancelar</button>
        </div>
    </div>

    <script>
        (() => {
            const root = document.getElementById('service-lines');
            if (!root) return;
            const impact = JSON.parse(root.dataset.impact || '{}');
            const initial = JSON.parse(root.dataset.initial || '{}');
            const retention = root.dataset.retentionDays || '365';
            const labels = { access: 'Accesos', supervision: 'Supervisión', observatory: 'Observatorio' };
            const modal = document.getElementById('service-line-modal');
            const body = document.getElementById('service-line-modal-body');
            let pending = null;

            const access = document.querySelector('input[name="has_access"][type="checkbox"]');
            const wrap = document.getElementById('personnel-folders-flag');
            if (access && wrap) {
                const sync = () => wrap.classList.toggle('hidden', !access.checked);
                access.addEventListener('change', sync);
                sync();
            }

            const modeInput = (line) => document.getElementById('line_deactivate_mode_' + line);

            const impactText = (line) => {
                const bits = [];
                if (line === 'access' && (impact.doors || 0) > 0) bits.push(impact.doors + ' puerta(s)');
                if (line === 'supervision' && (impact.posts || 0) > 0) bits.push(impact.posts + ' puesto(s)');
                if (line === 'observatory' && (impact.events || 0) > 0) bits.push(impact.events + ' folio(s)');
                if ((impact.installations || 0) > 0) bits.push(impact.installations + ' instalación(es) dejan de contar en el cupo de ' + labels[line]);
                return bits.length ? bits.join('; ') + '.' : 'No hay operación registrada; solo se libera el cupo.';
            };

            const openModal = (checkbox, line) => {
                pending = { checkbox, line };
                body.textContent = 'Vas a desactivar ' + labels[line] + ' en este cliente. ' + impactText(line)
                    + ' Elige si es temporal o si archivas la información operativa (retención ' + retention + ' días).';
                modal.classList.remove('hidden');
            };

            const closeModal = (restore) => {
                if (restore && pending) {
                    pending.checkbox.checked = true;
                    const input = modeInput(pending.line);
                    if (input) input.value = '';
                }
                pending = null;
                modal.classList.add('hidden');
            };

            root.querySelectorAll('.js-service-line').forEach((box) => {
                box.addEventListener('change', () => {
                    const line = box.dataset.line;
                    if (!line) return;
                    if (box.checked) {
                        const input = modeInput(line);
                        if (input) input.value = '';
                        return;
                    }
                    if (!initial[line]) {
                        return;
                    }
                    openModal(box, line);
                });
            });

            modal.querySelectorAll('.js-line-mode').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!pending) return;
                    const input = modeInput(pending.line);
                    if (input) input.value = btn.dataset.mode || 'temporary';
                    pending = null;
                    modal.classList.add('hidden');
                });
            });

            document.getElementById('service-line-modal-cancel')?.addEventListener('click', () => closeModal(true));

            const form = root.closest('form');
            form?.addEventListener('submit', (e) => {
                for (const line of ['access', 'supervision', 'observatory']) {
                    const box = root.querySelector('.js-service-line[data-line="' + line + '"]');
                    if (!box || !initial[line] || box.checked) continue;
                    const mode = (modeInput(line)?.value || '').trim();
                    if (!mode) {
                        e.preventDefault();
                        openModal(box, line);
                        return;
                    }
                }
            });
        })();
    </script>
</div>
