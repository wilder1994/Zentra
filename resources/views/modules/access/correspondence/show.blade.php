<x-access-layout title="Detalle de correspondencia">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.correspondence.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    @if($errors->any())
        <div class="mb-4 max-w-3xl rounded-lg border border-red-700 bg-red-900/40 px-4 py-3 text-sm text-red-200">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @php
        $statusLabel = ['pending' => 'Pendiente', 'delivered' => 'Entregado', 'voided' => 'Anulado'][$item->status] ?? $item->status;
        $statusClass = match ($item->status) {
            'delivered' => 'bg-emerald-500/15 text-emerald-400',
            'voided' => 'bg-red-500/15 text-red-400',
            default => 'bg-amber-500/15 text-amber-400',
        };
        $rows = [
            'Unidad' => $item->unitLabel(),
            'Para' => $item->recipientLabel(),
            'Tipo' => ($item->quantity > 1 ? $item->quantity.' × ' : '').$item->typeLabel(),
            'Transportadora' => $item->carrier ?? '—',
            'Guía' => $item->courier_guide ?? '—',
            'Puerta' => $item->location?->name ?? '—',
            'Llegó' => $item->received_at?->format('d/m/Y H:i').' · '.$item->received_at?->diffForHumans(),
            'Recibió (vigilante)' => $item->receiver?->name ?? '—',
        ];
        if ($item->status === 'delivered') {
            $rows['Entregado'] = $item->delivered_at?->format('d/m/Y H:i');
            $rows['Entregado a'] = $item->deliveredToLabel();
            $rows['Entregó (vigilante)'] = $item->deliverer?->name ?? '—';
        }
        if ($item->status === 'voided') {
            $rows['Anulado'] = $item->voided_at?->format('d/m/Y H:i').' · '.($item->voider?->name ?? '—');
            $rows['Motivo'] = $item->void_reason ?? '—';
        }
    @endphp

    <div class="max-w-3xl space-y-4">
        <div class="flex overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <div class="flex w-40 shrink-0 items-center justify-center bg-slate-950 sm:w-56">
                @if($item->photoUrl())
                    <img src="{{ $item->photoUrl() }}" alt="" class="h-full w-full object-cover">
                @else
                    <svg class="h-14 w-14 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>
                    </svg>
                @endif
            </div>
            <div class="w-px shrink-0 bg-gradient-to-b from-transparent via-indigo-500/70 to-transparent"></div>
            <div class="min-w-0 flex-1 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="truncate text-lg font-semibold text-white">{{ $item->unitLabel() !== '—' ? $item->unitLabel() : $item->recipientLabel() }}</p>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach($rows as $label => $value)
                        <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="text-slate-200">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if($item->notes)
                    <p class="mt-3 rounded-lg bg-slate-950/60 p-3 text-sm text-slate-300">{{ $item->notes }}</p>
                @endif
            </div>
        </div>

        @if($item->deliveryPhotoUrl())
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <p class="mb-2 text-xs text-slate-500">Foto de la entrega</p>
                <img src="{{ $item->deliveryPhotoUrl() }}" alt="" class="max-h-72 rounded-lg object-cover">
            </div>
        @endif

        @if($item->status === 'pending' && $canVoid)
            <form method="POST" action="{{ route('access.correspondence.void', $item) }}" x-data="{ show: false }"
                  class="porteria-form rounded-xl border border-slate-800 bg-slate-900 p-4">
                @csrf
                @method('PATCH')
                <button type="button" x-show="!show" @click="show = true" class="text-sm font-semibold text-red-400 hover:text-red-300">Anular registro</button>
                <div x-show="show" x-cloak class="space-y-3">
                    <label class="block text-xs text-slate-400">Motivo de la anulación <span class="text-amber-400">*</span>
                        <input name="void_reason" maxlength="255" class="mt-1 w-full rounded-lg border-slate-700 bg-slate-950 text-sm text-white"
                               placeholder="Registrado por error, duplicado, devuelto a la transportadora…">
                    </label>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Anular</button>
                        <button type="button" @click="show = false" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-slate-200">Cancelar</button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-access-layout>
