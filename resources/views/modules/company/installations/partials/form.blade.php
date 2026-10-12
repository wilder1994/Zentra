@php
    $installation = $installation ?? null;
    $isEdit = $installation !== null;
    $selectedClientId = (int) old('client_id', $installation?->client_id ?? request('client_id'));
    $formClients = $clients->map(fn ($client) => [
        'id' => (int) $client->id,
        'name' => $client->name,
        'has_geo' => $client->latitude !== null && $client->longitude !== null,
    ])->values();
    $areaLat = old('latitude', $installation?->latitude);
    $areaLng = old('longitude', $installation?->longitude);
    $idescLocked = is_numeric($areaLat) && is_numeric($areaLng)
        && app(\App\Support\Geo\CaliComunaLayer::class)->locate((float) $areaLat, (float) $areaLng) !== null;
@endphp

<div
    class="space-y-4"
    x-data="{
        clientId: @js((string) $selectedClientId),
        rectorId: @js((string) old('rector_user_id', $installation?->rector_user_id ?? '')),
        sameClient: @js((bool) old('is_client_site', $installation?->is_client_site ?? false)),
        name: @js(old('name', $installation?->name ?? '')),
        siteAdmins: @js($siteAdmins),
        clients: @js($formClients),
        get selectedClient() {
            return this.clients.find((row) => String(row.id) === String(this.clientId)) || null
        },
        get clientHasGeo() {
            return Boolean(this.selectedClient?.has_geo)
        },
        onClientChange() {
            this.toggleSameClient()
            const ok = this.siteAdmins.some((row) => String(row.client_id) === String(this.clientId) && String(row.id) === String(this.rectorId))
            if (! ok) this.rectorId = ''
        },
        toggleSameClient() {
            if (this.sameClient && this.selectedClient) {
                this.name = this.selectedClient.name
            }
        }
    }"
>
    <div>
        <x-ui.label for="client_id">Cliente</x-ui.label>
        <select
            id="client_id"
            name="client_id"
            x-model="clientId"
            @change="onClientChange()"
            required
            @disabled($isEdit)
            class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 disabled:text-slate-400"
        >
            <option value="">Seleccione…</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected($selectedClientId === (int) $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
        @if ($isEdit)
            <input type="hidden" name="client_id" value="{{ $installation->client_id }}">
        @endif
        <x-ui.field-error :messages="$errors->get('client_id')" />
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <x-ui.label for="name">Nombre</x-ui.label>
            <input
                id="name"
                type="text"
                name="name"
                x-model="name"
                :readonly="sameClient"
                required
                class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white read-only:text-slate-400"
            >
            <x-ui.field-error :messages="$errors->get('name')" />
        </div>
        <label class="inline-flex items-center gap-2 text-xs text-slate-300 sm:mt-6">
            <input type="hidden" name="is_client_site" value="0">
            <input type="checkbox" name="is_client_site" value="1" x-model="sameClient" @change="toggleSameClient()" class="rounded border-slate-700 text-indigo-600">
            La instalación es el mismo cliente
        </label>
    </div>

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <x-ui.label for="code">Código</x-ui.label>
            <x-ui.input id="code" name="code" :value="old('code', $installation?->code)" placeholder="Se genera si lo dejas vacío" />
            <x-ui.field-error :messages="$errors->get('code')" />
        </div>
        <div
            x-data="installationAreaFields({
                commune: @js(old('commune', $installation?->commune ?? '')),
                city: @js(old('city', $installation?->city ?? '')),
                idescLocked: @js($idescLocked),
            })"
            @geo-place.window="applyPlace($event.detail)"
        >
            <x-ui.label for="commune" x-text="areaLabel">Comuna</x-ui.label>
            <x-ui.input id="commune" name="commune" x-model="commune" @input="refreshKind()" placeholder="Se llena con el mapa"
                        class="read-only:text-slate-400" x-bind:readonly="idescLocked" />
            <p class="mt-1 text-[11px] text-slate-500" x-text="areaHint"></p>
            <x-ui.field-error :messages="$errors->get('commune')" />
        </div>
    </div>

    @include('modules.company.installations.partials.kind-fields', ['installation' => $installation])

    <div>
        <x-ui.label for="rector_user_id">Contacto en directorio</x-ui.label>
        <select
            id="rector_user_id"
            name="rector_user_id"
            x-model="rectorId"
            class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30"
        >
            <option value="">Sin asignar</option>
            @foreach ($siteAdmins as $row)
                <option value="{{ $row['id'] }}" x-show="String(clientId) === '{{ $row['client_id'] }}'">{{ $row['label'] }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-500">
            Opcional. Quien figura como contacto del directorio. El personal (admin o apoyo) se asigna en
            <a href="{{ route('company.users.create') }}" class="text-indigo-400 hover:text-indigo-300">Usuarios</a>.
        </p>
        <x-ui.field-error :messages="$errors->get('rector_user_id')" />
    </div>

    <p x-show="sameClient" x-cloak class="text-xs text-slate-500">Se usa el nombre y la ubicación de la ficha del cliente.</p>
    <p x-show="sameClient && !clientHasGeo" x-cloak class="text-xs text-amber-400">Este cliente no tiene pin. Complétalo en la ficha o crea la instalación con el mapa.</p>
    <x-ui.field-error :messages="$errors->get('is_client_site')" />

    <div x-show="!sameClient" x-cloak>
        <x-ui.geo-address-fields
            :address="old('address', $installation?->address)"
            :city="old('city', $installation?->city)"
            :department="old('department', $installation?->department)"
            :latitude="old('latitude', $installation?->latitude)"
            :longitude="old('longitude', $installation?->longitude)"
        />
        <x-ui.field-error :messages="$errors->get('latitude')" />
    </div>

    @if ($isEdit)
        <label class="inline-flex items-center gap-2 text-xs text-slate-300">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $installation->is_active)) class="rounded border-slate-700 text-indigo-600">
            Activa (cuenta en el cupo). Desmarcar la archiva con sus puestos y libera cupo.
        </label>
    @endif
</div>
