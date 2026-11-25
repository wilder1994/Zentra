@php
    $crimeTypes = $crimeTypes ?? collect();
    $crimeYear = $crimeYear ?? (int) now()->year;
    $crimeGrid = $crimeGrid ?? [];
    $crimeComunas = $crimeComunas ?? [];
    $crimeVeredas = $crimeVeredas ?? [];
    $canEdit = $canEdit ?? false;
    $activeTypes = $crimeTypes->where('is_active', true);
    $crimeSections = [
        ['title' => 'Comunas', 'rows' => $crimeComunas, 'norm' => 'R comuna normalizado', 'split' => ' '],
        ['title' => 'Veredas', 'rows' => $crimeVeredas, 'norm' => 'R vereda normalizado', 'split' => ' · '],
    ];
@endphp
<form id="crime-figures-form" method="POST" action="{{ route('company.observatory.crimes.figures') }}" class="-mt-2 space-y-6">
    @csrf
    <input type="hidden" name="year" value="{{ $crimeYear }}">
    @foreach ($crimeSections as $section)
        @php
            $crimeIndex = [];
            foreach ($section['rows'] as $place) {
                $sum = 0.0;
                foreach ($activeTypes as $type) {
                    $weight = \App\Support\Observatory\RiskMath::weight((int) $type->impacto, (int) $type->probabilidad);
                    $sum += ((int) ($crimeGrid[$place['code']][$type->id] ?? 0)) * $weight;
                }
                $crimeIndex[$place['code']] = round($sum, 1);
            }
            $crimeIndexMax = max($crimeIndex === [] ? [0] : $crimeIndex);
        @endphp
        <div data-crime-table>
            <div class="mb-2 flex items-center gap-3">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-teal-200">{{ $section['title'] }}</h3>
                <input type="search" data-crime-filter autocomplete="off" placeholder="Buscar {{ strtolower($section['title']) }}"
                       class="h-8 w-56 max-w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-xs text-white placeholder:text-slate-500">
            </div>
            <div class="overflow-x-auto rounded-xl border border-teal-900/50">
                <table class="risk-sheet risk-sheet-teal risk-comunas">
                    <colgroup>
                        <col class="col-crime">
                        @foreach ($section['rows'] as $place)
                            <col class="col-comuna">
                        @endforeach
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="sticky-col col-crime">Delito</th>
                            @foreach ($section['rows'] as $place)
                                @php
                                    $heading = $section['split'] === ' · '
                                        ? ($place['label'] ?? $place['name'])
                                        : (string) $place['name'];
                                    $parts = explode($section['split'], $heading, 2);
                                @endphp
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
                                @foreach ($section['rows'] as $place)
                                    <td>
                                        <input type="number" min="0" max="9999999" @disabled(! $canEdit)
                                               name="figures[{{ $place['code'] }}][{{ $type->id }}]"
                                               value="{{ (int) ($crimeGrid[$place['code']][$type->id] ?? 0) }}"
                                               class="cell-input num">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="--row-color: #5eead4">
                            <td class="sticky-col col-crime pad whitespace-nowrap">Índice ponderado</td>
                            @foreach ($section['rows'] as $place)
                                <td class="pad num">{{ number_format($crimeIndex[$place['code']] ?? 0, 1, '.', '') }}</td>
                            @endforeach
                        </tr>
                        <tr style="--row-color: #5eead4">
                            <td class="sticky-col col-crime pad whitespace-nowrap">{{ $section['norm'] }}</td>
                            @foreach ($section['rows'] as $place)
                                @php
                                    $normalized = $crimeIndexMax > 0
                                        ? round((($crimeIndex[$place['code']] ?? 0) / $crimeIndexMax) * 100, 1)
                                        : 0.0;
                                    $band = $parameters->bandOf((float) $normalized);
                                @endphp
                                <td class="pad num" style="{{ $parameters->bandStyle($band) }}">{{ number_format($normalized, 1, '.', '') }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</form>
<script>
    document.querySelectorAll('[data-crime-filter]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
        input.addEventListener('input', () => {
            const table = input.closest('[data-crime-table]')?.querySelector('table');
            const head = table?.tHead?.rows[0];
            if (!head) {
                return;
            }
            const words = normalizeCrimeQuery(input.value).split(' ').filter(Boolean);
            [...head.cells].forEach((cell, index) => {
                if (index === 0) {
                    return;
                }
                const show = words.every((word) => normalizeCrimeQuery(cell.innerText).includes(word));
                const display = show ? '' : 'none';
                cell.style.display = display;
                [...table.tBodies].forEach((body) => {
                    [...body.rows].forEach((row) => {
                        if (row.cells[index]) {
                            row.cells[index].style.display = display;
                        }
                    });
                });
            });
        });
    });

    function normalizeCrimeQuery(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/\p{M}/gu, '')
            .replace(/\s+/g, ' ')
            .replace(/\b0+(\d)/g, '$1')
            .trim();
    }
</script>
