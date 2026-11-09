@php
    $doors = $installation->locations ?? collect();
    $posts = $installation->supervisorPosts ?? collect();
    $activePosts = $posts->where('is_active', true);
    $guardsCount = $posts->sum(fn ($post) => $post->employees->where('is_active', true)->count());
    $canDoors = $canManageTree && $installation->is_active && $client->has_access;
    $canPosts = $canManageTree && $installation->is_active && ($client->has_access || $client->has_supervision);
    $canCreate = $canDoors || $canPosts;
    $showFichaLink = $showFichaLink ?? true;
    $returnTo = $returnTo ?? null;
    $maps = $maps ?? ['api_key' => config('google-maps.api_key')];
    $historyUrl = route('company.installations.history', $installation);
    $addressLine = trim(implode(' · ', array_filter([
        $installation->address,
        $installation->city,
        $installation->department,
    ])));
@endphp

<article
    class="w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-900/80"
    x-data="{
        editing: false,
        createOpen: false,
        doorOpen: false,
        postOpen: false,
        guardOpen: false,
        historyOpen: false,
        historyLoading: false,
        historyHtml: '',
        historyError: '',
        guardPostId: @js($activePosts->count() === 1 ? (string) $activePosts->first()->id : ''),
        doorEditId: null,
        postEditId: null,
        openCreate(kind) {
            this.createOpen = false;
            this.doorOpen = kind === 'door';
            this.postOpen = kind === 'post';
            this.guardOpen = kind === 'guard';
        },
        async openHistory() {
            this.historyOpen = true;
            this.historyLoading = true;
            this.historyError = '';
            this.historyHtml = '';
            try {
                const res = await fetch(@js($historyUrl), {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (! res.ok) throw new Error('No se pudo cargar el historial.');
                this.historyHtml = await res.text();
            } catch (e) {
                this.historyError = e?.message || 'Error al cargar.';
            } finally {
                this.historyLoading = false;
            }
        },
    }"
>
    <div class="grid md:grid-cols-[minmax(0,28%)_minmax(0,1fr)] md:items-stretch">
        <div class="border-b border-slate-800 md:border-b-0 md:border-r md:min-h-[22rem]">
            @include('modules.company.installations.partials.card-map', [
                'installation' => $installation,
                'maps' => $maps,
            ])
        </div>

        <div class="min-w-0 p-4 space-y-4">
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">Cliente</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-white truncate">{{ $installation->name }}</dd>
                    @if ($addressLine !== '')
                        <p class="mt-0.5 text-xs text-slate-400 truncate">{{ $addressLine }}</p>
                    @endif
                </div>
                <div class="min-w-0">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">Perfil de sitio</dt>
                    <dd class="mt-0.5 text-xs text-slate-200">{{ $installation->profileLabel() }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">Área</dt>
                    <dd class="mt-0.5 text-xs text-slate-200">{{ $installation->commune ?: '—' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-[10px] uppercase tracking-wide text-slate-500">Administrador</dt>
                    <dd class="mt-0.5 text-xs text-slate-200">{{ $installation->rector?->name ?: '—' }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($client->has_access)
                        <span class="inline-flex items-center rounded-full bg-teal-700/80 px-2.5 py-1 text-[11px] font-semibold text-white">Puerta {{ $doors->count() }}</span>
                    @endif
                    <span class="inline-flex items-center rounded-full bg-teal-700/80 px-2.5 py-1 text-[11px] font-semibold text-white">Puesto {{ $activePosts->count() }}</span>
                    <span class="inline-flex items-center rounded-full bg-teal-700/80 px-2.5 py-1 text-[11px] font-semibold text-white">Vigilantes {{ $guardsCount }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $installation->is_active ? 'bg-emerald-900/50 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                        {{ $installation->is_active ? 'Activa' : 'Archivada' }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="openHistory()" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-slate-200 hover:bg-slate-800">
                        Historial
                    </button>

                    @if ($canCreate)
                        <div class="relative" @click.outside="createOpen = false">
                            <button
                                type="button"
                                @click="createOpen = !createOpen"
                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-500"
                            >
                                Crear
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M5.25 7.5L10 12.25 14.75 7.5"/></svg>
                            </button>
                            <div
                                x-show="createOpen"
                                x-cloak
                                class="absolute right-0 z-20 mt-1 min-w-[9rem] overflow-hidden rounded-lg border border-slate-700 bg-indigo-950 shadow-xl"
                            >
                                @if ($canDoors)
                                    <button type="button" @click="openCreate('door')" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-800">Puerta</button>
                                @endif
                                @if ($canPosts)
                                    <button type="button" @click="openCreate('post')" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-800">Puesto</button>
                                    <button type="button" @click="openCreate('guard')" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-800">Vigilante</button>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if ($canManageTree)
                        <button type="button" @click="editing = !editing" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-indigo-300 hover:bg-slate-800">
                            Editar
                        </button>
                        @if ($installation->is_active)
                            <form method="POST" action="{{ route('company.clients.installations.archive', [$client, $installation]) }}" onsubmit="return confirm('¿Archivar esta instalación? Sus puestos también se archivan y libera un cupo.')">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-lg bg-rose-950/50 px-2.5 py-1.5 text-[11px] font-semibold text-rose-300 hover:bg-rose-900/50">Archivar</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('company.clients.installations.restore', [$client, $installation]) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-lg border border-emerald-800/60 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-300 hover:bg-emerald-950/40">Restaurar</button>
                            </form>
                        @endif
                    @endif

                    @if ($showFichaLink)
                        <a href="{{ route('company.installations.show', $installation) }}" class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-slate-200 hover:bg-slate-800">
                            Ver ficha
                        </a>
                    @endif
                </div>
            </div>

            @if ($canManageTree)
                <div x-show="editing" x-cloak class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    @include('modules.company.clients.partials.installation-form', [
                        'client' => $client,
                        'installation' => $installation,
                        'vista' => 'sitio',
                        'accent' => 'indigo',
                        'siteAdmins' => $siteAdmins,
                        'installationProfiles' => $installationProfiles ?? [],
                    ])
                </div>
            @endif

            @if ($client->has_access)
                <div class="space-y-2 border-t border-slate-800 pt-3">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Puertas</p>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($doors as $location)
                            <div class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg border border-slate-800 bg-slate-950/40 px-2.5 py-1.5 text-sm text-slate-200">
                                <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 21V5a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v16"/><path d="M4 21h16"/><path d="M13 12h.01"/></svg>
                                <span class="truncate">{{ $location->name }}</span>
                                <span class="font-mono text-[10px] text-slate-500">{{ $location->code }}</span>
                                @unless ($location->is_active)
                                    <span class="text-[10px] text-rose-400">archivada</span>
                                @endunless
                                @if ($canDoors)
                                    <button type="button" @click="doorEditId = doorEditId === {{ $location->id }} ? null : {{ $location->id }}" class="text-[11px] font-semibold text-emerald-400 hover:text-emerald-300">Editar</button>
                                    @if ($location->is_active)
                                        <form method="POST" action="{{ route('company.clients.locations.archive', [$client, $location]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-[11px] font-semibold text-rose-400 hover:text-rose-300">Archivar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('company.clients.locations.restore', [$client, $location]) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-[11px] font-semibold text-emerald-400 hover:text-emerald-300">Restaurar</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                            @if ($canDoors)
                                <div x-show="doorEditId === {{ $location->id }}" x-cloak class="w-full basis-full rounded-lg border border-slate-800 bg-slate-950/50 p-3">
                                    <form method="POST" action="{{ route('company.clients.locations.update', [$client, $location]) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="installation_id" value="{{ $installation->id }}">
                                        <div class="min-w-[12rem] flex-1">
                                            <label class="mb-1 block text-[11px] text-slate-500">Nombre</label>
                                            <input type="text" name="name" value="{{ $location->name }}" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                        </div>
                                        <label class="inline-flex items-center gap-1 pb-2 text-[11px] text-slate-300">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" @checked($location->is_active) class="rounded border-slate-700 text-indigo-600">
                                            Activa
                                        </label>
                                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                                        <button type="button" @click="doorEditId = null" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300">Cancelar</button>
                                    </form>
                                    <p class="mt-2 text-[10px] text-slate-600">Código {{ $location->code }} (asignado automáticamente)</p>
                                </div>
                            @endif
                        @empty
                            <p class="text-xs text-slate-500">Sin puertas.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($client->has_access || $client->has_supervision)
                <div class="space-y-3 border-t border-slate-800 pt-3">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Puestos y vigilantes</p>
                    <div class="max-h-72 space-y-3 overflow-y-auto pr-1">
                        @forelse ($posts as $post)
                            <div class="space-y-2 rounded-lg border border-slate-800/80 bg-slate-950/30 p-3">
                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                                    <div class="flex flex-wrap items-start justify-between gap-2 sm:border-r sm:border-dashed sm:border-slate-700 sm:pr-3">
                                        <div class="inline-flex items-start gap-2 text-sm text-slate-200">
                                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
                                            <span>
                                                <span class="font-medium">{{ $post->name }}</span>
                                                <span class="block text-[11px] text-slate-500">{{ $post->modalityLabel() }} · {{ $post->serviceDaysLabel() }}</span>
                                                @unless ($post->is_active)
                                                    <span class="text-[10px] text-slate-500">archivado</span>
                                                @endunless
                                            </span>
                                        </div>
                                        @if ($canPosts && $post->is_active)
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="postEditId = postEditId === {{ $post->id }} ? null : {{ $post->id }}" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300">Editar</button>
                                                <form method="POST" action="{{ route('company.clients.posts.archive', [$client, $post]) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="vista" value="sitio">
                                                    @if (! empty($returnTo))
                                                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                                        <input type="hidden" name="installation_id" value="{{ $installation->id }}">
                                                    @endif
                                                    <input type="hidden" name="observations" value="Archivo de puesto desde la tarjeta de instalación.">
                                                    <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Archivar</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                    <ul class="space-y-1.5">
                                        @forelse ($post->employees as $employee)
                                            <li class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-200">
                                                <span class="inline-flex min-w-0 items-center gap-2">
                                                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3"/><path d="M5 20c1.5-3.5 4-5 7-5s5.5 1.5 7 5"/><path d="M4 10h3M17 10h3"/></svg>
                                                    <span class="truncate">{{ $employee->fullName() }}</span>
                                                    <span class="font-mono text-[10px] text-slate-500">{{ $employee->document_number }}</span>
                                                </span>
                                                @if ($canPosts && $post->is_active)
                                                    <form method="POST" action="{{ route('company.clients.posts.employees.detach', [$client, $post, $employee]) }}">
                                                        @csrf
                                                        @if (! empty($returnTo))
                                                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                                            <input type="hidden" name="installation_id" value="{{ $installation->id }}">
                                                        @endif
                                                        <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Quitar</button>
                                                    </form>
                                                @endif
                                            </li>
                                        @empty
                                            <li class="text-xs text-slate-500">Sin vigilantes asignados.</li>
                                        @endforelse
                                    </ul>
                                </div>
                                @if ($canPosts && $post->is_active)
                                    <div x-show="postEditId === {{ $post->id }}" x-cloak class="rounded-lg border border-slate-800 bg-slate-950/50 p-3">
                                        <form method="POST" action="{{ route('company.clients.posts.update', [$client, $post]) }}" class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="vista" value="sitio">
                                            <input type="hidden" name="installation_id" value="{{ $post->installation_id }}">
                                            <input type="hidden" name="modality" value="{{ $post->modality }}">
                                            <input type="hidden" name="is_active" value="1">
                                            <input type="hidden" name="serves_holidays" value="{{ $post->serves_holidays ? '1' : '0' }}">
                                            <input type="hidden" name="reviews_per_day" value="{{ $post->reviewsPerDay() }}">
                                            @foreach ($post->serviceDayList() as $day)
                                                <input type="hidden" name="service_days[]" value="{{ $day }}">
                                            @endforeach
                                            @foreach ($post->employees as $employee)
                                                <input type="hidden" name="employee_ids[]" value="{{ $employee->id }}">
                                            @endforeach
                                            @if (! empty($returnTo))
                                                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                            @endif
                                            <div class="min-w-[12rem] flex-1">
                                                <label class="mb-1 block text-[11px] text-slate-500">Nombre del puesto</label>
                                                <input type="text" name="name" value="{{ $post->name }}" required maxlength="120" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                            </div>
                                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                                            <button type="button" @click="postEditId = null" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300">Cancelar</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">Sin puestos. Sin ellos la app no puede guardar revista.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($canDoors)
        <div x-show="doorOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl" @click.outside="doorOpen = false">
                <div class="mb-3 flex items-center justify-between">
                    <h5 class="text-sm font-semibold text-white">Agregar puerta</h5>
                    <button type="button" @click="doorOpen = false" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
                </div>
                <form method="POST" action="{{ route('company.clients.locations.store', $client) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="installation_id" value="{{ $installation->id }}">
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">Nombre</label>
                        <input type="text" name="name" required placeholder="Puerta principal" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                    </div>
                    <p class="text-[11px] text-slate-500">El código se asigna automáticamente.</p>
                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">Agregar puerta</button>
                </form>
            </div>
        </div>
    @endif

    @if ($canPosts)
        <div x-show="postOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl" @click.outside="postOpen = false">
                <div class="mb-3 flex items-center justify-between">
                    <h5 class="text-sm font-semibold text-white">Agregar puesto</h5>
                    <button type="button" @click="postOpen = false" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
                </div>
                @include('modules.company.clients.partials.post-form', [
                    'client' => $client,
                    'post' => null,
                    'installation' => $installation,
                    'vista' => 'sitio',
                    'accent' => 'indigo',
                    'postModalities' => $postModalities,
                    'namePlaceholder' => 'Puesto '.($posts->count() + 1),
                ])
            </div>
        </div>

        <div x-show="guardOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl" @click.outside="guardOpen = false">
                <div class="mb-3 flex items-center justify-between">
                    <h5 class="text-sm font-semibold text-white">Asignar vigilante</h5>
                    <button type="button" @click="guardOpen = false" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
                </div>
                @if ($activePosts->isEmpty())
                    <p class="text-sm text-slate-400">Crea un puesto antes de asignar vigilantes.</p>
                @else
                    <div class="mb-3">
                        <label class="mb-1 block text-[11px] text-slate-500">Puesto</label>
                        <select x-model="guardPostId" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                            <option value="">Seleccione…</option>
                            @foreach ($activePosts as $post)
                                <option value="{{ $post->id }}">{{ $post->name }} · {{ $post->modalityLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach ($activePosts as $post)
                        <div x-show="guardPostId === @js((string) $post->id)" x-cloak>
                            @include('modules.company.clients.partials.guard-assign-form', [
                                'client' => $client,
                                'post' => $post,
                                'vista' => 'sitio',
                            ])
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

    <div x-show="historyOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" role="dialog" aria-modal="true">
        <div class="max-h-[85vh] w-full max-w-lg overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-xl" @click.outside="historyOpen = false">
            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                <h5 class="text-sm font-semibold text-white">Historial</h5>
                <button type="button" @click="historyOpen = false" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-4">
                <div x-show="historyLoading" class="py-10 text-center text-sm text-slate-500">Cargando…</div>
                <div x-show="historyError" class="rounded-lg border border-rose-800/50 bg-rose-950/30 px-3 py-2 text-sm text-rose-200" x-text="historyError"></div>
                <div x-show="!historyLoading && !historyError" x-html="historyHtml"></div>
            </div>
        </div>
    </div>
</article>
