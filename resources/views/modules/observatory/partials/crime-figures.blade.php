@php
    $crimeTypes = $crimeTypes ?? collect();
    $crimeYear = $crimeYear ?? (int) now()->year;
    $crimeGrid = $crimeGrid ?? [];
    $crimeComunas = $crimeComunas ?? [];
    $canEdit = $canEdit ?? false;
    $activeTypes = $crimeTypes->where('is_active', true);
    $crimeIndex = [];
    foreach ($crimeComunas as $comuna) {
        $sum = 0.0;
        foreach ($activeTypes as $type) {
            $weight = \App\Support\Observatory\RiskMath::weight((int) $type->impacto, (int) $type->probabilidad);
            $sum += ((int) ($crimeGrid[$comuna['code']][$type->id] ?? 0)) * $weight;
        }
        $crimeIndex[$comuna['code']] = round($sum, 1);
    }
    $crimeIndexMax = max($crimeIndex === [] ? [0] : $crimeIndex);
@endphp
<form id="crime-figures-form" method="POST" action="{{ route('company.observatory.crimes.figures') }}" class="-mt-2">
    @csrf
    <input type="hidden" name="year" value="{{ $crimeYear }}">
    <div class="overflow-x-auto rounded-xl border border-teal-900/50">
        <table class="risk-sheet risk-sheet-teal risk-comunas">
            <colgroup>
                <col class="col-crime">
                @foreach ($crimeComunas as $comuna)
                    <col class="col-comuna">
                @endforeach
            </colgroup>
            <thead>
                <tr>
                    <th class="sticky-col col-crime">Delito</th>
                    @foreach ($crimeComunas as $comuna)
                        @php $parts = preg_split('/\s+/', (string) $comuna['name'], 2) ?: [$comuna['name']]; @endphp
                        <th class="col-comuna">
                            <span class="block">{{ $parts[0] }}</span>
                            @if (isset($parts[1]))<span class="block">{{ $parts[1] }}</span>@endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($activeTypes as $type)
                    <tr style="--row-color: {{ $type->color }}">
                        <td class="sticky-col col-crime pad whitespace-nowrap">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $type->color }}"></span>
                                {{ $type->name }}
                            </span>
                        </td>
                        @foreach ($crimeComunas as $comuna)
                            <td>
                                <input type="number" min="0" max="9999999" @disabled(! $canEdit)
                                       name="figures[{{ $comuna['code'] }}][{{ $type->id }}]"
                                       value="{{ (int) ($crimeGrid[$comuna['code']][$type->id] ?? 0) }}"
                                       class="cell-input num">
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr style="--row-color: #5eead4">
                    <td class="sticky-col col-crime pad whitespace-nowrap">Índice ponderado</td>
                    @foreach ($crimeComunas as $comuna)
                        <td class="pad num">{{ number_format($crimeIndex[$comuna['code']] ?? 0, 1, '.', '') }}</td>
                    @endforeach
                </tr>
                <tr style="--row-color: #5eead4">
                    <td class="sticky-col col-crime pad whitespace-nowrap">R comuna normalizado</td>
                    @foreach ($crimeComunas as $comuna)
                        @php
                            $normalized = $crimeIndexMax > 0
                                ? round((($crimeIndex[$comuna['code']] ?? 0) / $crimeIndexMax) * 100, 1)
                                : 0.0;
                            $band = $parameters->bandOf((float) $normalized);
                        @endphp
                        <td class="pad num" style="{{ $parameters->bandStyle($band) }}">{{ number_format($normalized, 1, '.', '') }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</form>
