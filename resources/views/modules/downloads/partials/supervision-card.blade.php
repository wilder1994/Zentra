@php
    $pwaUrl = $pwaUrl ?? '';
    $apkUrl = $apkUrl ?? '';
    $apkReady = (bool) ($apkReady ?? false);
    $openClass = $openClass ?? 'bg-indigo-600 hover:bg-indigo-500';
@endphp

<div class="max-w-xl rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-800">
        <h3 class="text-sm font-semibold text-white">App de Supervisión</h3>
        <p class="text-xs text-slate-500 mt-1">Android: instale el APK v1.9. El login es el mismo usuario de empresa; la API es Zentra. Tema claro u oscuro en el botón Tema.</p>
    </div>
    <div class="p-5 space-y-4">
        @if ($apkUrl)
            <a href="{{ $apkUrl }}"
               class="inline-flex items-center h-10 px-4 text-sm rounded-lg {{ $openClass }} text-white font-semibold">
                Descargar APK
            </a>
            <p class="text-xs text-slate-400">
                @if ($apkReady)
                    En el celular: abrir el archivo → Instalar (orígenes desconocidos). Una sola app para todas las empresas.
                @else
                    El archivo se publica tras generar el APK. Si la descarga falla, use la web de respaldo.
                @endif
            </p>
        @endif
        <div class="flex flex-col sm:flex-row gap-5 items-start">
            <canvas id="supervision-pwa-qr" class="w-40 h-40 rounded-lg bg-white p-2 shrink-0" width="160" height="160"></canvas>
            <div class="min-w-0 space-y-3">
                <p class="text-xs text-slate-500">Web (PWA) de respaldo</p>
                <p class="text-xs text-slate-400 break-all" id="supervision-pwa-url">{{ $pwaUrl }}</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $pwaUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center h-9 px-3 text-sm rounded-lg border border-slate-700 text-slate-200 hover:bg-slate-800">
                        Abrir web
                    </a>
                    <button type="button" id="copy-pwa-url"
                            class="inline-flex items-center h-9 px-3 text-sm rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800">
                        Copiar enlace
                    </button>
                </div>
            </div>
        </div>
        <ol class="text-xs text-slate-400 space-y-1.5 list-decimal list-inside">
            <li>Android: <span class="text-slate-200">Descargar APK</span> e instalar. Permita orígenes desconocidos si el teléfono lo pide.</li>
            <li>Ingresar con el usuario <span class="text-slate-200">supervisor</span> de la empresa (mismo que ahora).</li>
            <li>Web: Chrome/Safari → añadir a inicio. Con pantalla apagada el GPS solo va en el APK (corte 2).</li>
        </ol>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        (function () {
            const url = @json($pwaUrl);
            const canvas = document.getElementById('supervision-pwa-qr');
            if (canvas && window.QRCode && url) {
                QRCode.toCanvas(canvas, url, { width: 160, margin: 1, color: { dark: '#0f172a', light: '#ffffff' } });
            }
            document.getElementById('copy-pwa-url')?.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(url);
                    const btn = document.getElementById('copy-pwa-url');
                    if (btn) btn.textContent = 'Copiado';
                } catch {
                    window.prompt('Copie el enlace', url);
                }
            });
        })();
    </script>
@endpush
