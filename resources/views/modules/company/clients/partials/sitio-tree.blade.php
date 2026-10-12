@php
    $canManageTree = $canManageTree ?? false;
    $installations = $installations ?? collect();
    $postModalities = $postModalities ?? [];
    $siteAdmins = $siteAdmins ?? [];
@endphp

<section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4">
    <div>
        <h3 class="text-sm font-semibold text-white">Instalaciones y puestos</h3>
        <p class="mt-1 text-xs text-slate-500">
            Sitio, modalidad y vigilantes. Una instalación tiene varios puestos; un puesto, varios vigilantes. No es una puerta.
        </p>
    </div>

    @if ($canManageTree)
        @include('modules.company.clients.partials.installation-form', ['client' => $client, 'vista' => 'sitio', 'accent' => 'indigo', 'siteAdmins' => $siteAdmins])
    @endif

    <div class="space-y-3">
        @forelse ($installations as $installation)
            <article class="rounded-lg border border-slate-800 bg-slate-950/40 p-3 space-y-3" x-data="{ editing: false }">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-medium text-white">{{ $installation->name }}</p>
                        <a href="{{ route('company.installations.show', $installation) }}" class="text-xs text-indigo-400 hover:text-indigo-300">Ver ficha</a>
                        @if ($installation->is_client_site)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-900/40 text-indigo-300">Mismo cliente</span>
                        @endif
                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $installation->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                            {{ $installation->is_active ? 'Activa' : 'Archivada' }}
                        </span>
                        @php
                            $activePosts = $installation->supervisorPosts->where('is_active', true)->count();
                            $postsLimit = \App\Support\Catalog\PostQuota::postsLimit($installation);
                        @endphp
                        <span class="text-xs text-slate-500" title="Puestos activos / permitidos por tu plan">{{ $activePosts }}/{{ $postsLimit }} puestos</span>
                        @if ($installation->address || $installation->city)
                            <span class="text-xs text-slate-500">{{ $installation->address }}{{ $installation->city ? ' · '.$installation->city : '' }}</span>
                        @endif
                    </div>
                    @if ($canManageTree)
                        <div class="flex items-center gap-2">
                            <button type="button" @click="editing = !editing" class="text-xs text-indigo-300 hover:text-indigo-200">Editar</button>
                            @if ($installation->is_active)
                                <form method="POST" action="{{ route('company.clients.installations.archive', [$client, $installation]) }}" onsubmit="return confirm('¿Archivar esta instalación? Sus puestos también se archivan y libera un cupo.')">
                                    @csrf
                                    <button type="submit" class="text-xs text-amber-300 hover:text-amber-200">Archivar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('company.clients.installations.restore', [$client, $installation]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-emerald-300 hover:text-emerald-200">Restaurar</button>
                                </form>
                                <form method="POST" action="{{ route('company.clients.installations.destroy', [$client, $installation]) }}" onsubmit="return confirm('¿Dar de baja esta instalación? No se puede deshacer.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Dar de baja</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($canManageTree)
                    <div x-show="editing" x-cloak>
                        @include('modules.company.clients.partials.installation-form', [
                            'client' => $client,
                            'installation' => $installation,
                            'vista' => 'sitio',
                            'accent' => 'indigo',
                            'siteAdmins' => $siteAdmins,
                        ])
                    </div>
                @endif

                @if ($client->has_access || $client->has_supervision)
                    @include('modules.company.clients.partials.posts-block', [
                        'client' => $client,
                        'installation' => $installation,
                        'installations' => $installations,
                        'vista' => 'sitio',
                        'accent' => 'indigo',
                        'canManageTree' => $canManageTree && $installation->is_active,
                        'postModalities' => $postModalities,
                    ])
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-500">Aún no hay instalaciones. Créela aquí y luego sus puestos.</p>
        @endforelse
    </div>
</section>
