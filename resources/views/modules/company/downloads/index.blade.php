<x-company-layout title="Descargas">
    <p class="text-sm text-slate-400 mb-6">Descarga las apps según los productos contratados. Compártelas con clientes o supervisores según corresponda.</p>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($showSupervision)
            @include('modules.downloads.partials.supervision-card', [
                'pwaUrl' => $pwaUrl,
                'apkUrl' => route('company.downloads.apk'),
                'apkReady' => $apkReady ?? false,
                'openClass' => 'bg-indigo-600 hover:bg-indigo-500',
            ])
        @endif

        @if ($showResident)
            @include('modules.downloads.partials.resident-card', [
                'webUrl' => $residentWebUrl,
                'apkUrl' => route('company.downloads.resident-apk'),
                'apkReady' => $residentApkReady ?? false,
                'openClass' => 'bg-teal-600 hover:bg-teal-500',
                'shareHint' => 'Puedes copiar el enlace y enviárselo al cliente o a los residentes.',
            ])
        @endif
    </div>

    @if (! $showSupervision && ! $showResident)
        <p class="text-sm text-slate-500">No hay apps disponibles para los productos de esta empresa.</p>
    @endif
</x-company-layout>
