<x-access-layout :title="$resident->full_name">
    @include('modules.access.partials.subnav')

    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1">
            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Información</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Documento</dt>
                        <dd class="text-sm font-medium text-white">{{ $resident->displayedDocument() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Nombre</dt>
                        <dd class="text-sm font-medium text-white">{{ $resident->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Teléfono</dt>
                        <dd class="text-sm text-slate-400">{{ $resident->displayedContact($resident->phone) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Email</dt>
                        <dd class="text-sm text-slate-400">{{ $resident->displayedContact($resident->email) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Tipo</dt>
                        <dd class="text-sm text-slate-400">{{ ucfirst($resident->resident_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 uppercase">Estado</dt>
                        <dd>
                            @if($resident->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
                                    Activo
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-300 ring-1 ring-red-700">
                                    <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span>
                                    Inactivo
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <a href="{{ route('access.residents.edit', $resident) }}" class="text-indigo-400 hover:text-indigo-300 text-sm">Editar</a>
                </div>
            </div>

            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 mt-6">
                <h3 class="text-lg font-semibold text-white mb-4">Viviendas</h3>
                @forelse($resident->housingUnits as $hu)
                <div class="mb-2 p-2 bg-slate-950 rounded">
                    <p class="text-sm font-medium text-white">{{ $hu->building->name ?? '' }} - {{ $hu->unit_number }}</p>
                    <p class="text-xs text-slate-400">{{ ucfirst($hu->pivot->relationship_type) }} @if($hu->pivot->is_primary) <span class="text-indigo-400">(Principal)</span> @endif</p>
                </div>
                @empty
                <p class="text-sm text-slate-500">Sin viviendas asignadas</p>
                @endforelse
            </div>

            <div class="bg-slate-900 rounded-xl border border-slate-800 p-6 mt-6">
                <h3 class="text-lg font-semibold text-white mb-4">Vehículos</h3>
                @forelse($resident->vehicles as $v)
                <div class="mb-2 p-2 bg-slate-950 rounded flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-white">{{ $v->plate }}</p>
                        <p class="text-xs text-slate-400">{{ $v->brand }} {{ $v->model }} ({{ $v->color }})</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ ucfirst($v->type) }}</span>
                </div>
                @empty
                <p class="text-sm text-slate-500">Sin vehículos registrados</p>
                @endforelse
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800">
                    <h3 class="text-lg font-semibold text-white">Últimos Accesos</h3>
                </div>
                @if($resident->accessLogs->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800">
                        <thead class="bg-slate-950/60">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ubicación</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($resident->accessLogs as $log)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-2 text-sm text-slate-400">{{ $log->entry_time->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 text-sm text-slate-400">{{ $log->access_type }}</td>
                                <td class="px-4 py-2 text-sm text-slate-400">{{ $log->location->name ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    @if($log->status == 'active')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
                                            {{ $log->status }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-800 text-slate-400 ring-1 ring-slate-700">
                                            <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span>
                                            {{ $log->status }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-6 text-center">
                    <p class="text-sm text-slate-500">Sin registros de acceso</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-access-layout>
