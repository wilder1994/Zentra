@php
    use App\Support\Client\ClientPanelModules;
    $modules = $client->panelModules();
    $hasDoors = $client->hasDoors();
    $available = ClientPanelModules::availableLabelsFor($client);
@endphp

@if ($available !== [])
<section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
    <div>
        <h3 class="text-sm font-semibold text-white">Gestión de módulos</h3>
        <p class="mt-1 text-xs text-slate-500">Qué ve el panel del cliente. Solo se listan módulos permitidos por las líneas activas. Fijos: Resumen, Instalaciones, Personas, Usuarios, Accesos y Ajustes.</p>
    </div>
    <form method="POST" action="{{ route('company.clients.modules.update', $client) }}" class="space-y-3">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach ($available as $key => $label)
                @php
                    $on = (bool) ($modules[$key] ?? true);
                    $doorsLocked = $key === ClientPanelModules::DOORS && ! $hasDoors;
                    if ($doorsLocked) {
                        $on = false;
                    }
                @endphp
                <label class="flex items-start gap-3 rounded-lg border px-3 py-2 {{ $on ? 'border-indigo-500/60 bg-indigo-950/20' : 'border-slate-800 bg-slate-950/40' }} {{ $doorsLocked ? 'opacity-60' : '' }}">
                    <input type="hidden" name="modules[{{ $key }}]" value="0">
                    <input
                        type="checkbox"
                        name="modules[{{ $key }}]"
                        value="1"
                        class="mt-0.5 rounded border-slate-600 text-indigo-600"
                        @checked($on)
                        @disabled($doorsLocked)
                    >
                    <span>
                        <span class="text-sm text-white">{{ $label }}</span>
                        <span class="block text-[11px] text-slate-500">{{ ClientPanelModules::optionalHints()[$key] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <x-ui.button type="submit" size="sm">Guardar módulos</x-ui.button>
    </form>
</section>
@endif
