@php
    $indexRoute = $indexRoute ?? 'client.observatory.events.index';
    $typesRoute = $typesRoute ?? null;
    $vista = $vista ?? 'tablero';
    $tabQuery = request()->except('vista', 'page', 'seccion', 'eje', 'year');
    $riskCatalog = $riskCatalog ?? false;
    $riskAxis = $riskAxis ?? 'exogeno';
@endphp
@if ($riskCatalog && $typesRoute)
    <a href="{{ route($typesRoute, ['seccion' => 'catalogo', 'eje' => 'exogeno']) }}"
       @class(['admin-header-tab', 'is-active' => $riskAxis === 'exogeno'])>Riesgo exógeno (Comuna)</a>
    <a href="{{ route($typesRoute, ['seccion' => 'catalogo', 'eje' => 'endogeno']) }}"
       @class(['admin-header-tab', 'is-active' => $riskAxis === 'endogeno'])>Riesgo endógeno (Sede)</a>
@else
    <a href="{{ route($indexRoute, $tabQuery + ['vista' => 'tablero']) }}"
       @class(['admin-header-tab', 'is-active' => $vista === 'tablero'])>Tablero</a>
    <a href="{{ route($indexRoute, $tabQuery + ['vista' => 'eventos']) }}"
       @class(['admin-header-tab', 'is-active' => $vista === 'eventos'])>Eventos</a>
    @if ($typesRoute)
        <a href="{{ route($typesRoute, $tabQuery) }}"
           @class(['admin-header-tab', 'is-active' => $vista === 'tipos'])>Riesgos</a>
    @endif
@endif
