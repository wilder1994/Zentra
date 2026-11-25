@php
    $parameters = $risk['parameters'] ?? null;
    $crimeTypes = $risk['crime_types'] ?? collect();
    $reportTypes = $risk['report_types'] ?? collect();
@endphp
<section class="obs-card flex min-h-0 flex-col overflow-hidden p-2.5">
    <h3 class="shrink-0 text-sm font-semibold text-white">Catálogo de riesgo exógeno</h3>
    <div class="mt-1.5 min-h-0 flex-1 overflow-auto sidebar-scroll">
        <table class="w-full text-[11px]">
            <thead class="text-slate-500">
                <tr>
                    <th class="py-1 pr-2 text-left font-medium">Delito</th>
                    <th class="px-1 py-1 text-right font-medium">Imp.</th>
                    <th class="px-1 py-1 text-right font-medium">Prob.</th>
                    <th class="py-1 pl-1 text-left font-medium">Semáforo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($crimeTypes as $type)
                    @php
                        $score = (int) $type->impacto * (int) $type->probabilidad;
                        $band = $parameters ? $parameters->bandOf((float) $score) : 'BAJO';
                    @endphp
                    <tr class="border-t border-slate-800">
                        <td class="py-1 pr-2 text-slate-200">
                            <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full" style="background: {{ $type->color }}"></span>{{ $type->name }}
                        </td>
                        <td class="px-1 py-1 text-right tabular-nums text-slate-300">{{ (int) $type->impacto }}</td>
                        <td class="px-1 py-1 text-right tabular-nums text-slate-300">{{ (int) $type->probabilidad }}</td>
                        <td class="py-1 pl-1">
                            @if ($parameters)
                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="{{ $parameters->bandStyle($band) }}">{{ $band === 'CRITICO' ? 'CRÍTICO' : $band }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-slate-500">Sin delitos en la parametrización.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<section class="obs-card flex min-h-0 flex-col overflow-hidden p-2.5">
    <h3 class="shrink-0 text-sm font-semibold text-white">Catálogo de riesgo endógeno</h3>
    <div class="mt-1.5 min-h-0 flex-1 overflow-auto sidebar-scroll">
        <table class="w-full text-[11px]">
            <thead class="text-slate-500">
                <tr>
                    <th class="py-1 pr-2 text-left font-medium">Riesgo endógeno</th>
                    <th class="px-1 py-1 text-right font-medium">Imp.</th>
                    <th class="px-1 py-1 text-right font-medium">Urg.</th>
                    <th class="py-1 pl-1 text-left font-medium">Semáforo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reportTypes as $type)
                    @php
                        $score = (int) $type->impacto * (int) $type->urgencia;
                        $band = $parameters ? $parameters->bandOf((float) $score) : 'BAJO';
                    @endphp
                    <tr class="border-t border-slate-800">
                        <td class="py-1 pr-2 text-slate-200">
                            <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full" style="background: {{ $type->color }}"></span>{{ $type->name }}
                        </td>
                        <td class="px-1 py-1 text-right tabular-nums text-slate-300">{{ (int) $type->impacto }}</td>
                        <td class="px-1 py-1 text-right tabular-nums text-slate-300">{{ (int) $type->urgencia }}</td>
                        <td class="py-1 pl-1">
                            @if ($parameters)
                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold" style="{{ $parameters->bandStyle($band) }}">{{ $band === 'CRITICO' ? 'CRÍTICO' : $band }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-slate-500">Sin registros en el catálogo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
