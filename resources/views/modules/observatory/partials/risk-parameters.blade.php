@php $parameters = $parameters ?? null; $canEdit = $canEdit ?? false; @endphp
@if ($parameters)
    <form method="POST" action="{{ route('company.observatory.risk.update') }}"
          x-data="{
            alpha: {{ (int) $parameters->alpha_percent }},
            gamma: {{ (int) $parameters->gamma_percent }},
            days: {{ (int) $parameters->half_life_days }},
            points: {{ (int) $parameters->report_points_for_full }},
            low: {{ (int) $parameters->threshold_low }},
            mid: {{ (int) $parameters->threshold_medium }},
            high: {{ (int) $parameters->threshold_high }},
            crit: {{ (int) $parameters->threshold_critical }},
            beta() { return Math.max(0, 100 - Number(this.alpha || 0)); },
            lambda() { return (Math.log(2) / Math.max(1, Number(this.days || 1))).toFixed(4); },
            scale() { return (100 / Math.max(1, Number(this.points || 1))).toFixed(2); }
          }">
        @csrf
        @method('PUT')
        <div class="overflow-x-auto rounded-xl border border-indigo-900/60">
            <table class="risk-sheet min-w-[64rem]">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Ajuste</th>
                        <th class="num">Valor</th>
                        <th>Unidad</th>
                        <th class="num">Valor interno</th>
                        <th>Explicación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tone-indigo">
                        <td class="pad font-mono text-[11px]">ALPHA_COMUNA</td>
                        <td class="pad">Importancia del entorno (delitos de la comuna)</td>
                        <td><input type="number" name="alpha_percent" min="0" max="100" x-model.number="alpha" @disabled(! $canEdit) class="cell-input num"></td>
                        <td class="pad">% (porcentaje)</td>
                        <td class="pad num" x-text="(alpha / 100).toFixed(2)"></td>
                        <td class="pad text-slate-400">Cuánto pesa la comuna frente a los reportes de la sede.</td>
                    </tr>
                    <tr class="tone-indigo">
                        <td class="pad font-mono text-[11px]">BETA_INST</td>
                        <td class="pad">Importancia de los reportes internos de la sede</td>
                        <td class="pad num" x-text="beta()"></td>
                        <td class="pad">% (automático)</td>
                        <td class="pad num" x-text="(beta() / 100).toFixed(2)"></td>
                        <td class="pad text-slate-400">Lo que falta para completar el 100% con la comuna.</td>
                    </tr>
                    <tr class="tone-amber">
                        <td class="pad font-mono text-[11px]">GAMMA_INTER</td>
                        <td class="pad">Bono si coinciden comuna y reportes activos</td>
                        <td><input type="number" name="gamma_percent" min="0" max="100" x-model.number="gamma" @disabled(! $canEdit) class="cell-input num"></td>
                        <td class="pad">% (bono)</td>
                        <td class="pad num" x-text="(gamma / 100).toFixed(2)"></td>
                        <td class="pad text-slate-400">Suma extra cuando la comuna y la sede están altas a la vez.</td>
                    </tr>
                    <tr class="tone-sky">
                        <td class="pad font-mono text-[11px]">LAMBDA_DECAY</td>
                        <td class="pad">Días para que un reporte pierda la mitad de su peso</td>
                        <td><input type="number" name="half_life_days" min="1" max="3650" x-model.number="days" @disabled(! $canEdit) class="cell-input num"></td>
                        <td class="pad">Días</td>
                        <td class="pad num" x-text="lambda()"></td>
                        <td class="pad text-slate-400">A los días indicados, el reporte vale la mitad.</td>
                    </tr>
                    <tr class="tone-emerald">
                        <td class="pad font-mono text-[11px]">K_ESCALA_REP</td>
                        <td class="pad">Puntos acumulados para llegar al 100% de alerta</td>
                        <td><input type="number" name="report_points_for_full" min="1" max="10000" x-model.number="points" @disabled(! $canEdit) class="cell-input num"></td>
                        <td class="pad">Puntos</td>
                        <td class="pad num" x-text="scale()"></td>
                        <td class="pad text-slate-400">Con esos puntos la sede llega a 100.</td>
                    </tr>
                    @foreach ([
                        ['UMBRAL_BAJO', 'threshold_low', 'color_low', 'low', 'Puntaje inicial semáforo verde (bajo)', 'De aquí hasta el medio: riesgo aceptable.'],
                        ['UMBRAL_MEDIO', 'threshold_medium', 'color_medium', 'mid', 'Puntaje mínimo semáforo amarillo (medio)', 'Desde aquí: precaución.'],
                        ['UMBRAL_ALTO', 'threshold_high', 'color_high', 'high', 'Puntaje mínimo semáforo naranja (alto)', 'Desde aquí: intervención operativa.'],
                        ['UMBRAL_CRITICO', 'threshold_critical', 'color_critical', 'crit', 'Puntaje mínimo semáforo rojo (crítico)', 'Desde aquí: emergencia.'],
                    ] as [$code, $name, $color, $model, $label, $help])
                        <tr>
                            <td class="pad font-mono text-[11px]" style="box-shadow: inset 3px 0 0 {{ $parameters->{$color} }}; color: {{ $parameters->{$color} }}">{{ $code }}</td>
                            <td class="pad">
                                <span class="inline-flex items-center gap-2">
                                    <input type="color" name="{{ $color }}" value="{{ $parameters->{$color} }}" @disabled(! $canEdit) class="risk-swatch" title="Color de {{ $code }}">
                                    {{ $label }}
                                </span>
                            </td>
                            <td><input type="number" name="{{ $name }}" min="0" max="100" x-model.number="{{ $model }}" @disabled(! $canEdit) class="cell-input num"></td>
                            <td class="pad">Puntos (0–100)</td>
                            <td class="pad num" x-text="{{ $model }}"></td>
                            <td class="pad text-slate-400">{{ $help }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <div class="mt-3 flex justify-end">
                <button type="submit" class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white">Guardar parámetros</button>
            </div>
        @endif
    </form>
@endif
