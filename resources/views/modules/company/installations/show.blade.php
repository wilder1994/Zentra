<x-company-layout :title="$installation->name">
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('company.installations.index')" size="sm">← Listado</x-ui.button>
        @can('update', $installation->client)
            <x-ui.button variant="secondary" :href="route('company.clients.show', [$installation->client, 'vista' => 'sitio'])" size="sm">Ficha cliente</x-ui.button>
        @endcan
    </x-slot:actions>

    <div class="w-full">
        @include('modules.company.clients.partials.installation-card', [
            'client' => $client,
            'installation' => $installation,
            'installations' => collect([$installation]),
            'canManageTree' => $canManageTree,
            'postModalities' => $postModalities,
            'siteAdmins' => $siteAdmins,
            'installationProfiles' => $installationProfiles,
            'maps' => $maps,
            'showFichaLink' => false,
        ])
    </div>
</x-company-layout>
