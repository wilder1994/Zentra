@php
    $rows = $reportLog ?? [];
@endphp
<div class="overflow-x-auto rounded-xl border border-sky-900/50">
    <table class="risk-sheet risk-sheet-sky min-w-[72rem]">
        <thead>
            <tr>
                <th>Reporte</th>
                <th>Instalación</th>
                <th>Endógeno</th>
                <th>Cruce 10×10</th>
                <th class="num">0–100</th>
                <th>Semáforo</th>
                <th class="num">Días</th>
                <th class="num">Severidad</th>
                <th class="num">Decaimiento</th>
                <th class="num">Aporte</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="pad font-mono">R-{{ $row['id'] }}</td>
                    <td class="pad">{{ $row['site'] }}</td>
                    <td class="pad">{{ $row['type'] }}</td>
                    <td class="pad num">{{ $row['cruce'] }}</td>
                    <td class="pad num">{{ number_format((float) $row['score'], 1) }}</td>
                    <td class="pad ctr" style="{{ $parameters->bandStyle($row['band']) }}">{{ $row['band'] === 'CRITICO' ? 'CRÍTICO' : $row['band'] }}</td>
                    <td class="pad num">{{ $row['days'] }}</td>
                    <td class="pad num">{{ number_format((float) $row['severity'], 1) }}</td>
                    <td class="pad num">{{ number_format((float) $row['decay'], 3) }}</td>
                    <td class="pad num">{{ number_format((float) $row['aporte'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="pad text-slate-500">Todavía no hay reportes.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p class="mt-2 text-[11px] text-slate-500">Sale de los reportes reales. El decaimiento y el aporte se recalculan con los parámetros vigentes. Los últimos 300.</p>
