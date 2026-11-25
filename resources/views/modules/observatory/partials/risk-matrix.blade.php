@php
    $axis = $axis ?? 'Probabilidad';
    $low = (int) $parameters->threshold_medium;
    $high = (int) $parameters->threshold_high;
    $critical = (int) $parameters->threshold_critical;
    $groups = [
        ['span' => 3, 'rows' => [10, 9, 8], 'title' => '4 · '.$axis.' crítico', 'range' => '(76–100)', 'impact' => '4 · Impacto crítico (76–100)'],
        ['span' => 2, 'rows' => [7, 6], 'title' => '3 · '.$axis.' alto', 'range' => '(51–75)', 'impact' => '3 · Impacto alto (51–75)'],
        ['span' => 3, 'rows' => [5, 4, 3], 'title' => '2 · '.$axis.' medio', 'range' => '(26–50)', 'impact' => '2 · Impacto medio (26–50)'],
        ['span' => 2, 'rows' => [2, 1], 'title' => '1 · '.$axis.' bajo', 'range' => '(0–25)', 'impact' => '1 · Impacto bajo (0–25)'],
    ];
    $tone = function (int $score) use ($parameters): string {
        return $parameters->bandStyle($parameters->bandOf((float) $score));
    };
@endphp
<div class="overflow-x-auto">
    <table class="risk-matrix">
        <colgroup>
            <col class="axis-label">
            <col class="axis-num">
            @foreach (range(10, 1) as $column)
                <col class="score">
            @endforeach
        </colgroup>
        <thead>
            <tr class="text-slate-300">
                <th rowspan="2" colspan="2" class="border border-slate-700 px-2 py-1">Impacto/{{ $axis }}</th>
                @foreach ($groups as $group)
                    <th colspan="{{ $group['span'] }}" class="border border-slate-700 px-1 py-1 font-medium"><span class="block">{{ $group['title'] }}</span><span class="block">{{ $group['range'] }}</span></th>
                @endforeach
            </tr>
            <tr class="text-slate-400">
                @foreach (range(10, 1) as $column)
                    <th class="border border-slate-700 px-1 py-1">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($groups as $group)
                @foreach ($group['rows'] as $index => $row)
                    <tr>
                        @if ($index === 0)
                            <th rowspan="{{ count($group['rows']) }}" class="border border-slate-700 px-2 py-1 text-slate-200">{{ $group['impact'] }}</th>
                        @endif
                        <th class="border border-slate-700 px-1 py-1 text-slate-300">{{ $row }}</th>
                        @foreach (range(10, 1) as $column)
                            @php $score = $row * $column; @endphp
                            <td class="border border-slate-800 px-1 py-1 tabular-nums" style="{{ $tone($score) }}">{{ number_format($score, 1) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <p class="mt-2 text-[11px] text-slate-500">Escala 0–100. Verde 0–{{ $low - 0.1 }} · Amarillo {{ $low }}–{{ $high - 0.1 }} · Naranja {{ $high }}–{{ $critical - 0.1 }} · Rojo {{ $critical }}–100.</p>
</div>
