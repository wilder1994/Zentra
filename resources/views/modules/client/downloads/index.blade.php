<x-client-layout title="Descargas">
    <p class="text-sm text-slate-400 mb-6">APK y enlace para compartir con las personas del conjunto. Las cuentas se crean en Usuarios app.</p>

    @include('modules.downloads.partials.resident-card', [
        'webUrl' => $webUrl,
        'apkUrl' => $apkUrl,
        'apkReady' => $apkReady,
        'openClass' => 'bg-teal-600 hover:bg-teal-500',
        'shareHint' => 'Copia el enlace y envíalo a residentes (WhatsApp, correo, etc.).',
    ])
</x-client-layout>
