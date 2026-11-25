@php
    $canEdit = $canEdit ?? false;
@endphp
<div class="overflow-x-auto rounded-xl border border-amber-900/50">
    <table class="risk-sheet risk-sheet-amber risk-catalog">
        <thead>
            <tr>
                <th class="col-name">Riesgo endógeno</th>
                <th class="ctr col-axis">Impacto</th>
                <th class="ctr col-wide">Urgencia</th>
                <th class="ctr col-split">Criticidad<span class="block">0–100</span></th>
                <th class="ctr col-split">Severidad<span class="block">0–10</span></th>
                <th class="ctr col-axis">Semáforo</th>
                @if ($canEdit)<th class="ctr col-actions">Acciones</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($types as $type)
                @php
                    $score = (int) $type->impacto * (int) $type->urgencia;
                    $band = isset($parameters) ? $parameters->bandOf((float) $score) : 'BAJO';
                    $formId = 'report-type-'.$type->id;
                @endphp
                <tr style="--row-color: {{ $type->color }}" oninput="if (event.target.type === 'color') this.style.setProperty('--row-color', event.target.value)">
                    <td class="col-name">
                        <div class="flex h-[2.4rem] items-center">
                            <input type="hidden" name="is_active" form="{{ $formId }}" value="{{ $type->is_active ? 1 : 0 }}">
                            <input type="color" name="color" form="{{ $formId }}" value="{{ $type->color }}" @disabled(! $canEdit) class="risk-swatch">
                            <input type="text" name="name" form="{{ $formId }}" value="{{ $type->name }}" required @disabled(! $canEdit) class="cell-input">
                        </div>
                    </td>
                    <td><input type="number" name="impacto" form="{{ $formId }}" min="1" max="10" value="{{ (int) $type->impacto }}" @disabled(! $canEdit) class="cell-input ctr"></td>
                    <td><input type="number" name="urgencia" form="{{ $formId }}" min="1" max="10" value="{{ (int) $type->urgencia }}" @disabled(! $canEdit) class="cell-input ctr"></td>
                    <td class="pad ctr col-crit">{{ number_format($score, 1) }}</td>
                    <td class="pad ctr">{{ number_format($score / 10, 1) }}</td>
                    <td class="pad ctr" style="{{ $parameters->bandStyle($band) }}">{{ $band === 'CRITICO' ? 'CRÍTICO' : $band }}</td>
                    @if ($canEdit)
                        <td class="cell-actions">
                            <button type="submit" form="{{ $formId }}" class="cell-link cell-link-save">Guardar</button>
                            <button type="submit" form="delete-type-{{ $type->id }}" class="cell-link cell-link-delete">Eliminar</button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" class="pad text-slate-500">Sin registros en el catálogo.</td></tr>
            @endforelse
            @if ($canEdit && ($storeUrl || ($storeRoute ?? null)))
                <tr style="--row-color: {{ $nextColor ?? '#94a3b8' }}" oninput="if (event.target.type === 'color') this.style.setProperty('--row-color', event.target.value)">
                    <td class="col-name">
                        <div class="flex h-[2.4rem] items-center">
                            <input type="color" name="color" form="report-type-new" value="{{ $nextColor ?? '#94a3b8' }}" class="risk-swatch">
                            <input type="text" name="name" form="report-type-new" required placeholder="Nuevo riesgo endógeno" class="cell-input">
                        </div>
                    </td>
                    <td><input type="number" name="impacto" form="report-type-new" min="1" max="10" value="6" class="cell-input ctr"></td>
                    <td><input type="number" name="urgencia" form="report-type-new" min="1" max="10" value="6" class="cell-input ctr"></td>
                    <td class="col-crit"></td>
                    <td></td>
                    <td></td>
                    <td class="cell-actions"><button type="submit" form="report-type-new" class="cell-btn">Crear</button></td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
@if ($canEdit)
    @foreach ($types as $type)
        <form id="report-type-{{ $type->id }}" method="POST" action="{{ route($updateRoute, $type) }}" class="hidden">
            @csrf
            @method('PUT')
        </form>
        <form id="delete-type-{{ $type->id }}" method="POST" action="{{ route($destroyRoute, $type) }}" class="hidden" onsubmit="return confirm('¿Eliminar este registro del catálogo?')">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
    @if ($storeUrl || ($storeRoute ?? null))
        <form id="report-type-new" method="POST" action="{{ $storeUrl ?? route($storeRoute) }}" class="hidden">@csrf</form>
    @endif
@endif
<div class="mt-4 rounded-xl border border-violet-900/50 bg-slate-900 p-4">
    <h3 class="mb-3 text-sm font-semibold text-white">Matriz 10×10 de reportes</h3>
    @include('modules.observatory.partials.risk-matrix', ['axis' => 'Urgencia', 'parameters' => $parameters])
</div>
