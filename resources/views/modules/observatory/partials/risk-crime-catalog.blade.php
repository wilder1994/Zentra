@php
    $canEdit = $canEdit ?? false;
@endphp
<div class="overflow-x-auto rounded-xl border border-amber-900/50">
    <table class="risk-sheet risk-sheet-amber risk-catalog">
        <thead>
            <tr>
                <th class="col-name">Delito</th>
                <th class="ctr col-axis">Impacto</th>
                <th class="ctr col-wide">Probabilidad</th>
                <th class="ctr col-split">Criticidad<span class="block">0–100</span></th>
                <th class="ctr col-split">Peso<span class="block">0–10</span></th>
                <th class="ctr col-axis">Semáforo</th>
                @if ($canEdit)<th class="ctr col-actions">Acciones</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($crimeTypes as $type)
                @php
                    $score = (int) $type->impacto * (int) $type->probabilidad;
                    $band = $parameters->bandOf((float) $score);
                    $formId = 'crime-'.$type->id;
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
                    <td><input type="number" name="probabilidad" form="{{ $formId }}" min="1" max="10" value="{{ (int) $type->probabilidad }}" @disabled(! $canEdit) class="cell-input ctr"></td>
                    <td class="pad ctr col-crit">{{ number_format($score, 1) }}</td>
                    <td class="pad ctr">{{ number_format($score / 10, 1) }}</td>
                    <td class="pad ctr" style="{{ $parameters->bandStyle($band) }}">{{ $band === 'CRITICO' ? 'CRÍTICO' : $band }}</td>
                    @if ($canEdit)
                        <td class="cell-actions">
                            <button type="submit" form="{{ $formId }}" class="cell-link cell-link-save">Guardar</button>
                            <button type="submit" form="delete-crime-{{ $type->id }}" class="cell-link cell-link-delete">Eliminar</button>
                        </td>
                    @endif
                </tr>
            @endforeach
            @if ($canEdit)
                <tr style="--row-color: #f59e0b" oninput="if (event.target.type === 'color') this.style.setProperty('--row-color', event.target.value)">
                    <td class="col-name">
                        <div class="flex h-[2.4rem] items-center">
                            <input type="color" name="color" form="crime-new" value="#f59e0b" class="risk-swatch">
                            <input type="text" name="name" form="crime-new" required placeholder="Nuevo delito" class="cell-input">
                        </div>
                    </td>
                    <td><input type="number" name="impacto" form="crime-new" min="1" max="10" value="5" class="cell-input ctr"></td>
                    <td><input type="number" name="probabilidad" form="crime-new" min="1" max="10" value="5" class="cell-input ctr"></td>
                    <td class="col-crit"></td>
                    <td></td>
                    <td></td>
                    <td class="cell-actions"><button type="submit" form="crime-new" class="cell-btn">Crear</button></td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
@foreach ($crimeTypes as $type)
    @if ($canEdit)
        <form id="crime-{{ $type->id }}" method="POST" action="{{ route('company.observatory.crimes.update', $type) }}" class="hidden">
            @csrf
            @method('PUT')
        </form>
        <form id="delete-crime-{{ $type->id }}" method="POST" action="{{ route('company.observatory.crimes.destroy', $type) }}" class="hidden" onsubmit="return confirm('¿Eliminar este delito?')">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endforeach
@if ($canEdit)
    <form id="crime-new" method="POST" action="{{ route('company.observatory.crimes.store') }}" class="hidden">@csrf</form>
@endif
<div class="mt-4 rounded-xl border border-amber-900/50 bg-slate-900 p-4">
    <h3 class="mb-3 text-sm font-semibold text-white">Matriz 10×10 de delitos</h3>
    @include('modules.observatory.partials.risk-matrix', ['axis' => 'Probabilidad', 'parameters' => $parameters])
</div>
