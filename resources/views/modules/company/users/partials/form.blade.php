@php
    use App\Enums\AccessGrantScope;
    use App\Support\Auth\AssignableRoles;
    use App\Support\Auth\GrantableModules;
    $isEdit = $managedUser !== null;
    $employee = $managedUser?->employee;
    $selectedRole = old('role', $managedUser?->getRoleNames()->first() ?? 'supervisor');
    $selectedJobTitle = old('job_title', $managedUser?->job_title ?: $employee?->jobTitle?->name);
    $selectedClients = collect(old('client_ids', $managedUser?->clients?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedInstallations = collect(old('installation_ids', $managedUser?->assignedInstallations?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedInstallationPermission = old('installation_permission', $managedUser?->assignedInstallations?->first()?->pivot?->installation_permission ?? 'admin');
    $selectedOrigin = old('origin', $managedUser?->admin_origin ?? 'internal');
    $companyGrantLevels = [];
    foreach (array_keys(GrantableModules::forScope(AccessGrantScope::Company)) as $module) {
        $companyGrantLevels[$module] = 'none';
    }
    $clientGrantLevels = [];
    $installationGrantLevels = [];
    foreach ($managedUser?->moduleGrants ?? [] as $grant) {
        $level = $grant->level->value;
        if ($grant->scope === AccessGrantScope::Company) {
            $companyGrantLevels[$grant->module] = $level;
        } elseif ($grant->scope === AccessGrantScope::Client) {
            $clientGrantLevels[(string) $grant->scope_id][$grant->module] = $level;
        } else {
            $installationGrantLevels[(string) $grant->scope_id][$grant->module] = $level;
        }
    }
    $formConfig = [
        'isEdit' => $isEdit,
        'searchUrl' => $isEdit ? '' : route('company.users.employee-search'),
        'previewUrl' => $isEdit ? '' : route('company.users.credentials-preview'),
        'installationsUrl' => route('company.users.installations'),
        'rolesNeedingClients' => AssignableRoles::requiringClientAssignment(),
        'singleClientRoles' => AssignableRoles::requiringSingleClientAssignment(),
        'employeeId' => (string) old('employee_id', $employee?->id ?? ''),
        'employeeName' => old('employee_name', $employee?->fullName() ?? $managedUser?->name ?? ''),
        'documentNumber' => old('document_number', $managedUser?->document_number ?: $employee?->document_number ?? ''),
        'personalEmail' => old('personal_email', $employee?->email ?? ''),
        'email' => old('email', $managedUser?->email ?? ''),
        'username' => old('username', $managedUser?->username ?? ''),
        'jobTitle' => (string) $selectedJobTitle,
        'role' => $selectedRole,
        'origin' => $selectedOrigin,
        'clientIds' => array_map('strval', $selectedClients),
        'installationIds' => array_map('strval', $selectedInstallations),
        'installationPermission' => (string) $selectedInstallationPermission,
        'generated' => filled(old('username')),
        'clients' => $clients->map(fn ($client) => [
            'id' => (string) $client->id,
            'name' => $client->name,
        ])->values()->all(),
        'companyModules' => collect(GrantableModules::forScope(AccessGrantScope::Company))
            ->map(fn ($row, $key) => ['key' => $key, 'label' => $row['label']])
            ->values()
            ->all(),
        'scopedModules' => collect(GrantableModules::forScope(AccessGrantScope::Client))
            ->map(fn ($row, $key) => ['key' => $key, 'label' => $row['label']])
            ->values()
            ->all(),
        'companyGrants' => $companyGrantLevels,
        'clientGrants' => $clientGrantLevels,
        'installationGrants' => $installationGrantLevels,
    ];
@endphp

<script type="application/json" id="company-user-form-config">@json($formConfig)</script>
<script>
    window.companyUserForm = function () {
        const cfg = JSON.parse(document.getElementById('company-user-form-config').textContent)
        return {
            isEdit: cfg.isEdit,
            employeeId: cfg.employeeId,
            employeeName: cfg.employeeName,
            documentNumber: cfg.documentNumber,
            personalEmail: cfg.personalEmail,
            email: cfg.email,
            username: cfg.username,
            jobTitle: cfg.jobTitle,
            role: cfg.role,
            origin: cfg.origin,
            password: '',
            generated: cfg.generated,
            loading: false,
            nameResults: [],
            documentResults: [],
            pickedClientIds: cfg.clientIds || [],
            selectedInstallationIds: cfg.installationIds || [],
            installationPermission: cfg.installationPermission || 'admin',
            installations: [],
            clients: cfg.clients || [],
            companyModules: cfg.companyModules || [],
            scopedModules: cfg.scopedModules || [],
            companyGrants: cfg.companyGrants || {},
            clientGrants: cfg.clientGrants || {},
            installationGrants: cfg.installationGrants || {},
            clientQuery: '',
            installationQuery: '',
            get isClientFacing() { return this.role === 'client-admin' || this.role === 'client-installation-admin' },
            get isInstallationAdmin() { return this.role === 'client-installation-admin' },
            get isExternal() { return this.isInstallationAdmin || (this.role === 'client-admin' && this.origin === 'external') },
            get isCollaborator() { return this.role === 'colaborador' },
            get usesCompanyMatrix() { return this.role === 'colaborador' || this.role === 'company-admin' },
            get usesClientMatrix() { return this.role === 'colaborador' || this.role === 'client-admin' },
            get usesInstallationMatrix() { return this.role === 'colaborador' || this.role === 'client-installation-admin' },
            get usesGrantMatrix() { return this.usesCompanyMatrix || this.usesClientMatrix || this.usesInstallationMatrix },
            get needsEmployee() { return !this.isExternal && ['company-admin', 'client-admin', 'supervisor', 'guardia', 'colaborador'].includes(this.role) },
            get needsClients() { return cfg.rolesNeedingClients.includes(this.role) },
            get singleClient() { return cfg.singleClientRoles.includes(this.role) || this.isExternal },
            get isSupervisor() { return this.role === 'supervisor' },
            get filteredClients() {
                const q = this.clientQuery.trim().toLowerCase()
                if (!q) return this.clients
                return this.clients.filter((row) => row.name.toLowerCase().includes(q))
            },
            get filteredInstallations() {
                const q = this.installationQuery.trim().toLowerCase()
                if (!q) return this.installations
                return this.installations.filter((row) => row.name.toLowerCase().includes(q))
            },
            init() {
                if (this.isInstallationAdmin) this.origin = 'external'
                if (this.role === 'company-admin') this.fillCompanyAdminGrants()
                if (this.usesClientMatrix) this.fillScopedManage(this.clientGrants, this.pickedClientIds)
                if (this.usesInstallationMatrix) this.fillScopedManage(this.installationGrants, this.selectedInstallationIds)
                this.$watch('role', () => {
                    if (this.isInstallationAdmin) this.origin = 'external'
                    if (this.role === 'company-admin') this.fillCompanyAdminGrants()
                    if (this.role === 'client-admin') this.fillScopedManage(this.clientGrants, this.pickedClientIds)
                    if (this.role === 'client-installation-admin') this.fillScopedManage(this.installationGrants, this.selectedInstallationIds)
                    this.loadInstallationsIfNeeded()
                })
                this.$watch('origin', () => this.loadInstallationsIfNeeded())
                this.$watch('pickedClientIds', (ids) => {
                    (ids || []).forEach((id) => this.ensureScopedGrants(this.clientGrants, id))
                    if (this.usesClientMatrix) this.fillScopedManage(this.clientGrants, ids || [])
                    this.loadInstallationsIfNeeded()
                })
                this.$watch('installations', (rows) => {
                    (rows || []).forEach((row) => this.ensureScopedGrants(this.installationGrants, String(row.id)))
                })
                this.$watch('selectedInstallationIds', (ids) => {
                    if (this.usesInstallationMatrix) this.fillScopedManage(this.installationGrants, ids || [])
                })
                this.loadInstallationsIfNeeded()
            },
            toggleClient(id) {
                const value = String(id)
                if (this.singleClient) {
                    this.pickedClientIds = [value]
                    return
                }
                if (this.pickedClientIds.includes(value)) {
                    this.pickedClientIds = this.pickedClientIds.filter((item) => item !== value)
                } else {
                    this.pickedClientIds = [...this.pickedClientIds, value]
                    if (this.usesClientMatrix) this.ensureScopedGrants(this.clientGrants, value)
                }
            },
            toggleInstallation(id) {
                const value = String(id)
                if (this.selectedInstallationIds.includes(value)) {
                    this.selectedInstallationIds = this.selectedInstallationIds.filter((item) => item !== value)
                } else {
                    this.selectedInstallationIds = [...this.selectedInstallationIds, value]
                    if (this.usesInstallationMatrix) this.ensureScopedGrants(this.installationGrants, value)
                }
            },
            ensureScopedGrants(map, id) {
                if (map[id]) return
                const row = {}
                ;(this.scopedModules || []).forEach((mod) => { row[mod.key] = 'none' })
                map[id] = row
            },
            fillScopedManage(map, ids) {
                const keys = (this.scopedModules || []).map((mod) => mod.key)
                const skip = new Set(['employees', 'documents'])
                ;(ids || []).forEach((id) => {
                    this.ensureScopedGrants(map, id)
                    const anySet = keys.some((key) => map[id][key] && map[id][key] !== 'none')
                    if (anySet) return
                    keys.forEach((key) => { map[id][key] = skip.has(key) ? 'none' : 'manage' })
                })
            },
            fillCompanyAdminGrants() {
                const keys = (this.companyModules || []).map((mod) => mod.key)
                const anySet = keys.some((key) => this.companyGrants[key] && this.companyGrants[key] !== 'none')
                if (anySet) return
                const next = { ...this.companyGrants }
                keys.forEach((key) => { next[key] = 'manage' })
                this.companyGrants = next
            },
            async loadInstallationsIfNeeded() {
                if (this.isCollaborator && this.pickedClientIds.length > 0) {
                    const rows = []
                    for (const clientId of this.pickedClientIds) {
                        const res = await fetch(`${cfg.installationsUrl}?client_id=${clientId}`, { headers: { Accept: 'application/json' } })
                        const data = await res.json()
                        ;(data.installations || []).forEach((row) => rows.push({ ...row, id: String(row.id) }))
                    }
                    this.installations = rows
                    return
                }
                if (! this.isInstallationAdmin || this.pickedClientIds.length !== 1) {
                    if (! this.isInstallationAdmin && ! this.isCollaborator) {
                        this.installations = []
                        this.selectedInstallationIds = []
                    }
                    return
                }
                const res = await fetch(cfg.installationsUrl + '?client_id=' + encodeURIComponent(this.pickedClientIds[0]), { headers: { Accept: 'application/json' } })
                const data = await res.json()
                this.installations = data.installations || []
                const valid = new Set(this.installations.map((row) => String(row.id)))
                this.selectedInstallationIds = this.selectedInstallationIds.filter((id) => valid.has(id))
            },
            async search(field) {
                if (this.isEdit || this.isExternal) return
                const q = (field === 'document' ? this.documentNumber : this.employeeName).trim()
                if (q.length < 2) {
                    if (field === 'document') this.documentResults = []
                    else this.nameResults = []
                    return
                }
                const res = await fetch(cfg.searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                const data = await res.json()
                const rows = data.employees || []
                if (field === 'document') this.documentResults = rows
                else this.nameResults = rows
            },
            async pick(row) {
                this.employeeId = String(row.id)
                this.employeeName = row.name
                this.documentNumber = row.document_number
                this.personalEmail = row.email || ''
                this.jobTitle = row.job_title || this.jobTitle
                this.nameResults = []
                this.documentResults = []
                await this.generate()
            },
            async generate() {
                if (this.isEdit) return
                if (this.isExternal && ! this.employeeName.trim()) { alert('Indique el nombre.'); return }
                if (! this.isExternal && ! this.employeeId) { alert('Seleccione el empleado.'); return }
                this.loading = true
                try {
                    const body = new FormData()
                    body.append('_token', document.querySelector('meta[name=csrf-token]').content)
                    if (this.isExternal) body.append('name', this.employeeName)
                    else body.append('employee_id', this.employeeId)
                    const res = await fetch(cfg.previewUrl, { method: 'POST', headers: { Accept: 'application/json' }, body })
                    const data = await res.json()
                    if (! res.ok) throw new Error(data.message || (data.errors && Object.values(data.errors)[0][0]) || 'No se pudo generar')
                    this.username = data.username
                    this.password = data.password
                    this.generated = true
                } catch (e) {
                    alert(e.message)
                } finally {
                    this.loading = false
                }
            },
        }
    }
</script>

<div class="space-y-4" x-data="companyUserForm()">
    <input type="hidden" name="employee_id" :value="isExternal ? '' : employeeId">
    <input type="hidden" name="origin" :value="isClientFacing ? (isInstallationAdmin ? 'external' : origin) : ''">
    <template x-for="id in pickedClientIds" :key="'c'+id">
        <input type="hidden" name="client_ids[]" :value="id" :disabled="!needsClients && !isCollaborator">
    </template>
    <template x-for="id in selectedInstallationIds" :key="'i'+id">
        <input type="hidden" name="installation_ids[]" :value="id" :disabled="!isInstallationAdmin && !isCollaborator">
    </template>
    <input type="hidden" name="installation_permission" :value="installationPermission" :disabled="!isInstallationAdmin">
    @if ($isEdit && ! ($managedUser?->admin_origin === 'external' || $managedUser?->hasRole('client-installation-admin')))
        <input type="hidden" name="name" value="{{ $employee?->fullName() ?: $managedUser->name }}">
    @endif

    <x-client.member-photo-picker
        name="avatar"
        error-field="avatar"
        accent="company"
        :preview-url="$managedUser?->avatar_path ? \Illuminate\Support\Facades\Storage::url($managedUser->avatar_path) : null"
    />

    <div>
        <x-ui.label for="role">Rol</x-ui.label>
        <select name="role" id="role" x-model="role" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
            @foreach ($roleOptions as $role)
                <option value="{{ $role }}" @selected($role === $selectedRole)>{{ AssignableRoles::label($role) }}</option>
            @endforeach
        </select>
        <x-ui.field-error :messages="$errors->get('role')" />
    </div>

    <div x-show="isClientFacing && !isInstallationAdmin" class="rounded-lg border border-slate-800 bg-slate-950/50 p-3 space-y-2">
        <p class="text-xs font-medium text-slate-300">Origen del administrador</p>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="radio" value="internal" x-model="origin" :disabled="isEdit" class="border-slate-600 bg-slate-950 text-indigo-600">
            Interno · empleado de la empresa
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="radio" value="external" x-model="origin" :disabled="isEdit" class="border-slate-600 bg-slate-950 text-indigo-600">
            Externo · administrador del cliente
        </label>
        <p class="text-[11px] text-slate-500">Interno puede operar varios clientes. Externo queda amarrado a uno.</p>
        <x-ui.field-error :messages="$errors->get('origin')" />
    </div>
    <p x-show="isInstallationAdmin" class="text-xs text-slate-500">Admin instalaciones es siempre externo: varias instalaciones del mismo cliente. En la ficha sale en Administrador o Apoyo (cargo · nombre). No crea usuarios ni cambia Ajustes.</p>
    <p x-show="usesCompanyMatrix" class="text-xs text-slate-500">Nada, Ver o Gestionar. Pánico y Observatorio son independientes. Supervisión también oye pánico.</p>

    <div x-show="usesCompanyMatrix" x-cloak class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/50 p-3">
        <p class="text-xs font-medium text-slate-300">Módulos de empresa</p>
        <p class="text-[11px] text-slate-500">Incluye tablero, menús y Atención de pánicos. Ver o Gestionar pánicos habilita Atender.</p>
        <template x-for="mod in companyModules" :key="mod.key">
            <div class="flex flex-wrap items-center justify-between gap-2 py-1">
                <span class="text-sm text-slate-200" x-text="mod.label"></span>
                <div class="flex gap-3 text-xs text-slate-300">
                    <label class="inline-flex items-center gap-1"><input type="radio" :name="'grants[company]['+mod.key+']'" value="none" x-model="companyGrants[mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Nada</label>
                    <label class="inline-flex items-center gap-1"><input type="radio" :name="'grants[company]['+mod.key+']'" value="view" x-model="companyGrants[mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Ver</label>
                    <label class="inline-flex items-center gap-1"><input type="radio" :name="'grants[company]['+mod.key+']'" value="manage" x-model="companyGrants[mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Gestionar</label>
                </div>
            </div>
        </template>
        <x-ui.field-error :messages="$errors->get('grants')" />
    </div>
    @if ($showMinorsNotice ?? false)
        <div x-show="isClientFacing">
            @include('partials.minors-data-notice')
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="needsEmployee || isExternal">
        <div class="relative">
            <x-ui.label for="employee-name">Nombre</x-ui.label>
            <input
                id="employee-name"
                type="text"
                :name="isExternal ? 'name' : null"
                x-model="employeeName"
                @input.debounce.250ms="search('name')"
                autocomplete="off"
                @readonly($isEdit && ! ($managedUser?->admin_origin === 'external' || $managedUser?->hasRole('client-installation-admin')))
                :readonly="needsEmployee && isEdit"
                placeholder="Nombre"
                class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white"
            >
            <div x-show="!isExternal && nameResults.length" class="absolute z-20 mt-1 w-full max-h-48 overflow-auto rounded-lg border border-slate-700 bg-slate-950">
                <template x-for="row in nameResults" :key="'n'+row.id">
                    <button type="button" class="block w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-800" @click="pick(row)" x-text="row.name + ' · ' + row.document_number"></button>
                </template>
            </div>
            <x-ui.field-error :messages="$errors->get('employee_id')" />
            <x-ui.field-error :messages="$errors->get('name')" />
        </div>
        <div class="relative">
            <x-ui.label for="employee-document">Cédula</x-ui.label>
            <input
                id="employee-document"
                type="text"
                :name="isExternal ? 'document_number' : null"
                x-model="documentNumber"
                @input.debounce.250ms="search('document')"
                autocomplete="off"
                @readonly($isEdit && ! ($managedUser?->admin_origin === 'external' || $managedUser?->hasRole('client-installation-admin')))
                :readonly="needsEmployee"
                placeholder="Cédula"
                class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white"
            >
            <div x-show="!isExternal && documentResults.length" class="absolute z-20 mt-1 w-full max-h-48 overflow-auto rounded-lg border border-slate-700 bg-slate-950">
                <template x-for="row in documentResults" :key="'d'+row.id">
                    <button type="button" class="block w-full text-left px-3 py-2 text-sm text-slate-200 hover:bg-slate-800" @click="pick(row)" x-text="row.document_number + ' · ' + row.name"></button>
                </template>
            </div>
            <x-ui.field-error :messages="$errors->get('document_number')" />
        </div>
    </div>

    <div>
        <x-ui.label for="username">Usuario de acceso</x-ui.label>
        <input type="text" id="username-display" :value="username" readonly class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white font-mono">
        @if (! $isEdit)
            <input type="hidden" name="username" :value="username">
        @endif
        <p class="mt-1 text-[11px] text-slate-500">Primer nombre + primer apellido + 4 dígitos. Es el login. No es la cédula.</p>
        <x-ui.field-error :messages="$errors->get('username')" />
    </div>

    <div>
        <x-ui.label for="job_title">Cargo / función</x-ui.label>
        <select x-show="!isExternal" :name="isExternal ? '' : 'job_title'" id="job_title" x-model="jobTitle" :disabled="isExternal" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
            <option value="">Seleccione</option>
            @foreach ($jobTitles as $title)
                <option value="{{ $title->name }}" @selected($selectedJobTitle === $title->name)>{{ $title->name }}</option>
            @endforeach
            @if ($selectedJobTitle && $jobTitles->where('name', $selectedJobTitle)->isEmpty())
                <option value="{{ $selectedJobTitle }}" selected>{{ $selectedJobTitle }}</option>
            @endif
        </select>
        <input x-show="isExternal" type="text" :name="isExternal ? 'job_title' : ''" x-model="jobTitle" :disabled="!isExternal" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white" placeholder="Cargo en el cliente">
        <p class="mt-1 text-[11px] text-slate-500" x-show="!isExternal">Catálogo de la empresa. Al elegir el empleado se precarga el cargo de la ficha.</p>
        <x-ui.field-error :messages="$errors->get('job_title')" />
    </div>

    <div x-show="needsEmployee">
        <x-ui.label for="personal-email">Email personal</x-ui.label>
        <input id="personal-email" type="email" :value="personalEmail" readonly class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
        <p class="mt-1 text-[11px] text-slate-500">Viene de la ficha del empleado. No es el login.</p>
    </div>

    <div x-show="isExternal">
        <x-ui.label for="email">Correo</x-ui.label>
        <input id="email" type="email" name="email" x-model="email" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
        <p class="mt-1 text-[11px] text-slate-500">Contacto del administrador externo. El login es el usuario de acceso.</p>
        <x-ui.field-error :messages="$errors->get('email')" />
    </div>

    @if ($isEdit && $managedUser?->supervisor_code)
        <div x-show="isSupervisor" class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-xs text-slate-500">Código de revista (permanente)</p>
            <p class="mt-1 font-mono text-lg tracking-widest text-indigo-300">{{ $managedUser->supervisor_code }}</p>
            <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-400">
                <input type="checkbox" name="regenerate_supervisor_code" value="1" class="rounded border-slate-600 bg-slate-950 text-indigo-600">
                Regenerar código (rotación deliberada)
            </label>
        </div>
    @endif

    <div x-show="needsClients || isCollaborator" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <x-ui.label>Cliente</x-ui.label>
            <div class="mt-1 overflow-hidden rounded-lg border border-slate-800 bg-slate-950/50">
                <input type="search" x-model="clientQuery" placeholder="Filtrar…" autocomplete="off"
                       class="w-full h-9 px-3 text-sm border-0 border-b border-slate-800 bg-transparent text-white placeholder:text-slate-600 focus:ring-0">
                <div class="max-h-44 overflow-auto p-2 space-y-1">
                    <template x-for="row in filteredClients" :key="'cf'+row.id">
                        <label class="flex items-center gap-2 rounded px-1.5 py-1 text-sm text-slate-300 hover:bg-slate-800/80">
                            <input type="checkbox" class="rounded border-slate-600 bg-slate-950 text-indigo-600"
                                   :checked="pickedClientIds.includes(String(row.id))"
                                   @change="toggleClient(row.id)">
                            <span x-text="row.name"></span>
                        </label>
                    </template>
                    <p x-show="filteredClients.length === 0" class="px-1.5 py-2 text-xs text-slate-500">Sin coincidencias.</p>
                </div>
            </div>
            <p x-show="role === 'guardia'" class="mt-2 text-[11px] text-slate-500">El vigilante opera portería solo si está asignado a un puesto de una instalación con puertas.</p>
            <p x-show="isExternal" class="mt-2 text-[11px] text-slate-500">El externo queda amarrado a un solo cliente.</p>
            <x-ui.field-error :messages="$errors->get('client_ids')" />
        </div>
        <div x-show="isInstallationAdmin || isCollaborator">
            <x-ui.label>Instalaciones</x-ui.label>
            <div class="mt-1 overflow-hidden rounded-lg border border-slate-800 bg-slate-950/50">
                <input type="search" x-model="installationQuery" placeholder="Filtrar…" autocomplete="off"
                       class="w-full h-9 px-3 text-sm border-0 border-b border-slate-800 bg-transparent text-white placeholder:text-slate-600 focus:ring-0">
                <div class="max-h-44 overflow-auto p-2 space-y-1">
                    <p x-show="installations.length === 0" class="px-1.5 py-2 text-xs text-slate-500">Elige un cliente para ver sus instalaciones.</p>
                    <template x-for="row in filteredInstallations" :key="'if'+row.id">
                        <label class="flex items-center gap-2 rounded px-1.5 py-1 text-sm text-slate-300 hover:bg-slate-800/80">
                            <input type="checkbox" class="rounded border-slate-600 bg-slate-950 text-indigo-600"
                                   :checked="selectedInstallationIds.includes(String(row.id))"
                                   @change="toggleInstallation(row.id)">
                            <span x-text="row.name"></span>
                        </label>
                    </template>
                    <p x-show="installations.length > 0 && filteredInstallations.length === 0" class="px-1.5 py-2 text-xs text-slate-500">Sin coincidencias.</p>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-slate-500">Puede operar varias del mismo cliente.</p>
            <div class="mt-3 space-y-2" x-show="isInstallationAdmin">
                <p class="text-xs font-medium text-slate-300">Permiso en esas instalaciones</p>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="radio" value="admin" x-model="installationPermission" class="border-slate-600 bg-slate-950 text-indigo-600">
                    Admin · opera y puede borrar nodos
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="radio" value="support" x-model="installationPermission" class="border-slate-600 bg-slate-950 text-indigo-600">
                    Apoyo · mismas instalaciones, no borra nodos ni edita la ficha
                </label>
            </div>
            <x-ui.field-error :messages="$errors->get('installation_ids')" />
            <x-ui.field-error :messages="$errors->get('installation_permission')" />
        </div>
    </div>

    <div x-show="usesClientMatrix && pickedClientIds.length" x-cloak class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/40 p-3">
        <p class="text-xs font-medium text-slate-300">Permisos por cliente</p>
        <p class="text-[11px] text-slate-500">Nada / Ver / Gestionar por módulo. Portería no se recorta aquí. Empleados y documentos quedan en Nada salvo que los marques.</p>
        <template x-for="id in pickedClientIds" :key="'cg'+id">
            <div class="space-y-1">
                <p class="text-sm text-white" x-text="(clients.find((c) => String(c.id) === String(id)) || {}).name"></p>
                <template x-for="mod in scopedModules" :key="'cm'+id+mod.key">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs text-slate-400" x-text="mod.label"></span>
                        <div class="flex gap-3 text-xs text-slate-300">
                            <label><input type="radio" :name="'grants[client]['+id+']['+mod.key+']'" value="none" x-model="clientGrants[id][mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Nada</label>
                            <label><input type="radio" :name="'grants[client]['+id+']['+mod.key+']'" value="view" x-model="clientGrants[id][mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Ver</label>
                            <label><input type="radio" :name="'grants[client]['+id+']['+mod.key+']'" value="manage" x-model="clientGrants[id][mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Gestionar</label>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <div x-show="usesInstallationMatrix && (isCollaborator ? installations.length : selectedInstallationIds.length)" x-cloak class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/40 p-3">
        <p class="text-xs font-medium text-slate-300">Permisos por instalación</p>
        <p class="text-[11px] text-slate-500">Nada / Ver / Gestionar por módulo de esas instalaciones. Portería no se recorta aquí. Empleados y documentos quedan en Nada salvo que los marques.</p>
        <template x-for="row in (isCollaborator ? installations : installations.filter((item) => selectedInstallationIds.includes(String(item.id))))" :key="'ig'+row.id">
            <div class="space-y-1">
                <p class="text-sm text-white" x-text="row.name"></p>
                <template x-for="mod in scopedModules" :key="'im'+row.id+mod.key">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs text-slate-400" x-text="mod.label"></span>
                        <div class="flex gap-3 text-xs text-slate-300">
                            <label><input type="radio" :name="'grants[installation]['+row.id+']['+mod.key+']'" value="none" x-model="installationGrants[row.id][mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Nada</label>
                            <label><input type="radio" :name="'grants[installation]['+row.id+']['+mod.key+']'" value="view" x-model="installationGrants[row.id][mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Ver</label>
                            <label><input type="radio" :name="'grants[installation]['+row.id+']['+mod.key+']'" value="manage" x-model="installationGrants[row.id][mod.key]" class="border-slate-600 bg-slate-950 text-indigo-600"> Gestionar</label>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    @if (! $isEdit)
        <div class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/50 p-3">
            <p class="text-xs text-slate-500" x-show="needsEmployee">Al elegir el empleado se genera el usuario (nombre.apellido + 4 dígitos) y una clave.</p>
            <p class="text-xs text-slate-500" x-show="isExternal">Genere el usuario con el nombre (nombre.apellido + 4 dígitos) y una clave.</p>
            <button type="button" @click="generate()" :disabled="loading || (needsEmployee && !employeeId) || (isExternal && !employeeName)" class="h-9 px-3 rounded-lg bg-slate-800 text-xs font-semibold text-white">
                <span x-text="loading ? 'Generando…' : 'Generar usuario y contraseña'"></span>
            </button>
            <div x-show="generated" class="text-sm space-y-1">
                <p class="text-slate-400">Usuario: <span class="font-mono text-white" x-text="username"></span></p>
                <p class="text-slate-400">Contraseña: <span class="font-mono text-white" x-text="password"></span></p>
            </div>
            <input type="hidden" name="password" :value="password">
            <input type="hidden" name="password_confirmation" :value="password">
            <x-ui.field-error :messages="$errors->get('password')" />
        </div>
    @else
        <div>
            <p class="mb-2 text-xs text-slate-500">Opcional. Al reasignar un vigilante conserva el mismo usuario y contraseña.</p>
            <x-ui.label for="password">Nueva contraseña (opcional)</x-ui.label>
            <x-ui.input type="password" id="password" name="password" autocomplete="new-password" />
            <x-ui.field-error :messages="$errors->get('password')" />
        </div>
        <div>
            <x-ui.label for="password_confirmation">Confirmar contraseña</x-ui.label>
            <x-ui.input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
        </div>
    @endif

    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser?->is_active ?? true)) class="rounded border-slate-600 bg-slate-950 text-indigo-600">
        Usuario activo
    </label>
</div>
