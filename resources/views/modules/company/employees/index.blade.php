<x-company-layout title="Empleados">
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('company.employees.export')" size="sm" title="Empleados activos en el formato de carga (sirve para Arzen)">Exportar</x-ui.button>
        <x-ui.button variant="secondary" :href="route('company.employees.template')" size="sm">Formato</x-ui.button>
        <button
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-700 px-4 text-sm font-medium text-slate-200 hover:bg-slate-800"
            onclick="window.dispatchEvent(new CustomEvent('open-employee-import'))"
        >
            Carga masiva
        </button>
        <x-ui.button :href="route('company.employees.create')" size="sm">Nuevo empleado</x-ui.button>
    </x-slot:actions>

    <div class="space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div class="min-w-[14rem]">
                <x-ui.input name="q" :value="$search" placeholder="Nombre, documento o correo" />
            </div>
            <select name="status" class="h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                <option value="active" @selected($status === 'active')>Activos</option>
                <option value="archived" @selected($status === 'archived')>Archivados</option>
                <option value="all" @selected($status === 'all')>Todos</option>
            </select>
            <select name="per_page" class="h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white" onchange="this.form.submit()">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected(($perPage ?? 25) === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <x-ui.button type="submit" variant="secondary" size="sm">Buscar</x-ui.button>
        </form>

        <div class="rounded-lg border border-slate-800 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Identificación</th>
                        <th class="px-4 py-3 text-left">Nombre</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                        <th class="px-4 py-3 text-left">Cargo</th>
                        <th class="px-4 py-3 text-left">Ingreso</th>
                        <th class="px-4 py-3 text-left">Teléfono</th>
                        <th class="px-4 py-3 text-left">EPS</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-slate-900/40">
                            <td class="px-4 py-3 text-slate-300">{{ $employee->document_type }} {{ $employee->document_number }}</td>
                            <td class="px-4 py-3 text-white">{{ $employee->fullName() }}</td>
                            <td class="px-4 py-3">
                                @if ($employee->is_active)
                                    <span class="text-xs text-emerald-300">Activo</span>
                                @else
                                    <span class="text-xs text-slate-500">Retiro</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $employee->jobTitle?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $employee->hired_on?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $employee->phone ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $employee->eps_name ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('company.employees.show', $employee) }}" class="text-indigo-400 hover:text-indigo-300">Ficha</a>
                                @if ($employee->is_active)
                                    <a href="{{ route('company.employees.edit', $employee) }}" class="ml-3 text-indigo-400 hover:text-indigo-300">Editar</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Sin empleados en este filtro.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <p>
                @if ($employees->total() === 0)
                    0 personas
                @else
                    Mostrando {{ $employees->firstItem() }}–{{ $employees->lastItem() }} de {{ $employees->total() }}
                @endif
            </p>
            <div>{{ $employees->links() }}</div>
        </div>
    </div>
</x-company-layout>
