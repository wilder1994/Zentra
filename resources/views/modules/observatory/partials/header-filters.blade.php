@php
    /** @var \App\Support\Observatory\ObservatoryFilters $filters */
    $vista = $vista ?? 'tablero';
    $exportUrl = ($exportRoute ?? null) ? $exportRoute.'?'.http_build_query($filters->query()) : null;
@endphp
<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-2" id="observatory-filter">
    <input type="hidden" name="vista" value="{{ $vista }}">
    @include('modules.company.supervision.partials.period-picker', ['from' => $filters->from, 'to' => $filters->to])
    @include('modules.observatory.partials.filters-panel', [
        'filters' => $filters,
        'filterOptions' => $filterOptions,
        'vista' => $vista,
        'isCompany' => $isCompany ?? false,
    ])
    @if ($vista === 'tablero')
        @include('modules.observatory.partials.share-modal')
        @if ($exportUrl)
            <a href="{{ $exportUrl }}"
               class="inline-flex items-center h-9 px-3 text-sm rounded-lg border border-amber-500/40 text-amber-200 hover:bg-amber-500/10">
                Descargar PPTX
            </a>
        @endif
    @endif
</form>
