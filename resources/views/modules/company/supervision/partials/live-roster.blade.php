@php
    $rows = $rows ?? [];
@endphp
@if ($rows === [])
    <p class="text-sm text-slate-500">Nadie en turno ahora.</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-800">
                <th class="text-left py-2 pr-2 font-medium">Supervisor</th>
                <th class="text-left py-2 pr-2 font-medium">Estado</th>
                <th class="text-right py-2 font-medium">Km</th>
                <th class="text-right py-2 font-medium">Rev.</th>
                <th class="text-right py-2 font-medium"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="supervision-live-pick border-b border-slate-800/70 align-top cursor-pointer hover:bg-slate-800/60"
                    data-shift-id="{{ $row['shift_id'] ?? '' }}">
                    <td class="py-2 pr-2">
                        <p class="font-medium text-slate-100">{{ $row['user'] ?? 'Supervisor' }}</p>
                        @php
                            $signal = $row['signal'] ?? (! empty($row['online']) ? 'online' : 'no_signal');
                            $signalClass = match ($signal) {
                                'screen_off' => 'text-amber-400',
                                'online' => 'text-emerald-400',
                                default => 'text-red-400',
                            };
                        @endphp
                        <p class="text-xs mt-0.5 {{ $signalClass }}">
                            {{ $row['online_label'] ?? 'Sin señal' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-0.5">Inicio {{ $row['started_at_label'] ?? '—' }}</p>
                        <p class="text-xs text-sky-500 mt-0.5">{{ $row['zone_label'] ?? 'Sin zona' }}</p>
                    </td>
                    <td class="py-2 pr-2 text-xs text-slate-300 leading-snug">{{ $row['status_line'] ?? '—' }}</td>
                    <td class="py-2 text-right text-slate-300 tabular-nums">{{ number_format((float) ($row['km'] ?? 0), 1) }}</td>
                    <td class="py-2 text-right text-slate-300 tabular-nums">{{ (int) ($row['reviews_count'] ?? 0) }}</td>
                    <td class="py-2 pl-2 text-right">
                        <div class="flex flex-col items-end gap-1">
                            @if (! empty($row['sheet_url']))
                                <a href="{{ $row['sheet_url'] }}"
                                   class="inline-flex rounded-md border border-slate-700 px-2 py-0.5 text-[11px] font-semibold text-indigo-300 hover:bg-slate-800"
                                   onclick="event.stopPropagation()">Ver</a>
                            @endif
                            @if (($canManageZones ?? false) && ! empty($row['zones_url']))
                                <button type="button" class="js-shift-zones inline-flex rounded-md border border-sky-700/70 px-2 py-0.5 text-[11px] font-semibold text-sky-300 hover:bg-slate-800"
                                        data-url="{{ $row['zones_url'] }}" data-user="{{ $row['user'] ?? 'Supervisor' }}">Zonas</button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
