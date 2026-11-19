@php
    $hasPeople = $employees->isNotEmpty();
    $searching = $searching ?? false;
@endphp

@if (! $hasPeople && ! $searching)
    <div class="rounded-xl border border-slate-800 bg-slate-900/60 px-6 py-10 text-center">
        <p class="text-white font-medium">No hay carpetas de personal</p>
        <p class="text-sm text-slate-400 mt-1">Aparecen cuando hay empleados activos. Primero registra personal.</p>
        <div class="mt-4">
            <x-ui.button :href="route('company.employees.index')" size="sm">Ir a Empleados</x-ui.button>
        </div>
    </div>
@else
    <div class="rounded-lg border border-slate-800 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Cédula</th>
                    <th class="px-4 py-3 text-left">Nombre</th>
                    <th class="px-4 py-3 text-left">Carpetas</th>
                    <th class="px-4 py-3 text-left">Documentos</th>
                    <th class="px-4 py-3 text-left">Indexación</th>
                    <th class="px-4 py-3 text-left">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($employees as $employee)
                    <tr class="bg-slate-950/40">
                        <td class="px-4 py-3 text-slate-300">
                            <span class="text-slate-500">{{ $employee->document_type }}</span>
                            {{ $employee->document_number }}
                        </td>
                        <td class="px-4 py-3 text-white">{{ $employee->fullName() }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ (int) $employee->folders_count }}/{{ $folderTotal }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ (int) $employee->documents_count }}</td>
                        <td class="px-4 py-3">
                            @if ($employee->indexing_enabled)
                                <span class="text-xs text-emerald-300">Activo</span>
                            @else
                                <span class="text-xs text-slate-500">Desactivado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a class="inline-flex items-center gap-2 text-indigo-300 hover:text-white" href="{{ route('company.personnel-documents.folder', $employee) }}">
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="#818cf8" d="M3 7.25A1.75 1.75 0 0 1 4.75 5.5H9l1.7 1.7h8.55A1.75 1.75 0 0 1 21 8.95v9.3A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25v-11Z"/>
                                    <path fill="#a5b4fc" d="M3 9.5h18v8.75A1.75 1.75 0 0 1 19.25 20H4.75A1.75 1.75 0 0 1 3 18.25V9.5Z"/>
                                </svg>
                                Ver carpeta
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay coincidencias para esa búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>
@endif
