@php
    use App\Support\Auth\AssignableRoles;
    $isEdit = $managedUser !== null;
    $selectedRole = old('role', $managedUser?->getRoleNames()->first() ?? 'client-admin');
    $selectedInstallations = collect(old('installation_ids', $managedUser?->assignedInstallations?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedInstallationPermission = old('installation_permission', $managedUser?->assignedInstallations?->first()?->pivot?->installation_permission ?? 'admin');
@endphp

<div class="space-y-4" x-data="{ role: @js($selectedRole) }">
    @if ($errors->any())
        <div class="rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-sm text-red-200">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <p class="text-xs text-slate-500">Solo se dan de alta administradores <strong class="text-slate-300">externos</strong> de este cliente. Los internos (empleados) se crean en el panel empresa.</p>

    <div>
        <x-ui.label for="role">Línea</x-ui.label>
        <select name="role" id="role" x-model="role" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30">
            @foreach ($roleOptions as $role)
                <option value="{{ $role }}">{{ AssignableRoles::label($role) }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-500" x-show="role === 'client-admin'">Administra todo el panel de este cliente.</p>
        <p class="mt-1 text-[11px] text-slate-500" x-show="role === 'client-installation-admin'">Opera las instalaciones que elijas. Puede ver Ajustes, no modificarlos, y no crea usuarios.</p>
        <x-ui.field-error :messages="$errors->get('role')" />
    </div>
    @if ($showMinorsNotice ?? false)
        @include('partials.minors-data-notice')
    @endif

    <div>
        <x-ui.label for="name">Nombre</x-ui.label>
        <x-ui.input id="name" name="name" :value="old('name', $managedUser?->name)" required accent="client" />
        <x-ui.field-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-ui.label for="document_number">Cédula</x-ui.label>
        <x-ui.input id="document_number" name="document_number" :value="old('document_number', $managedUser?->document_number)" required accent="client" />
        <x-ui.field-error :messages="$errors->get('document_number')" />
    </div>

    <div>
        <x-ui.label for="job_title">Cargo</x-ui.label>
        <x-ui.input id="job_title" name="job_title" :value="old('job_title', $managedUser?->job_title)" required accent="client" placeholder="Administrador, coordinador…" />
        <x-ui.field-error :messages="$errors->get('job_title')" />
    </div>

    <div>
        <x-ui.label for="email">Correo</x-ui.label>
        <x-ui.input type="email" id="email" name="email" :value="old('email', $managedUser?->email)" required accent="client" />
        <x-ui.field-error :messages="$errors->get('email')" />
    </div>

    @if ($isEdit)
        <div>
            <x-ui.label>Usuario de acceso</x-ui.label>
            <x-ui.input :value="$managedUser?->username" disabled accent="client" />
            <p class="mt-1 text-[11px] text-slate-500">No se cambia. El correo no es el login.</p>
        </div>
    @endif

    <div x-show="role === 'client-installation-admin'" class="rounded-lg border border-slate-800 bg-slate-950/50 p-3 space-y-2">
        <x-ui.label>Instalaciones</x-ui.label>
        @forelse ($installations as $installation)
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input
                    type="checkbox"
                    name="installation_ids[]"
                    value="{{ $installation->id }}"
                    @checked(in_array((int) $installation->id, $selectedInstallations, true))
                    class="rounded border-slate-600 bg-slate-950 text-teal-600"
                >
                <span>{{ $installation->name }}</span>
            </label>
        @empty
            <p class="text-xs text-slate-500">Este cliente no tiene instalaciones activas.</p>
        @endforelse
        <x-ui.field-error :messages="$errors->get('installation_ids')" />
        <div class="pt-2 space-y-2">
            <p class="text-xs font-medium text-slate-300">Permiso en esas instalaciones</p>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="radio" name="installation_permission" value="admin" @checked($selectedInstallationPermission === 'admin') class="border-slate-600 bg-slate-950 text-teal-600">
                Admin · opera y puede borrar nodos
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="radio" name="installation_permission" value="support" @checked($selectedInstallationPermission === 'support') class="border-slate-600 bg-slate-950 text-teal-600">
                Apoyo · mismas instalaciones, no borra nodos
            </label>
            <x-ui.field-error :messages="$errors->get('installation_permission')" />
        </div>
    </div>

    <div>
        <x-ui.label for="password">{{ $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</x-ui.label>
        <x-ui.input type="password" id="password" name="password" :required="! $isEdit" accent="client" />
        <x-ui.field-error :messages="$errors->get('password')" />
    </div>
    <div>
        <x-ui.label for="password_confirmation">Confirmar contraseña</x-ui.label>
        <x-ui.input type="password" id="password_confirmation" name="password_confirmation" accent="client" />
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser?->is_active ?? true)) class="rounded border-slate-600 bg-slate-950 text-teal-600">
        Usuario activo
    </label>
</div>
