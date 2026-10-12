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
@endphp

<div>
    <p class="text-sm font-medium text-white">Líneas de servicio</p>
    <p class="text-xs text-slate-500 mt-0.5">Tu plan cuenta <strong>instalaciones activas</strong>: {{ $metrics['installations_remaining'] ?? 0 }} libres de {{ $metrics['installation_capacity'] ?? 0 }}. Cada línea activa en el cliente usa un cupo de esa línea por cada instalación.</p>
    @unless ($errorsListedAbove ?? false)
        <x-ui.field-error name="package" />
        <x-ui.field-error :messages="$errors->get('has_access')" />
        <x-ui.field-error :messages="$errors->get('has_supervision')" />
        <x-ui.field-error :messages="$errors->get('has_observatory')" />
    @endunless

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <label class="rounded-xl border px-4 py-3 cursor-pointer {{ $accessOn ? 'border-indigo-500 bg-indigo-950/30' : 'border-slate-800 bg-slate-950/40' }}">
            <input type="hidden" name="has_access" value="0">
            <input type="checkbox" name="has_access" value="1" class="rounded border-slate-600 text-indigo-600" @checked($accessOn) @disabled(($accessMax < 1 || ($accessRemaining < 1 && ! $accessOn)))>
            <span class="ml-2 text-sm font-semibold text-white">Accesos</span>
            <p class="mt-1 text-[11px] text-slate-400 leading-relaxed">Portería, censo y puntos de acceso. Cupo {{ $accessRemaining }}/{{ $accessMax }}.</p>
        </label>
        <label class="rounded-xl border px-4 py-3 cursor-pointer {{ $proOn ? 'border-amber-500 bg-amber-950/20' : 'border-slate-800 bg-slate-950/40' }}">
            <input type="hidden" name="has_supervision" value="0">
            <input type="checkbox" name="has_supervision" value="1" class="rounded border-slate-600 text-amber-500" @checked($proOn) @disabled($supervisionDisabled)>
            <span class="ml-2 text-sm font-semibold text-white">Supervisión</span>
            <p class="mt-1 text-[11px] text-slate-400 leading-relaxed">App, GPS y revista en puestos de Supervisión. Cupo {{ $supervisionCap }}.</p>
        </label>
        <label class="rounded-xl border px-4 py-3 cursor-pointer {{ $obsOn ? 'border-sky-500 bg-sky-950/20' : 'border-slate-800 bg-slate-950/40' }}">
            <input type="hidden" name="has_observatory" value="0">
            <input type="checkbox" name="has_observatory" value="1" class="rounded border-slate-600 text-sky-500" @checked($obsOn) @disabled($obsDisabled)>
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
    <script>
        (() => {
            const access = document.querySelector('input[name="has_access"][type="checkbox"]');
            const wrap = document.getElementById('personnel-folders-flag');
            if (!access || !wrap) return;
            const sync = () => wrap.classList.toggle('hidden', !access.checked);
            access.addEventListener('change', sync);
            sync();
        })();
    </script>
</div>
