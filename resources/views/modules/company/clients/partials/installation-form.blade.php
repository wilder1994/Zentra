@php
    $installation = $installation ?? null;
    $vista = $vista ?? 'sitio';
    $accent = $accent ?? 'indigo';
    $checkboxClass = $accent === 'amber'
        ? 'rounded border-slate-700 text-amber-500'
        : 'rounded border-slate-700 text-indigo-600';
    $buttonClass = $accent === 'amber'
        ? 'rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-500'
        : 'rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500';
    $isEdit = $installation !== null;
    $action = $isEdit
        ? route('company.clients.installations.update', [$client, $installation])
        : route('company.clients.installations.store', $client);
    $sameClient = (bool) old('is_client_site', $installation?->is_client_site ?? false);
    $clientHasGeo = $client->latitude !== null && $client->longitude !== null;
    $siteAdmins = $siteAdmins ?? [];
    $formConfig = [
        'sameClient' => $sameClient,
        'name' => old('name', $installation?->name ?? ''),
        'clientName' => $client->name,
        'clientHasGeo' => $clientHasGeo,
    ];
    $areaLat = old('latitude', $installation?->latitude);
    $areaLng = old('longitude', $installation?->longitude);
    $idescLocked = is_numeric($areaLat) && is_numeric($areaLng)
        && app(\App\Support\Geo\CaliComunaLayer::class)->locate((float) $areaLat, (float) $areaLng) !== null;
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/40 p-3"
    x-data="installationSiteForm(@js($formConfig))"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="vista" value="{{ $vista }}">

    <div class="grid sm:grid-cols-2 gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-400 mb-1">{{ $isEdit ? 'Instalación' : 'Nueva instalación' }}</label>
            <input
                type="text"
                name="name"
                x-model="name"
                :readonly="sameClient"
                required
                placeholder="{{ $client->name }}"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white read-only:text-slate-400"
            >
            <x-ui.field-error :messages="$errors->get('name')" />
        </div>
        <label class="inline-flex items-center gap-2 text-xs text-slate-300 pb-2">
            <input type="hidden" name="is_client_site" value="0">
            <input
                type="checkbox"
                name="is_client_site"
                value="1"
                x-model="sameClient"
                @change="toggleSameClient()"
                class="{{ $checkboxClass }}"
            >
            La instalación es el mismo cliente
        </label>
    </div>

    <p x-show="sameClient" x-cloak class="text-xs text-slate-500">
        Se usa el nombre y la ubicación de la ficha del cliente.
    </p>
    <p x-show="sameClient && !clientHasGeo" x-cloak class="text-xs text-amber-400">
        Este cliente no tiene pin. Complétalo en la ficha o crea la instalación con el mapa.
    </p>
    <x-ui.field-error :messages="$errors->get('is_client_site')" />

    <div class="grid sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs text-slate-400 mb-1">Código</label>
            <input type="text" name="code" value="{{ old('code', $installation?->code) }}" placeholder="Se genera solo" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
        </div>
        <div
            x-data="installationAreaFields({
                commune: @js(old('commune', $installation?->commune ?? '')),
                city: @js(old('city', $installation?->city ?? $client->city ?? '')),
                idescLocked: @js($idescLocked),
            })"
            @geo-place.window="applyPlace($event.detail)"
        >
            <label class="block text-xs text-slate-400 mb-1" x-text="areaLabel">Comuna</label>
            <input type="text" name="commune" x-model="commune" @input="refreshKind()" placeholder="Se llena con el mapa"
                   x-bind:readonly="idescLocked" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white read-only:text-slate-400">
            <p class="mt-1 text-[11px] text-slate-500" x-text="areaHint"></p>
        </div>
    </div>
    @include('modules.company.installations.partials.kind-fields', ['installation' => $installation])
    <div>
        <label class="block text-xs text-slate-400 mb-1">Contacto en directorio</label>
        <select name="rector_user_id" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
            <option value="">Sin asignar</option>
            @foreach ($siteAdmins as $row)
                <option value="{{ $row['id'] }}" @selected((int) old('rector_user_id', $installation?->rector_user_id) === (int) $row['id'])>{{ $row['label'] }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-500">Opcional. El personal (admin o apoyo) se asigna en Usuarios.</p>
    </div>

    <div x-show="!sameClient" x-cloak>
        <x-ui.geo-address-fields
            :address="old('address', $installation?->address)"
            :city="old('city', $installation?->city)"
            :department="old('department', $installation?->department)"
            :latitude="old('latitude', $installation?->latitude)"
            :longitude="old('longitude', $installation?->longitude)"
            :accent="$accent === 'amber' ? 'default' : 'default'"
        />
        <x-ui.field-error :messages="$errors->get('latitude')" />
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if ($isEdit)
            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $installation->is_active)) class="{{ $checkboxClass }}">
                Activa · desmarcar la archiva con sus puestos y libera cupo
            </label>
        @endif
        <button type="submit" class="{{ $buttonClass }}">
            {{ $isEdit ? 'Guardar' : 'Crear instalación' }}
        </button>
    </div>
</form>
