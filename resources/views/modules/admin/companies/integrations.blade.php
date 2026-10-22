@php
    use App\Models\ApiIntegrationKey;
    use App\Support\Api\ApiAbilities;

    $plain = session('api_key_plain');
    $openCreate = session('integration_modal') === 'create' || old('user_mode') !== null;
    $baseUrl = rtrim((string) config('app.url'), '/');
    $levelOptions = [
        ApiAbilities::LEVEL_NONE => 'Sin acceso',
        ApiAbilities::LEVEL_READ => 'Lectura',
        ApiAbilities::LEVEL_WRITE => 'Lectura y escritura',
        ApiAbilities::LEVEL_CUSTOM => 'Personalizado',
    ];
    $statusTone = [
        ApiIntegrationKey::STATUS_ACTIVE => 'border-emerald-700/60 bg-emerald-950/40 text-emerald-300',
        ApiIntegrationKey::STATUS_EXPIRED => 'border-amber-700/60 bg-amber-950/40 text-amber-300',
        ApiIntegrationKey::STATUS_USER_INACTIVE => 'border-amber-700/60 bg-amber-950/40 text-amber-300',
        ApiIntegrationKey::STATUS_REVOKED => 'border-slate-700 bg-slate-900 text-slate-400',
    ];
    $active = $keys->filter(fn (ApiIntegrationKey $k): bool => $k->status() === ApiIntegrationKey::STATUS_ACTIVE)->count();
    $lastUsed = $keys->map(fn (ApiIntegrationKey $k) => $k->token?->last_used_at)->filter()->max();
    $input = 'mt-1 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-slate-100 focus:border-violet-500 focus:outline-none';
    $defaultLevels = ['observatory' => ApiAbilities::LEVEL_READ];
@endphp

<x-admin-layout :title="'Empresa: '.$company->displayName()">
    @include('modules.admin.companies.partials.nav-slots', [
        'company' => $company,
        'companyNavActive' => 'integrations',
    ])

    <div class="w-full space-y-4"
         x-data="{ createOpen: @js($openCreate), helpOpen: false, manualOpen: null, plainOpen: @js($plain !== null), copied: false,
                   copy(text) { navigator.clipboard.writeText(text).then(() => { this.copied = true; setTimeout(() => this.copied = false, 1800) }) } }"
         @keydown.escape.window="helpOpen = false; createOpen = false; manualOpen = null">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-white">
                    API keys de la empresa
                    <button type="button" @click="helpOpen = true"
                            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-600 text-[11px] font-semibold text-slate-400 hover:border-violet-400 hover:text-violet-300"
                            aria-label="Cómo funcionan las integraciones" title="Cómo funciona">?</button>
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">Cada key es de un usuario de {{ $company->displayName() }} y solo abre los módulos que le marques.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($manuals as $manualType => $manualTitle)
                    <button type="button" @click="manualOpen = @js($manualType)"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-700 px-3 text-sm text-slate-200 hover:bg-slate-800">
                        <svg class="h-4 w-4 text-violet-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2V5z" />
                            <path d="M8 7h6M8 11h6" />
                        </svg>
                        {{ $manualTitle }}
                    </button>
                @endforeach
                @if ($canManage)
                    <x-ui.button variant="platform" size="sm" @click="createOpen = true">+ Nueva API key</x-ui.button>
                @endif
            </div>
        </div>

        @if ($errors->has('key'))
            <div class="rounded-lg border border-red-800/60 bg-red-950/40 px-4 py-2.5 text-sm text-red-200">{{ $errors->first('key') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Keys activas</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $active }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Revocadas o vencidas</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $keys->count() - $active }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Último uso</p>
                <p class="mt-1 text-lg font-semibold text-white">{{ $lastUsed ? $lastUsed->diffForHumans() : 'Nunca' }}</p>
            </div>
        </div>

        <section class="rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden">
            @if ($keys->isEmpty())
                <div class="px-4 py-10 text-center">
                    <p class="text-sm text-slate-300">Esta empresa aún no tiene API keys.</p>
                    <p class="mt-1 text-xs text-slate-500">Crea una cuando otro sistema (p. ej. la Secretaría de Educación) necesite leer o enviar datos.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-800 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-medium">Key</th>
                                <th class="px-4 py-2.5 text-left font-medium">Usuario</th>
                                <th class="px-4 py-2.5 text-left font-medium">Permisos</th>
                                <th class="px-4 py-2.5 text-left font-medium">Estado</th>
                                <th class="px-4 py-2.5 text-left font-medium">Último uso</th>
                                <th class="px-4 py-2.5 text-left font-medium">Fechas</th>
                                @if ($canManage)
                                    <th class="px-4 py-2.5 text-right font-medium">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($keys as $key)
                                @php $status = $key->status(); @endphp
                                <tr class="align-top">
                                    <td class="px-4 py-3 min-w-[9rem]">
                                        <p class="font-medium text-slate-100">{{ $key->name }}</p>
                                        <p class="font-mono text-[11px] text-slate-500">{{ $key->token_hint ? '••••'.$key->token_hint : '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-slate-200">{{ $key->user?->name ?? '—' }}</p>
                                        <p class="max-w-[13rem] truncate text-[11px] text-slate-500" title="{{ $key->user?->email }}">{{ $roleLabels[$key->user?->getRoleNames()->first()] ?? $key->user?->getRoleNames()->first() }} · {{ $key->user?->email }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-0.5">
                                            @foreach ($modules as $moduleKey => $module)
                                                @php $level = ApiAbilities::levelFor($moduleKey, $key->abilities ?? []); @endphp
                                                @continue($level === ApiAbilities::LEVEL_NONE)
                                                <p class="text-xs text-slate-300">
                                                    <span class="text-slate-500">{{ $module['label'] }}:</span>
                                                    {{ ApiAbilities::levelLabel($level) }}
                                                </p>
                                                @if ($level === ApiAbilities::LEVEL_CUSTOM)
                                                    <p class="text-[11px] text-slate-500">
                                                        {{ collect($module['endpoints'])->only($key->abilities ?? [])->pluck('label')->implode(' · ') }}
                                                    </p>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $statusTone[$status] }}">{{ $key->statusLabel() }}</span>
                                        @if ($key->revoked_at)
                                            <p class="mt-1 text-[11px] text-slate-500">{{ $key->revoked_at->format('d/m/Y H:i') }}{{ $key->revokedBy ? ' · '.$key->revokedBy->name : '' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $key->token?->last_used_at?->diffForHumans() ?? 'Nunca' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                                        Creada {{ $key->created_at?->format('d/m/Y') }}
                                        @if ($key->regenerated_at)
                                            <span class="block text-[11px] text-slate-500">Regenerada {{ $key->regenerated_at->format('d/m/Y') }}</span>
                                        @endif
                                        <span class="block text-[11px] text-slate-500">{{ $key->expires_at ? 'Vence '.$key->expires_at->format('d/m/Y') : 'Sin vencimiento' }}</span>
                                    </td>
                                    @if ($canManage)
                                        <td class="px-4 py-3">
                                            @unless ($key->isRevoked())
                                                <div class="flex justify-end gap-2">
                                                    @if ($status !== ApiIntegrationKey::STATUS_EXPIRED)
                                                    <form method="POST" action="{{ route('admin.companies.integrations.regenerate', [$company, $key]) }}"
                                                          onsubmit="return confirm('¿Regenerar «{{ $key->name }}»? La key actual deja de funcionar y el otro sistema debe usar la nueva.')">
                                                        @csrf
                                                        <button type="submit" class="h-8 rounded-lg border border-slate-700 px-3 text-xs font-medium text-slate-200 hover:bg-slate-800">Regenerar</button>
                                                    </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.companies.integrations.revoke', [$company, $key]) }}"
                                                          onsubmit="return confirm('¿Revocar «{{ $key->name }}»? Deja de funcionar de inmediato y no se puede reactivar.')">
                                                        @csrf
                                                        <button type="submit" class="h-8 rounded-lg border border-red-800/70 px-3 text-xs font-medium text-red-300 hover:bg-red-950/40">Revocar</button>
                                                    </form>
                                                </div>
                                            @endunless
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Key recién creada o regenerada: se muestra una sola vez --}}
        @if ($plain)
            <div x-show="plainOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                <div class="w-full max-w-xl rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    <h3 class="text-base font-semibold text-white">Key de «{{ $plain['name'] }}»</h3>
                    <p class="mt-1 text-xs text-amber-300">Cópiala ahora. Por seguridad no se vuelve a mostrar; si se pierde, regenérala.</p>
                    <div class="mt-3 flex gap-2">
                        <input type="text" readonly value="{{ $plain['token'] }}" id="api-key-plain"
                               class="h-10 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 font-mono text-xs text-slate-100" @focus="$el.select()">
                        <button type="button" class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500"
                                @click="copy(@js($plain['token']))" x-text="copied ? 'Copiada' : 'Copiar'">Copiar</button>
                    </div>
                    <p class="mt-4 text-[11px] uppercase tracking-wide text-slate-500">Probar la key</p>
                    <pre class="mt-1 overflow-x-auto rounded-lg border border-slate-800 bg-slate-950 p-3 text-[11px] leading-relaxed text-slate-300">curl {{ $baseUrl }}/api/auth/me \
  -H "Authorization: Bearer {{ $plain['token'] }}" \
  -H "Accept: application/json"</pre>
                    <div class="mt-4 flex justify-end">
                        <button type="button" class="h-9 rounded-lg border border-slate-700 px-4 text-sm text-slate-200 hover:bg-slate-800" @click="plainOpen = false">Ya la guardé</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Nueva API key --}}
        @if ($canManage)
            <div x-show="createOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="createOpen = false">
                <form method="POST" action="{{ route('admin.companies.integrations.store', $company) }}"
                      class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl border border-slate-700 bg-slate-900 shadow-2xl"
                      x-data="{
                          userMode: @js(old('user_mode', 'existing')),
                          levels: @js(array_merge(collect($modules)->map(fn () => 'none')->all(), $defaultLevels, old('levels', []))),
                          clientQuery: '',
                      }">
                    @csrf
                    <div class="border-b border-slate-800 px-5 py-4">
                        <h3 class="text-base font-semibold text-white">Nueva API key</h3>
                        <p class="mt-0.5 text-xs text-slate-500">La key actúa como el usuario elegido y solo entra a los endpoints que marques.</p>
                    </div>

                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                        @if ($errors->any() && $openCreate)
                            <div class="rounded-lg border border-red-800/60 bg-red-950/40 px-3 py-2 text-xs text-red-200">
                                @foreach ($errors->all() as $message)
                                    <p>{{ $message }}</p>
                                @endforeach
                            </div>
                        @endif

                        <label class="block text-xs text-slate-400">Nombre de la key
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Secretaría de Educación — Observatorio" class="{{ $input }}">
                        </label>

                        <div>
                            <p class="text-xs text-slate-400">Usuario de la key</p>
                            <div class="mt-1.5 inline-flex rounded-lg border border-slate-700 p-0.5 text-xs">
                                <label class="cursor-pointer rounded-md px-3 py-1.5" :class="userMode === 'existing' ? 'bg-violet-600 text-white' : 'text-slate-300'">
                                    <input type="radio" name="user_mode" value="existing" x-model="userMode" class="sr-only"> Usuario existente
                                </label>
                                <label class="cursor-pointer rounded-md px-3 py-1.5" :class="userMode === 'new' ? 'bg-violet-600 text-white' : 'text-slate-300'">
                                    <input type="radio" name="user_mode" value="new" x-model="userMode" class="sr-only"> Usuario externo nuevo
                                </label>
                            </div>

                            <div x-show="userMode === 'existing'" class="mt-2 text-xs text-slate-400">
                                <x-ui.search-select name="user_id" placeholder="Escribe el nombre o correo…" :options="$users" />
                                <p class="mt-1 text-[11px] text-slate-500">Ve solo lo que ese usuario ve en el panel (sus clientes e instalaciones).</p>
                            </div>

                            <div x-show="userMode === 'new'" x-cloak class="mt-2 space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="block text-xs text-slate-400">Nombre
                                        <input type="text" name="new_user_name" value="{{ old('new_user_name') }}" maxlength="120" placeholder="Integración Secretaría" class="{{ $input }}">
                                    </label>
                                    <label class="block text-xs text-slate-400">Correo de contacto (opcional)
                                        <input type="email" name="new_user_email" value="{{ old('new_user_email') }}" maxlength="190" placeholder="sistemas@entidad.gov.co" class="{{ $input }}">
                                    </label>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">Clientes que puede ver <span class="text-slate-500">(ninguno marcado = todos los de la empresa)</span></p>
                                    <input type="search" x-model="clientQuery" placeholder="Buscar cliente…" class="{{ $input }}">
                                    <div class="mt-2 max-h-36 space-y-1 overflow-y-auto rounded-lg border border-slate-800 p-2">
                                        @foreach ($clients as $client)
                                            <label class="flex items-center gap-2 text-xs text-slate-300"
                                                   x-show="clientQuery === '' || @js(mb_strtolower($client->name)).includes(clientQuery.toLowerCase())">
                                                <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" @checked(in_array($client->id, old('client_ids', [])))
                                                       class="rounded border-slate-600 bg-slate-950 text-violet-500">
                                                {{ $client->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-500">Se crea como colaborador con Observatorio en «Ver», con clave aleatoria: no entra al panel, solo usa la API.</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400">Permisos por módulo</p>
                            <div class="mt-2 space-y-3">
                                @foreach ($modules as $moduleKey => $module)
                                    <div class="rounded-lg border border-slate-800 p-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-medium text-slate-100">{{ $module['label'] }}</p>
                                                <p class="text-[11px] text-slate-500">{{ $module['hint'] }}</p>
                                            </div>
                                            <select name="levels[{{ $moduleKey }}]" x-model="levels['{{ $moduleKey }}']"
                                                    class="h-8 rounded-lg border border-slate-700 bg-slate-950 px-2 text-xs text-slate-100">
                                                @foreach ($levelOptions as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mt-2 space-y-1" x-show="levels['{{ $moduleKey }}'] !== 'none'" x-cloak>
                                            @foreach ($module['endpoints'] as $ability => $endpoint)
                                                <label class="flex items-start gap-2 text-xs"
                                                       :class="levels['{{ $moduleKey }}'] === 'custom' ? 'text-slate-200' : 'text-slate-500'">
                                                    <input type="checkbox" name="custom[]" value="{{ $ability }}"
                                                           @checked(in_array($ability, old('custom', [])))
                                                           x-show="levels['{{ $moduleKey }}'] === 'custom'"
                                                           :disabled="levels['{{ $moduleKey }}'] !== 'custom'"
                                                           class="mt-0.5 rounded border-slate-600 bg-slate-950 text-violet-500">
                                                    <span x-show="levels['{{ $moduleKey }}'] !== 'custom'"
                                                          x-text="levels['{{ $moduleKey }}'] === 'write' || {{ $endpoint['write'] ? 'false' : 'true' }} ? '✓' : '—'"
                                                          class="w-3 shrink-0 text-center"></span>
                                                    <span>
                                                        {{ $endpoint['label'] }}
                                                        <span class="block font-mono text-[10px] text-slate-500">{{ implode(' · ', $endpoint['routes']) }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <label class="block text-xs text-slate-400 sm:w-1/2">Vence (opcional)
                            <input type="date" name="expires_at" value="{{ old('expires_at') }}" min="{{ now()->addDay()->toDateString() }}" class="{{ $input }}">
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-800 px-5 py-3">
                        <button type="button" class="h-9 rounded-lg px-4 text-sm text-slate-300 hover:text-white" @click="createOpen = false">Cancelar</button>
                        <button type="submit" class="h-9 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">Crear key</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Manuales descargables (súper admin y técnico externo) --}}
        @foreach ($manuals as $manualType => $manualTitle)
            <div x-show="manualOpen === @js($manualType)" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="manualOpen = null">
                <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 px-5 py-4">
                        <h3 class="text-base font-semibold text-white">{{ $manualTitle }}</h3>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.companies.integrations.manual', [$company, $manualType]) }}"
                               class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14" /></svg>
                                Descargar PDF
                            </a>
                            <button type="button" class="text-slate-400 hover:text-white" @click="manualOpen = null" aria-label="Cerrar">✕</button>
                        </div>
                    </div>
                    <div class="api-manual flex-1 overflow-y-auto px-5 py-4">
                        @include('modules.admin.companies.manuals.intro', $manual)
                        @include('modules.admin.companies.manuals.api-key-sections', $manual)
                        @if ($manualType === 'integracion')
                            @include('modules.admin.companies.manuals.swagger-sections', $manual)
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Ayuda tipo manual --}}
        <div x-show="helpOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="helpOpen = false">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                    <h3 class="text-base font-semibold text-white">Integraciones API · cómo funciona</h3>
                    <button type="button" class="text-slate-400 hover:text-white" @click="helpOpen = false" aria-label="Cerrar">✕</button>
                </div>
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4 text-sm leading-relaxed text-slate-300">
                    <section>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-300">Para qué sirve</h4>
                        <p class="mt-1">Conectar otro sistema con Zentra (p. ej. la Secretaría de Educación o el software de un cliente) sin compartir la contraseña de una persona. El otro sistema lee o envía datos con una <strong>API key</strong>.</p>
                    </section>
                    <section>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-300">Cómo funciona</h4>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li>Cada key es de <strong>un usuario</strong> de la empresa: uno existente o un usuario externo creado solo para esto.</li>
                            <li>La key solo entra a los endpoints que marques: <strong>Lectura</strong> (consultar), <strong>Lectura y escritura</strong> (también crear) o <strong>Personalizado</strong> (endpoints sueltos).</li>
                            <li>Además ve solo los datos de ese usuario: sus clientes e instalaciones. Doble candado: permiso de la key + alcance del usuario.</li>
                            <li>La key se muestra <strong>una sola vez</strong> al crearla o regenerarla. Zentra guarda solo una huella; si se pierde, regenérala.</li>
                            <li><strong>Regenerar</strong> entrega una key nueva con los mismos permisos; la anterior deja de servir. <strong>Revocar</strong> la apaga de inmediato y no se reactiva.</li>
                            <li>Si el usuario se desactiva, la key deja de funcionar.</li>
                        </ul>
                    </section>
                    <section>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-300">Duración y vencimiento</h4>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li>El vencimiento es <strong>opcional</strong>: al crear la key tú eliges si le pones fecha («Vence») o no.</li>
                            <li><strong>Sin fecha</strong>, la key no caduca: sigue activa hasta que la revoques o la regeneres.</li>
                            <li><strong>Con fecha</strong>, funciona hasta ese día; cuando la fecha pasa, deja de funcionar y aparece como «Vencida». Para seguir, crea una key nueva.</li>
                        </ul>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-2.5">
                                <p class="font-semibold text-slate-200">Si creas y entregas la key</p>
                                <p class="mt-1 text-slate-400">Ponle fecha cuando el acceso sea temporal (un contrato, una prueba). Avísale al otro sistema la fecha de vencimiento al entregarla.</p>
                            </div>
                            <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-2.5">
                                <p class="font-semibold text-slate-200">Si integras la key</p>
                                <p class="mt-1 text-slate-400">Pregunta si tu key vence. Si un día todo responde <code class="text-slate-300">401</code>, la key venció o fue revocada o regenerada: pide una nueva a soporte.</p>
                            </div>
                        </div>
                    </section>
                    <section>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-violet-300">Cómo se integra</h4>
                        <p class="mt-1">En cada petición el otro sistema envía la key en el encabezado <code class="rounded bg-slate-800 px-1 text-xs">Authorization: Bearer &lt;key&gt;</code> y pide JSON:</p>
                        <pre class="mt-2 overflow-x-auto rounded-lg border border-slate-800 bg-slate-950 p-3 text-[11px] leading-relaxed text-slate-300">curl {{ $baseUrl }}/api/observatory/events \
  -H "Authorization: Bearer TU_API_KEY" \
  -H "Accept: application/json"</pre>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-400">
                            <li>Probar la key: <code class="text-slate-300">GET /api/auth/me</code> responde el usuario de la key.</li>
                            <li><code class="text-slate-300">401</code>: key inválida, revocada o vencida. <code class="text-slate-300">403</code>: la key no tiene ese permiso o el dato está fuera del alcance del usuario.</li>
                            <li>Los reportes creados por API quedan con canal «Integración».</li>
                        </ul>
                        <a href="{{ route('observatory.docs') }}" target="_blank" rel="noopener"
                           class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-violet-300 hover:text-violet-200">Ver documentación del Observatorio (Swagger) ↗</a>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
