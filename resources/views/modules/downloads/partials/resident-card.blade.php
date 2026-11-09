@php
    $webUrl = $webUrl ?? '';
    $apkUrl = $apkUrl ?? '';
    $apkReady = (bool) ($apkReady ?? false);
    $openClass = $openClass ?? 'bg-teal-600 hover:bg-teal-500';
    $shareHint = $shareHint ?? 'Comparte el enlace con las personas del conjunto.';
    $uid = 'resident-'.substr(md5($webUrl.$apkUrl), 0, 8);
@endphp

<div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-800">
        <h3 class="text-sm font-semibold text-white">App de residentes (Usuarios app)</h3>
        <p class="text-xs text-slate-500 mt-1">Android: APK. iPhone: enlace web por el momento. El login lo crea el panel en Usuarios app.</p>
    </div>
    <div class="p-5 space-y-4">
        @if ($apkUrl)
            <a href="{{ $apkUrl }}"
               class="inline-flex items-center h-10 px-4 text-sm rounded-lg {{ $openClass }} text-white font-semibold {{ $apkReady ? '' : 'pointer-events-none opacity-50' }}">
                Descargar APK
            </a>
            <p class="text-xs text-slate-400">
                @if ($apkReady)
                    En el celular: abrir el archivo → Instalar (orígenes desconocidos).
                @else
                    El APK se publicará cuando esté generado. Mientras tanto usa el enlace web.
                @endif
            </p>
        @endif

        <div class="flex flex-col sm:flex-row gap-5 items-start">
            <canvas id="{{ $uid }}-qr" class="w-40 h-40 rounded-lg bg-white p-2 shrink-0" width="160" height="160"></canvas>
            <div class="min-w-0 space-y-3">
                <p class="text-xs text-slate-500">Enlace para compartir (web / iPhone)</p>
                <p class="text-xs text-slate-400 break-all" id="{{ $uid }}-url">{{ $webUrl }}</p>
                <p class="text-[11px] text-slate-500">{{ $shareHint }}</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $webUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center h-9 px-3 text-sm rounded-lg border border-slate-700 text-slate-200 hover:bg-slate-800">
                        Abrir web
                    </a>
                    <button type="button" id="{{ $uid }}-copy"
                            class="inline-flex items-center h-9 px-3 text-sm rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800">
                        Copiar enlace
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        (function () {
            const url = @json($webUrl);
            const canvas = document.getElementById(@json($uid.'-qr'));
            if (canvas && window.QRCode && url) {
                QRCode.toCanvas(canvas, url, { width: 160, margin: 1, color: { dark: '#0f172a', light: '#ffffff' } });
            }
            document.getElementById(@json($uid.'-copy'))?.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(url);
                    const btn = document.getElementById(@json($uid.'-copy'));
                    if (btn) btn.textContent = 'Copiado';
                } catch {
                    window.prompt('Copie el enlace', url);
                }
            });
        })();
    </script>
@endpush
