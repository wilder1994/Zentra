<x-company-layout :title="$employee->fullName()">
    <x-slot:actions>
        @if ($employee->is_active)
            <x-ui.button variant="secondary" :href="route('company.employees.edit', $employee)" size="sm">Editar</x-ui.button>
        @endif
        <x-ui.button variant="secondary" :href="route('company.employees.index')" size="sm">← Empleados</x-ui.button>
    </x-slot:actions>

    @php
        $fact = fn (?string $value) => filled($value) ? $value : '—';
        $day = fn ($carbon) => $carbon?->format('d/m/Y') ?? '—';
        $codeName = function (?string $name, ?string $code) {
            if (! filled($name) && ! filled($code)) {
                return '—';
            }

            return filled($code) ? $name.' · '.$code : $name;
        };
        $issuePlace = trim(implode(' · ', array_filter([
            $employee->document_issue_department,
            $employee->document_issue_city,
        ])));
        $birthPlace = trim(implode(' · ', array_filter([
            $employee->birth_department,
            $employee->birth_city,
        ])));
    @endphp

    <div class="max-w-4xl space-y-4">
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    @include('modules.company.employees.partials.avatar', [
                        'employee' => $employee,
                        'canEdit' => $employee->is_active,
                        'autosubmit' => true,
                        'action' => route('company.employees.photo.store', $employee),
                    ])
                    <div>
                        <p class="text-xs text-slate-500">{{ $employee->document_type }} {{ $employee->document_number }}</p>
                        <h2 class="text-xl font-semibold text-white">{{ $employee->fullName() }}</h2>
                        <p class="text-sm text-slate-400">
                            @if ($employee->is_active)
                                Activo
                            @else
                                Archivado{{ $employee->ceased_at ? ' · '.$employee->ceased_at->format('d/m/Y') : '' }}
                            @endif
                            · {{ $employee->jobTitle?->name ?? '—' }}
                        </p>
                    </div>
                </div>
                @if ($employee->is_active)
                    <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700" x-on:click="$dispatch('open-employee-reassign')">
                        Reasignar
                    </button>
                @endif
            </div>
            @php
                $currentPost = $currentPost ?? $employee->supervisorPosts->first();
            @endphp
            <p class="text-sm text-slate-400">
                @if ($currentPost)
                    Puesto: {{ $currentPost->name }}
                    @if ($currentPost->installation)
                        · {{ $currentPost->installation->name }}
                    @endif
                    @if ($currentPost->client)
                        · {{ $currentPost->client->name }}
                    @endif
                    @if ($currentPost->modality)
                        · {{ $currentPost->modalityLabel() }}
                    @endif
                @else
                    Sin puesto asignado.
                @endif
            </p>

            <section class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Identidad</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Nacimiento</dt>
                        <dd class="text-white">{{ $day($employee->birth_date) }}{{ $employee->age() !== null ? ' · '.$employee->age().' años' : '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Lugar de nacimiento</dt>
                        <dd class="text-white">{{ $fact($birthPlace !== '' ? $birthPlace : null) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Expedición</dt>
                        <dd class="text-white">{{ $day($employee->document_issued_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Lugar de expedición</dt>
                        <dd class="text-white">{{ $fact($issuePlace !== '' ? $issuePlace : null) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Sexo</dt>
                        <dd class="text-white">{{ $employee->sex?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Grupo sanguíneo</dt>
                        <dd class="text-white">{{ $employee->blood_group?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Nacionalidad</dt>
                        <dd class="text-white">{{ $fact($employee->nationality) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Escolaridad</dt>
                        <dd class="text-white">{{ $fact($employee->education) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Estado civil</dt>
                        <dd class="text-white">{{ $fact($employee->marital_status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Hijos</dt>
                        <dd class="text-white">{{ $employee->children_count ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Discapacidad</dt>
                        <dd class="text-white">{{ $employee->has_disability ? 'Sí' : 'No' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Contacto y residencia</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Teléfono</dt>
                        <dd class="text-white">{{ $fact($employee->phone) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Correo</dt>
                        <dd class="text-white">{{ $fact($employee->email) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Residencia</dt>
                        <dd class="text-white">{{ $fact($employee->residence_city) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Dirección</dt>
                        <dd class="text-white">{{ $fact($employee->address) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500">Emergencia</dt>
                        <dd class="text-white">{{ $fact($employee->emergency_contact) }}{{ $employee->emergency_phone ? ' · '.$employee->emergency_phone : '' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Vinculación laboral</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Cargo</dt>
                        <dd class="text-white">{{ $employee->jobTitle?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Tipo</dt>
                        <dd class="text-white">{{ $employee->collaboratorType?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Vinculación</dt>
                        <dd class="text-white">{{ $fact($employee->engagement_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Cotizante</dt>
                        <dd class="text-white">{{ $fact($employee->contributor_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Tipo de contrato</dt>
                        <dd class="text-white">{{ $fact($employee->labor_contract_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Ingreso</dt>
                        <dd class="text-white">{{ $day($employee->hired_on) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Vencimiento</dt>
                        <dd class="text-white">{{ $day($employee->labor_contract_ends_on) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Retiro</dt>
                        <dd class="text-white">{{ $day($employee->left_on ?? $employee->ceased_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Seguridad social</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">EPS</dt>
                        <dd class="text-white">{{ $codeName($employee->eps_name, $employee->eps_code) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Pensión</dt>
                        <dd class="text-white">{{ $codeName($employee->afp_name, $employee->afp_code) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Caja</dt>
                        <dd class="text-white">{{ $fact($employee->compensation_fund) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">ARL</dt>
                        <dd class="text-white">{{ $codeName($employee->arl_name, $employee->arl_risk_level) }}</dd>
                    </div>
                </dl>
            </section>

            <p class="text-xs text-slate-500">Los certificados PDF, HV y cursos irán en una carpeta documental aparte, no en esta ficha.</p>
        </div>

        @if ($employee->is_active)
            <form method="POST" action="{{ route('company.employees.archive', $employee) }}" onsubmit="return confirm('¿Archivar este empleado? Si tiene usuario, se desactivará el acceso.')">
                @csrf
                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Archivar empleado</button>
            </form>
        @else
            <form method="POST" action="{{ route('company.employees.restore', $employee) }}">
                @csrf
                <x-ui.button type="submit" variant="secondary" size="sm">Restaurar empleado</x-ui.button>
            </form>
        @endif
    </div>

    @if ($employee->is_active)
        @include('modules.company.employees.partials.reassign-modal', [
            'employee' => $employee,
            'currentPost' => $currentPost ?? null,
            'assignmentTree' => $assignmentTree ?? [],
        ])
    @endif
</x-company-layout>
