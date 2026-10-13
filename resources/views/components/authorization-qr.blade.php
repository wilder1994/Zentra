@props(['value', 'size' => 'w-56'])

<div {{ $attributes->merge(['class' => "{$size} aspect-square rounded-2xl bg-white p-3 shadow-lg shadow-black/30"]) }}
     data-auth-qr="{{ $value }}" role="img" aria-label="Código QR de la autorización"></div>

@once
    <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    <script>
        (function () {
            function render() {
                document.querySelectorAll('[data-auth-qr]').forEach(function (el) {
                    if (el.dataset.rendered || typeof qrcode === 'undefined') return;
                    var qr = qrcode(0, 'M');
                    qr.addData(el.dataset.authQr);
                    qr.make();
                    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
                    el.dataset.rendered = '1';
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', render);
            } else {
                render();
            }
        })();
    </script>
@endonce
