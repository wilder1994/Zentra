@php
    use App\Support\Auth\AssignableRoles;
    $isEdit = $managedUser !== null;
    $selectedRole = old('role', $managedUser?->getRoleNames()->first());
    $selectedClients = collect(old('client_ids', $managedUser?->clients->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $requiresClients = in_array($selectedRole, AssignableRoles::requiringClientAssignment(), true);
    $singleClient = in_array($selectedRole, AssignableRoles::requiringSingleClientAssignment(), true);
    $isSupervisor = $selectedRole === 'supervisor';
    $accent = $accent ?? 'default';
    $focusClass = match ($accent) {
        'platform' => 'focus:border-violet-500 focus:ring-violet-500/30',
        'client' => 'focus:border-teal-500 focus:ring-teal-500/30',
        default => 'focus:border-indigo-500 focus:ring-indigo-500/30',
    };
@endphp

<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-4 items-start">
        <div class="shrink-0">
            <x-ui.label>Foto de perfil</x-ui.label>
            <div class="mt-1 flex items-center gap-3">
                @if ($managedUser?->avatar_path)
                    <img src="{{ asset('storage/'.$managedUser->avatar_path) }}" alt="" class="h-14 w-14 rounded-full object-cover border border-slate-700">
                @else
                    <div class="h-14 w-14 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs text-slate-500">Sin foto</div>
                @endif
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-slate-400 file:mr-3 file:rounded file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-xs file:text-slate-200">
            </div>
            <x-ui.field-error :messages="$errors->get('avatar')" />
        </div>
    </div>

    <div>
        <x-ui.label for="name">Nombre</x-ui.label>
        <x-ui.input id="name" name="name" :value="old('name', $managedUser?->name)" required :accent="$accent" />
        <x-ui.field-error :messages="$errors->get('name')" />
    </div>

    @if ($isEdit)
        <div>
            <x-ui.label for="username">Usuario de acceso</x-ui.label>
            <x-ui.input id="username" name="username_display" :value="$managedUser?->username" disabled :accent="$accent" />
            <p class="mt-1 text-[11px] text-slate-500">No se cambia. El correo de abajo no es el login de Supervisión.</p>
        </div>
    @endif

    <div>
        <x-ui.label for="job_title">Cargo / función</x-ui.label>
        <x-ui.input id="job_title" name="job_title" :value="old('job_title', $managedUser?->job_title)" placeholder="Ej. Portería, Ronda, Supervisor de zona" :accent="$accent" />
        <p class="mt-1 text-[11px] text-slate-500">Dato de la cuenta. El cargo de RH se edita en Empleados.</p>
        <x-ui.field-error :messages="$errors->get('job_title')" />
    </div>

    <div>
        <x-ui.label for="email">Email de acceso</x-ui.label>
        <x-ui.input type="email" id="email" name="email" :value="old('email', $managedUser?->email)" :accent="$accent" />
        <x-ui.field-error :messages="$errors->get('email')" />
    </div>

    <div>
        <x-ui.label for="role">Rol</x-ui.label>
        <select name="role" id="role" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:ring-1 {{ $focusClass }}">
            @foreach ($roleOptions as $role)
                <option value="{{ $role }}" @selected($selectedRole === $role)>{{ AssignableRoles::label($role) }}</option>
            @endforeach
        </select>
        <x-ui.field-error :messages="$errors->get('role')" />
    </div>

    @if ($isEdit && $managedUser?->supervisor_code)
        <div id="supervisor-code-block" class="{{ $isSupervisor ? '' : 'hidden' }} rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-xs text-slate-500">Código de revista (permanente)</p>
            <p class="mt-1 font-mono text-lg tracking-widest text-indigo-300">{{ $managedUser->supervisor_code }}</p>
            <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-400">
                <input type="checkbox" name="regenerate_supervisor_code" value="1" class="rounded border-slate-600 bg-slate-950 text-indigo-600">
                Regenerar código (rotación deliberada)
            </label>
        </div>
    @elseif (! $isEdit)
        <p id="supervisor-code-hint" class="{{ $isSupervisor ? '' : 'hidden' }} text-xs text-slate-500">
            Al crear un supervisor de vigilancia se genera un código numérico de 6 dígitos para firmar revistas en portería.
        </p>
    @endif

    @if (! empty($showCompanySelect) && $companies)
        <div>
            <x-ui.label for="security_company_id">Empresa</x-ui.label>
            <select name="security_company_id" id="security_company_id" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30">
                <option value="">— Sin empresa —</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('security_company_id', $managedUser?->security_company_id) === (int) $company->id)>
                        {{ $company->trade_name ?: $company->legal_name }}
                    </option>
                @endforeach
            </select>
            <x-ui.field-error :messages="$errors->get('security_company_id')" />
        </div>
    @endif

    @if (isset($clients) && $clients->isNotEmpty())
        <div id="client-assignment-block" class="{{ $requiresClients ? '' : 'hidden' }}">
            <x-ui.label id="client-assignment-label">
                <span data-single class="{{ $singleClient ? '' : 'hidden' }}">Cliente asignado (vigilante)</span>
                <span data-multi class="{{ $singleClient ? 'hidden' : '' }}">Clientes asignados</span>
            </x-ui.label>
            <div class="mt-2 space-y-2 rounded-lg border border-slate-800 bg-slate-950/50 p-3 max-h-48 overflow-y-auto">
                @foreach ($clients as $client)
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input
                            type="{{ $singleClient ? 'radio' : 'checkbox' }}"
                            name="client_ids[]"
                            value="{{ $client->id }}"
                            @checked(in_array((int) $client->id, $selectedClients, true))
                            class="client-assign-input rounded border-slate-600 bg-slate-950 text-indigo-600"
                            data-input
                        >
                        <span>{{ $client->name }}</span>
                        @if ($client->relationLoaded('securityCompany') && $client->securityCompany)
                            <span class="text-xs text-slate-500">({{ $client->securityCompany->trade_name }})</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <p data-reassign-hint class="mt-1 text-[11px] text-amber-400/90 {{ $singleClient && $isEdit ? '' : 'hidden' }}">
                Al reasignar el vigilante a otro cliente conserva el mismo usuario y contraseña.
            </p>
            <x-ui.field-error :messages="$errors->get('client_ids')" />
        </div>
    @endif

    <div>
        <x-ui.label for="password">{{ $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</x-ui.label>
        <x-ui.input type="password" id="password" name="password" :required="! $isEdit" :accent="$accent" />
        <x-ui.field-error :messages="$errors->get('password')" />
    </div>

    <div>
        <x-ui.label for="password_confirmation">Confirmar contraseña</x-ui.label>
        <x-ui.input type="password" id="password_confirmation" name="password_confirmation" :accent="$accent" />
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser?->is_active ?? true)) class="rounded border-slate-600 bg-slate-950 text-indigo-600">
        Usuario activo
    </label>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const roleSelect = document.getElementById('role');
                const block = document.getElementById('client-assignment-block');
                const codeHint = document.getElementById('supervisor-code-hint');
                const codeBlock = document.getElementById('supervisor-code-block');
                const reassignHint = document.querySelector('[data-reassign-hint]');
                const singleLabel = document.querySelector('[data-single]');
                const multiLabel = document.querySelector('[data-multi]');
                if (!roleSelect) return;

                const rolesNeedingClients = @json(AssignableRoles::requiringClientAssignment());
                const singleClientRoles = @json(AssignableRoles::requiringSingleClientAssignment());

                const setInputType = (single) => {
                    document.querySelectorAll('.client-assign-input').forEach((input) => {
                        input.type = single ? 'radio' : 'checkbox';
                    });
                    singleLabel?.classList.toggle('hidden', !single);
                    multiLabel?.classList.toggle('hidden', single);
                };

                const toggle = () => {
                    const role = roleSelect.value;
                    const needsClients = rolesNeedingClients.includes(role);
                    const single = singleClientRoles.includes(role);
                    block?.classList.toggle('hidden', !needsClients);
                    codeHint?.classList.toggle('hidden', role !== 'supervisor');
                    codeBlock?.classList.toggle('hidden', role !== 'supervisor');
                    reassignHint?.classList.toggle('hidden', !(single && {{ $isEdit ? 'true' : 'false' }}));
                    if (needsClients) setInputType(single);
                };

                roleSelect.addEventListener('change', toggle);
                toggle();
            });
        </script>
    @endpush
@endonce
