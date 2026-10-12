@php
    use App\Enums\PartyType;
@endphp

<x-company-layout :title="$client->name">
    @include('modules.company.clients.partials.nav-slots', [
        'client' => $client,
        'clientNavActive' => 'cliente',
        'vista' => 'cliente',
        'canOperate' => $canOperate,
        'canOperateClientPanel' => $canOperateClientPanel,
        'canUpdate' => $canUpdate,
        'companyContext' => $companyContext ?? ['is_quota_full' => true],
    ])

    <div class="max-w-2xl space-y-4">
        <x-ui.button variant="secondary" :href="route('company.clients.show', [$client, 'vista' => 'cliente'])" size="sm">← Ficha</x-ui.button>
        <form method="POST" action="{{ route('company.clients.update', $client) }}" class="space-y-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4">
            @csrf
            @method('PUT')

            @include('modules.company.clients.partials.service-lines', [
                'metrics' => $metrics,
                'accessDefault' => $client->has_access,
                'proDefault' => $client->has_supervision,
                'observatoryDefault' => $client->has_observatory,
                'foldersDefault' => $client->show_personnel_folders,
            ])

            <div>
                <x-ui.label for="party_type">Tipo de cliente</x-ui.label>
                <select name="party_type" id="party_type" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
                    @foreach (PartyType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('party_type', $client->party_type?->value ?? PartyType::LegalEntity->value) === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
                <x-ui.field-error :messages="$errors->get('party_type')" />
            </div>

            <div>
                <x-ui.label for="structure_type_id">Tipo de estructura</x-ui.label>
                <select name="structure_type_id" id="structure_type_id" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
                    <option value="">Seleccione…</option>
                    @foreach ($structureTypes as $id => $label)
                        <option value="{{ $id }}" @selected((string) old('structure_type_id', $client->structure_type_id) === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-500">Queda fijo para este cliente; los nodos del árbol heredan este tipo.</p>
                <x-ui.field-error :messages="$errors->get('structure_type_id')" />
            </div>

            <div>
                <x-ui.label for="name">Nombre comercial</x-ui.label>
                <x-ui.input id="name" type="text" name="name" :value="old('name', $client->name)" required />
                <x-ui.field-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-ui.label for="legal_name">Razón social / nombre legal</x-ui.label>
                <x-ui.input id="legal_name" type="text" name="legal_name" :value="old('legal_name', $client->legal_name)" />
                <x-ui.field-error :messages="$errors->get('legal_name')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-ui.label for="document_type">Tipo de documento</x-ui.label>
                    <select name="document_type" id="document_type" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
                        <option value="">Seleccione…</option>
                        @foreach ($documentTypes as $code => $label)
                            <option value="{{ $code }}" @selected(old('document_type', $client->document_type) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-ui.field-error :messages="$errors->get('document_type')" />
                </div>
                <div>
                    <x-ui.label for="tax_id">Número de documento</x-ui.label>
                    <x-ui.input id="tax_id" type="text" name="tax_id" :value="old('tax_id', $client->tax_id)" required />
                    <x-ui.field-error :messages="$errors->get('tax_id')" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-ui.label for="email">Correo de contacto</x-ui.label>
                    <x-ui.input id="email" type="email" name="email" :value="old('email', $client->email)" required />
                    <x-ui.field-error :messages="$errors->get('email')" />
                </div>
                <div>
                    <x-ui.label for="phone">Teléfono</x-ui.label>
                    <x-ui.input id="phone" type="text" name="phone" :value="old('phone', $client->phone)" />
                    <x-ui.field-error :messages="$errors->get('phone')" />
                </div>
            </div>

            <div class="border-t border-slate-800 pt-4 space-y-4">
                <p class="text-xs font-medium text-slate-400">Representante legal</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-ui.label for="representative_name">Nombre</x-ui.label>
                        <x-ui.input id="representative_name" type="text" name="representative_name" :value="old('representative_name', $client->representative_name)" />
                        <x-ui.field-error :messages="$errors->get('representative_name')" />
                    </div>
                    <div>
                        <x-ui.label for="representative_email">Correo</x-ui.label>
                        <x-ui.input id="representative_email" type="email" name="representative_email" :value="old('representative_email', $client->representative_email)" />
                        <x-ui.field-error :messages="$errors->get('representative_email')" />
                    </div>
                </div>
            </div>

            <x-ui.geo-address-fields
                :address="old('address', $client->address)"
                :city="old('city', $client->city)"
                :department="old('department', $client->department)"
                :latitude="old('latitude', $client->latitude)"
                :longitude="old('longitude', $client->longitude)"
            />

            <div>
                <x-ui.label for="service_started_at">Inicio de servicio</x-ui.label>
                <x-ui.input id="service_started_at" type="date" name="service_started_at" :value="old('service_started_at', $client->service_started_at?->format('Y-m-d'))" />
                <p class="mt-1 text-[11px] text-slate-500">Zentra no gestiona cobros de la empresa hacia este cliente.</p>
                <x-ui.field-error :messages="$errors->get('service_started_at')" />
            </div>

            <p class="text-xs text-slate-500">
                Modalidad: {{ $client->securityCompany?->package_modality?->label() ?? '—' }}
                (heredada del paquete de la empresa).
            </p>

            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active)) class="rounded border-slate-600 bg-slate-950 text-indigo-600">
                Cliente activo · desmarcar lo archiva con sus instalaciones y puestos y libera cupo
            </label>

            <x-ui.button type="submit" size="md">Guardar cambios</x-ui.button>
        </form>
    </div>
</x-company-layout>
