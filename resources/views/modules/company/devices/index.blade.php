<x-company-layout title="Cambio de dispositivo">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="text-sm text-slate-400">Solicitudes de supervisores para usar la app desde otro celular. Vencen a los 10 minutos.</p>
        <form method="GET" class="flex gap-2">
            <select name="status" class="h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                <option value="">Todas</option>
                @foreach (\App\Enums\DeviceChangeStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="h-9 px-3 rounded-lg border border-slate-700 text-sm text-slate-200">Filtrar</button>
        </form>
    </div>

    <div class="rounded-xl border border-slate-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-900 text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="text-left px-3 py-2 font-medium">Fecha</th>
                    <th class="text-left px-3 py-2 font-medium">Supervisor</th>
                    <th class="text-left px-3 py-2 font-medium">De</th>
                    <th class="text-left px-3 py-2 font-medium">A</th>
                    <th class="text-left px-3 py-2 font-medium">Estado</th>
                    <th class="text-left px-3 py-2 font-medium">Atendió</th>
                    <th class="text-right px-3 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 bg-slate-950/40">
                @forelse ($requests as $row)
                    <tr>
                        <td class="px-3 py-2 text-slate-300 whitespace-nowrap">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 text-slate-200">
                            {{ $row->user?->name ?? '—' }}
                            <span class="block text-xs text-slate-500">{{ $row->user?->username }}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-400">{{ $row->previous_device_label ?: 'Celular anterior' }}</td>
                        <td class="px-3 py-2 text-slate-300">
                            {{ $row->device_label ?: 'Celular nuevo' }}
                            @if ($row->latitude !== null && $row->longitude !== null)
                                <a href="https://www.google.com/maps?q={{ $row->latitude }},{{ $row->longitude }}" target="_blank" rel="noopener" class="block text-xs text-indigo-400 hover:text-indigo-300">Ver ubicación</a>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="text-xs {{ match ($row->status) {
                                \App\Enums\DeviceChangeStatus::Pendiente => 'text-amber-300',
                                \App\Enums\DeviceChangeStatus::Aprobada => 'text-emerald-300',
                                \App\Enums\DeviceChangeStatus::Rechazada => 'text-red-300',
                                default => 'text-slate-500',
                            } }}">{{ $row->status->label() }}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-400">
                            {{ $row->resolver?->name ?? '—' }}
                            @if ($row->resolved_at)
                                <span class="block text-xs text-slate-500">{{ $row->resolved_at->format('d/m/Y H:i') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($row->isOpen())
                                <form method="POST" action="{{ route('company.devices.approve', $row) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="h-8 px-3 rounded-lg bg-emerald-600 text-xs font-medium text-white hover:bg-emerald-500">Aceptar</button>
                                </form>
                                <form method="POST" action="{{ route('company.devices.reject', $row) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="h-8 px-3 rounded-lg border border-slate-700 text-xs text-slate-200 hover:bg-slate-800">Rechazar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-slate-500">Aún no hay solicitudes de cambio de dispositivo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($requests->hasPages())
            <div class="px-3 py-3 border-t border-slate-800">{{ $requests->links() }}</div>
        @endif
    </div>
</x-company-layout>
