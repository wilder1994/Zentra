@php
    $mapId = $mapId ?? 'installation-pin-map';
    $callback = $callback ?? 'initInstallationPinMap';
    $cali = $installation->hasCoordinates()
        ? app(\App\Support\Geo\CaliComunaLayer::class)->locate(
            (float) $installation->latitude,
            (float) $installation->longitude,
        )
        : null;
@endphp
@if ($installation->hasCoordinates() && ! empty($maps['api_key']))
    @push('scripts')
        <script>
            window.{{ $callback }} = function () {
                const pos = {
                    lat: {{ (float) $installation->latitude }},
                    lng: {{ (float) $installation->longitude }},
                };
                const el = document.getElementById(@json($mapId));
                if (!el || !window.google?.maps) return;
                const map = new google.maps.Map(el, {
                    center: pos,
                    zoom: 16,
                    mapTypeId: google.maps.MapTypeId.HYBRID,
                    streetViewControl: false,
                });
                new google.maps.Marker({
                    position: pos,
                    map,
                    title: @json($installation->name),
                });
                if (typeof window.attachCaliLayer === 'function') {
                    window.attachCaliLayer(map, @js($cali['code'] ?? ''), @js(route('geo.cali-comunas')));
                }
                google.maps.event.addListenerOnce(map, 'idle', function () {
                    google.maps.event.trigger(map, 'resize');
                    map.setCenter(pos);
                });
            };
        </script>
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $maps['api_key'] }}&callback={{ $callback }}" async defer></script>
    @endpush
@endif
