@php
    $hasPin = $installation->hasCoordinates();
    $apiKey = $maps['api_key'] ?? config('google-maps.api_key');
    $lat = $hasPin ? (float) $installation->latitude : null;
    $lng = $hasPin ? (float) $installation->longitude : null;
@endphp

<div class="relative h-full min-h-[14rem] overflow-hidden bg-slate-950 md:min-h-full">
    @if ($hasPin && $apiKey)
        <img
            src="https://maps.googleapis.com/maps/api/staticmap?{{ http_build_query([
                'center' => $lat.','.$lng,
                'zoom' => 16,
                'size' => '480x640',
                'scale' => 2,
                'maptype' => 'hybrid',
                'markers' => 'color:0x4f46e5|'.$lat.','.$lng,
                'key' => $apiKey,
            ]) }}"
            alt="Mapa de {{ $installation->name }}"
            class="absolute inset-0 h-full w-full object-cover"
            loading="lazy"
        >
        <a
            href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}"
            target="_blank"
            rel="noopener"
            class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/80 to-transparent px-3 py-2 text-[10px] font-semibold text-indigo-200 hover:text-white"
        >
            Abrir en Maps
        </a>
    @elseif ($hasPin)
        <iframe
            title="Mapa de {{ $installation->name }}"
            class="absolute inset-0 h-full w-full border-0"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lng - 0.008 }}%2C{{ $lat - 0.006 }}%2C{{ $lng + 0.008 }}%2C{{ $lat + 0.006 }}&layer=mapnik&marker={{ $lat }}%2C{{ $lng }}"
        ></iframe>
    @else
        <div class="flex h-full min-h-[14rem] items-center justify-center px-4 text-center text-xs text-slate-500">
            Sin pin en el mapa
        </div>
    @endif
</div>
