@php
    $canManageTree = $canManageTree ?? false;
    $installations = $installations ?? collect();
    $postModalities = $postModalities ?? [];
    $siteAdmins = $siteAdmins ?? [];
    $openCreate = $canManageTree && old('__form') === 'installation_create';
@endphp

<section
    class="space-y-4"
    x-data="{ createOpen: @js($openCreate) }"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-white">Instalaciones</h3>
            <p class="mt-1 text-xs text-slate-500">
                Cada instalación concentra sus puertas, puestos y vigilantes.
                @if (! $client->has_access)
                    Este cliente no tiene Accesos: solo puestos (sin puertas).
                @endif
            </p>
        </div>
        @if ($canManageTree)
            <button type="button" @click="createOpen = true" class="inline-flex h-9 items-center rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-500">
                + Instalación
            </button>
        @endif
    </div>

    @if ($canManageTree)
        <div
            x-show="createOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="create-installation-title"
        >
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl" @click.outside="createOpen = false">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h4 id="create-installation-title" class="text-base font-semibold text-white">Nueva instalación</h4>
                    <button type="button" @click="createOpen = false" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
                </div>
                @include('modules.company.clients.partials.installation-form', [
                    'client' => $client,
                    'vista' => 'sitio',
                    'accent' => 'indigo',
                    'siteAdmins' => $siteAdmins,
                    'installationProfiles' => $installationProfiles ?? [],
                ])
            </div>
        </div>
    @endif

    <div class="space-y-4">
        @forelse ($installations as $installation)
            @include('modules.company.clients.partials.installation-card', [
                'client' => $client,
                'installation' => $installation,
                'installations' => $installations,
                'canManageTree' => $canManageTree,
                'postModalities' => $postModalities,
                'siteAdmins' => $siteAdmins,
                'maps' => $maps ?? ['api_key' => config('google-maps.api_key')],
            ])
        @empty
            <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-6 py-10 text-center">
                <p class="text-sm text-slate-400">Aún no hay instalaciones.</p>
                @if ($canManageTree)
                    <button type="button" @click="createOpen = true" class="mt-3 inline-flex h-9 items-center rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-500">
                        Crear la primera
                    </button>
                @endif
            </div>
        @endforelse
    </div>
</section>
