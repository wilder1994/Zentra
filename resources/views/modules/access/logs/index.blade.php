<x-access-layout title="Ingreso y salida">
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('access.logs.index', ['tab' => 'movimiento']) }}"
           class="px-4 py-2 rounded-lg text-sm font-semibold {{ $tab === 'movimiento' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300' }}">Movimiento</a>
        <a href="{{ route('access.logs.index', array_merge(request()->except('tab'), ['tab' => 'registros'])) }}"
           class="px-4 py-2 rounded-lg text-sm font-semibold {{ $tab === 'registros' ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-300' }}">Registros</a>
        @php $bulkTotal = $tab === 'registros' ? array_sum($bulkCounts) : 0; @endphp
        @if($bulkTotal > 0)
            @can('access.register.exit')
                <div class="ml-auto" x-data="{ open: false, target: '{{ $bulkCounts['visitor'] > 0 && $bulkCounts['visitor_vehicle'] > 0 ? 'both' : ($bulkCounts['visitor'] > 0 ? 'visitor' : 'visitor_vehicle') }}' }">
                    <button type="button" @click="open = true" class="px-4 py-2 rounded-lg bg-red-700 text-white text-sm font-semibold">Salida masiva ({{ $bulkTotal }})</button>
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
                            <div class="absolute inset-0 bg-slate-950/70" @click="open = false"></div>
                            <form method="POST" action="{{ route('access.logs.bulk-exit') }}" class="relative w-full max-w-sm space-y-4 rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                                @csrf
                                <div>
                                    <h3 class="text-base font-semibold text-white">Salida masiva de visitantes</h3>
                                    <p class="mt-1 text-xs text-slate-400">El censo no se incluye: residentes y sus vehículos viven en la instalación.</p>
                                </div>
                                <div class="space-y-2">
                                    @foreach([
                                        'visitor' => ['Personas visitantes', $bulkCounts['visitor']],
                                        'visitor_vehicle' => ['Vehículos visitantes', $bulkCounts['visitor_vehicle']],
                                        'both' => ['Ambos', $bulkTotal],
                                    ] as $value => [$label, $count])
                                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm {{ $count === 0 ? 'opacity-50' : '' }}"
                                               :class="target === '{{ $value }}' ? 'border-red-500 bg-red-500/10 text-white' : 'border-slate-700 text-slate-300'">
                                            <span class="flex items-center gap-2">
                                                <input type="radio" name="target" value="{{ $value }}" x-model="target" @disabled($count === 0) class="text-red-600">
                                                {{ $label }}
                                            </span>
                                            <span class="text-xs font-semibold">{{ $count }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="open = false" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">Cancelar</button>
                                    <button class="px-4 py-2 rounded-lg bg-red-700 text-white text-sm font-semibold">Dar salida</button>
                                </div>
                            </form>
                        </div>
                    </template>
                </div>
            @endcan
        @endif
    </div>

    @if($tab === 'movimiento')
    <div class="porteria-form max-w-2xl space-y-4" x-data="porteriaMove()" x-init="boot()">
        <label class="block text-sm font-medium text-slate-300">Documento, nombre, placa o QR de autorización</label>
        <div class="flex gap-2">
            <input type="search" x-model="q" x-ref="search" @input="pending()" @input.debounce.350ms="lookup()" placeholder="Buscar…"
                   class="w-full rounded-lg bg-slate-950 border-slate-700 text-white">
            <button type="button" @click="openScanner()" class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 text-sm font-semibold text-white hover:bg-slate-700" title="Escanear QR">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/></svg>
                <span class="hidden sm:inline">Escanear QR</span>
            </button>
        </div>

        <p x-show="loading" class="text-xs text-slate-500">Buscando…</p>

        <template x-teleport="body">
            <div x-show="scanner.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeScanner()">
                <div class="absolute inset-0 bg-slate-950/80" @click="closeScanner()"></div>
                <div class="relative w-full max-w-md space-y-3 rounded-2xl border border-slate-700 bg-slate-900 p-4 shadow-2xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-white">Escanear QR de autorización</h3>
                        <button type="button" @click="closeScanner()" class="text-slate-400 hover:text-white">✕</button>
                    </div>
                    <div class="relative overflow-hidden rounded-xl bg-black aspect-square">
                        <video x-ref="scanVideo" playsinline muted class="h-full w-full object-cover"></video>
                        <div class="pointer-events-none absolute inset-10 rounded-2xl border-2 border-emerald-400/80 shadow-[0_0_0_9999px_rgba(2,6,23,0.45)]"></div>
                    </div>
                    <p class="text-center text-xs" :class="scanner.error ? 'text-red-400' : 'text-slate-400'" x-text="scanner.error || 'Apunta la cámara al código QR del visitante.'"></p>
                </div>
            </div>
        </template>

        <div x-show="!selected && authSummary" x-cloak class="rounded-xl border p-3 text-sm"
             :class="authSummary?.state === 'active' ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10'">
            <p class="font-semibold text-white" x-text="authSummary ? authSummary.type + ' · ' + authSummary.destination : ''"></p>
            <p class="text-xs mt-0.5" :class="authSummary?.state === 'active' ? 'text-emerald-300' : 'text-amber-300'" x-text="authSummary?.message"></p>
            <p class="text-xs text-slate-400 mt-1" x-show="authSummary?.guests > 1" x-text="'Elige la persona que está en portería (' + authSummary?.guests + ' autorizadas).'"></p>
            <p class="text-xs text-amber-300 mt-1" x-show="authSummary?.unregistered?.length" x-text="'Sin documento registrado: ' + (authSummary?.unregistered || []).join(', ') + ' — búscalos por cédula o regístralos.'"></p>
        </div>

        <div class="space-y-2" x-show="!selected && hits.length">
            <template x-for="row in hits" :key="row.kind + '-' + row.id">
                <button type="button" @click="pick(row)" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-indigo-500">
                    <img x-show="row.photo_url" :src="row.photo_url" alt="" class="h-12 w-12 rounded-lg object-cover bg-slate-800">
                    <div class="h-12 w-12 rounded-lg bg-slate-800" x-show="!row.photo_url"></div>
                    <div class="min-w-0">
                        <p class="text-white text-sm font-medium truncate" x-text="row.title"></p>
                        <p class="text-xs text-slate-500 truncate" x-text="typeLabel(row) + (row.document ? ' · ' + row.document : '')"></p>
                    </div>
                    <span class="ml-auto"></span>
                    <span x-show="row.auth?.can_enter && !row.inside" class="shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold text-emerald-300">Autorizado</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="row.blocked ? 'bg-red-500/15 text-red-400' : (row.inside ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/40 text-slate-300')"
                          x-text="row.blocked ? 'Bloqueado' : (row.inside ? 'Dentro' : 'Afuera')"></span>
                </button>
            </template>
        </div>

        <div x-show="!selected && searched && !loading && hits.length === 0" class="porteria-form rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-4">
            <div>
                <p class="text-sm font-semibold text-white">Primera vez — registrar ficha</p>
                <p class="text-xs text-slate-400 mt-1">Completa los datos. Visitantes y vehículos quedan con el ingreso registrado en el mismo paso.</p>
            </div>
            <form method="POST" action="{{ route('access.logs.register') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="also_enter" :value="regKind === 'member' ? '0' : '1'">
                <input type="hidden" name="subject_kind" :value="regKind === 'census_vehicle' ? 'vehicle' : regKind">

                <div>
                    <p class="text-xs text-slate-400 mb-2">Tipo de ficha</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach([
                            'visitor' => 'Visitante · persona',
                            'vehicle' => 'Visitante · vehículo',
                            'member' => 'Censo · persona',
                            'census_vehicle' => 'Censo · vehículo',
                        ] as $kindValue => $kindLabel)
                            <button type="button" @click="regKind = '{{ $kindValue }}'"
                                    :class="regKind === '{{ $kindValue }}' ? 'bg-indigo-600 text-white border-transparent' : 'bg-slate-950 text-slate-300 border-slate-700'"
                                    class="px-3 py-2 rounded-lg border text-sm font-medium text-left transition-colors">{{ $kindLabel }}</button>
                        @endforeach
                    </div>
                </div>

                <template x-if="regKind === 'census_vehicle'">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-slate-400 col-span-2">Apartamento / casa del propietario <span class="text-amber-400">*</span>
                                <select name="structure_id" required class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                    <option value="">Seleccionar</option>
                                    @foreach($nodes as $node)
                                        <option value="{{ $node->id }}">{{ $node->installation?->name }} · {{ $node->name }}@if($node->structureType) ({{ $node->structureType->name }})@endif</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-400">Placa <span class="text-amber-400">*</span>
                                <input name="plate" x-model="regPlate" required class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm uppercase">
                            </label>
                            <label class="text-xs text-slate-400">Tipo
                                <select name="vehicle_type" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                    <option value="carro">Carro</option>
                                    <option value="moto">Moto</option>
                                    <option value="camion">Camión</option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-400">Marca
                                <input name="vehicle_brand" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                            </label>
                            <label class="text-xs text-slate-400">Color
                                <input name="vehicle_color" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                            </label>
                        </div>
                        @include('modules.access.partials.camera-capture', [
                            'name' => 'person_photo_data',
                            'label' => 'Foto del vehículo',
                            'required' => false,
                            'hint' => 'Opcional',
                        ])
                    </div>
                </template>

                <template x-if="regKind === 'visitor' || regKind === 'vehicle'">
                    <div class="space-y-4">
                        @include('modules.access.partials.visitor-profile-fields', ['prefix' => 'reg'])
                        <div class="grid grid-cols-2 gap-2" x-show="regKind === 'vehicle'">
                            <label class="text-xs text-slate-400">Placa <span class="text-amber-400">*</span>
                                <input name="plate" x-model="regPlate" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm uppercase">
                            </label>
                            <label class="text-xs text-slate-400">Tipo
                                <select name="vehicle_type" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                    <option value="carro">Carro</option>
                                    <option value="moto">Moto</option>
                                    <option value="camion">Camión</option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-400">Marca
                                <input name="vehicle_brand" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                            </label>
                            <label class="text-xs text-slate-400">Color
                                <input name="vehicle_color" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                            </label>
                        </div>
                        @include('modules.access.partials.camera-capture', [
                            'name' => 'person_photo_data',
                            'label' => 'Foto',
                            'required' => true,
                            'hint' => 'Abre la cámara, acomoda a la persona y pulsa Tomar foto',
                        ])
                        <div class="space-y-2 rounded-xl border border-slate-700/60 bg-slate-950/40 p-3">
                            <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Esta visita</p>
                            <label class="text-xs text-slate-400 block">¿A dónde va? (apartamento o zona)
                                <select name="destination_structure_id" x-model="destId" @change="loadHosts()"
                                        class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                    <option value="">Elegir nodo o usar texto abajo</option>
                                    @foreach($nodes as $node)
                                        <option value="{{ $node->id }}">{{ $node->name }}@if($node->structureType) · {{ $node->structureType->name }}@endif</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-400 block">Destino (si no está en la lista)
                                <input name="destination_text" x-model="destText"
                                       class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm"
                                       placeholder="Zona común, administración, torre…">
                            </label>
                            <label class="text-xs text-slate-400 block">¿Quién autoriza? (residente del nodo)
                                <select name="authorized_member_id" x-model="authId"
                                        class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                    <option value="">— Escribir nombre abajo —</option>
                                    <template x-for="h in hosts" :key="h.id">
                                        <option :value="h.id" x-text="h.name"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="text-xs text-slate-400 block">Nombre de quien autoriza
                                <input name="authorized_text" x-model="authText"
                                       class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm"
                                       placeholder="Administración, contratista externo, residente por teléfono…">
                            </label>
                            <label class="text-xs text-slate-400 block">Motivo (opcional)
                                <input name="purpose" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm"
                                       placeholder="Visita, entrega, revisión de contadores…">
                            </label>
                        </div>
                    </div>
                </template>

                <template x-if="regKind === 'member'">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs text-slate-400">Nombres
                            <input name="first_name" x-model="regFirst" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                        </label>
                        <label class="text-xs text-slate-400">Apellidos
                            <input name="last_name" x-model="regLast" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                        </label>
                        <label class="text-xs text-slate-400">Tipo doc.
                            <input name="document_type" value="CC" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                        </label>
                        <label class="text-xs text-slate-400">Documento
                            <input name="document_number" x-model="regDoc" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                        </label>
                        <label class="text-xs text-slate-400 col-span-2">Apartamento / nodo
                            <select name="structure_id" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                <option value="">Seleccionar</option>
                                @foreach($nodes as $node)
                                    <option value="{{ $node->id }}">{{ $node->installation?->name }} · {{ $node->name }}@if($node->structureType) ({{ $node->structureType->name }})@endif</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs text-slate-400 col-span-2">Tipo de persona
                            <select name="member_type_id" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                <option value="">Tipo de persona</option>
                                @foreach($memberTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </template>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold"
                            x-text="regKind === 'member' ? 'Crear ficha del censo' : 'Registrar e ingresar'"></button>
                </div>
            </form>
        </div>

        <div x-show="selected" class="porteria-form rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-4">
            <div class="flex gap-4">
                <img x-show="selected?.photo_url" :src="selected?.photo_url" alt="" class="h-24 w-24 rounded-xl object-cover bg-slate-800">
                <div class="h-24 w-24 rounded-xl bg-slate-800" x-show="selected && !selected.photo_url"></div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-lg font-semibold text-white truncate" x-text="selected?.title"></p>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold"
                              :class="selected?.blocked ? 'bg-red-500/15 text-red-400' : (selected?.inside ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-700/40 text-slate-300')"
                              x-text="selected?.blocked ? 'Bloqueado' : (selected?.inside ? 'Dentro' : 'Afuera')"></span>
                    </div>
                    <p class="text-sm text-slate-400" x-text="selected?.document"></p>
                    <p class="text-xs text-slate-500" x-text="selected ? typeLabel(selected) + (selected.node ? ' · ' + selected.node : '') : ''"></p>
                    <p class="text-xs text-red-400" x-show="selected?.blocked" x-text="selected?.block_reason"></p>
                </div>
            </div>

            <template x-if="selected?.auth && !selected.inside && !selected.blocked">
                <div class="rounded-xl border p-3 text-sm"
                     :class="selected.auth.can_enter ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-amber-500/40 bg-amber-500/10'">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold" :class="selected.auth.can_enter ? 'text-emerald-300' : 'text-amber-300'"
                           x-text="(selected.auth.can_enter ? 'Autorización vigente' : 'Autorización ' + selected.auth.state_label.toLowerCase()) + ' · ' + selected.auth.type"></p>
                        <span class="text-xs text-slate-400" x-text="selected.auth.validity"></span>
                    </div>
                    <dl class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2" x-show="selected.auth.can_enter">
                        <div><dt class="text-xs text-slate-500">Destino</dt><dd class="text-white" x-text="selected.auth.destination"></dd></div>
                        <div><dt class="text-xs text-slate-500">Autoriza</dt><dd class="text-white" x-text="selected.auth.authorized_name"></dd></div>
                        <div x-show="selected.auth.plate"><dt class="text-xs text-slate-500">Placa</dt><dd class="font-mono text-white" x-text="selected.auth.plate"></dd></div>
                        <div class="sm:col-span-2" x-show="selected.auth.notes"><dt class="text-xs text-slate-500">Notas</dt><dd class="text-slate-200" x-text="selected.auth.notes"></dd></div>
                    </dl>
                    <p class="mt-1 text-xs text-amber-200" x-show="!selected.auth.can_enter" x-text="selected.auth.message"></p>
                </div>
            </template>

            <form method="POST" action="{{ route('access.logs.move') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="kind" :value="selected?.kind">
                <input type="hidden" name="id" :value="selected?.id">

                <template x-if="step && selected && !selected.census && !selected.photo_url">
                    <div>
                        <p class="text-xs text-amber-300 mb-2">Sin foto en la ficha — tómala antes de ingresar.</p>
                        @include('modules.access.partials.camera-capture', [
                            'name' => 'person_photo_data',
                            'label' => 'Foto',
                            'required' => true,
                        ])
                    </div>
                </template>

                <template x-if="step === 'auth'">
                    <div class="space-y-3 rounded-xl border border-emerald-500/40 bg-slate-950/40 p-3">
                        <input type="hidden" name="authorization_guest_id" :value="selected.auth.guest_id">
                        <p class="text-sm text-slate-300">Destino y autorizador vienen de la autorización y no se modifican.</p>
                        <div class="flex flex-wrap gap-2">
                            <button name="action" value="enter" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">Ingresar con autorización</button>
                            <button type="button" @click="normalEnter()" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">Va a otro lugar</button>
                        </div>
                    </div>
                </template>

                <template x-if="step === 'confirm'">
                    <div class="space-y-3 rounded-xl border border-slate-700/60 bg-slate-950/40 p-3">
                        <input type="hidden" name="destination_structure_id" :value="destId">
                        <input type="hidden" name="destination_text" :value="destText">
                        <input type="hidden" name="authorized_member_id" :value="authId">
                        <input type="hidden" name="authorized_text" :value="authText">
                        <p class="text-sm font-semibold text-white">¿Se dirige al mismo lugar?</p>
                        <dl class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-slate-500">Destino</dt><dd class="text-slate-200" x-text="lastDestination()"></dd></div>
                            <div><dt class="text-xs text-slate-500">Autoriza</dt><dd class="text-slate-200" x-text="lastAuthorizer()"></dd></div>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <button name="action" value="enter" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">Sí, ingresar</button>
                            <button type="button" @click="step = 'form'" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">No, cambiar</button>
                        </div>
                    </div>
                </template>

                <template x-if="step === 'form'">
                    <div class="space-y-3 rounded-xl border border-slate-700/60 bg-slate-950/40 p-3">
                        <p class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Esta visita</p>
                        <label class="block text-xs text-slate-400">¿A dónde va? (apartamento o zona)
                            <select name="destination_structure_id" x-model="destId" @change="loadHosts()" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                <option value="">Elegir nodo o usar texto abajo</option>
                                @foreach($nodes as $node)
                                    <option value="{{ $node->id }}">{{ $node->name }}@if($node->structureType) · {{ $node->structureType->name }}@endif</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs text-slate-400">Destino (si no está en la lista)
                            <input name="destination_text" x-model="destText" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm" placeholder="Zona común, administración…">
                        </label>
                        <label class="block text-xs text-slate-400">¿Quién autoriza? (residente del nodo)
                            <select name="authorized_member_id" x-model="authId" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                <option value="">— Escribir nombre abajo —</option>
                                <template x-for="h in hosts" :key="h.id">
                                    <option :value="h.id" x-text="h.name" :selected="String(h.id) === authId"></option>
                                </template>
                            </select>
                        </label>
                        <label class="block text-xs text-slate-400">Nombre de quien autoriza
                            <input name="authorized_text" x-model="authText" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm"
                                   placeholder="Administración, persona externa…">
                        </label>
                        <button name="action" value="enter" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">Ingresar</button>
                    </div>
                </template>

                <div class="flex flex-wrap gap-2" x-show="!step">
                    <template x-if="selected && selected.inside">
                        <button name="action" value="exit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold"
                                :class="selected.census ? 'bg-emerald-600' : 'bg-amber-600'">Dar salida</button>
                    </template>
                    <template x-if="selected && !selected.inside && !selected.blocked && selected.census">
                        <button name="action" value="enter" class="px-4 py-2 rounded-lg bg-yellow-400 text-slate-900 text-sm font-semibold">Ingresar</button>
                    </template>
                    <template x-if="selected && !selected.inside && !selected.blocked && !selected.census">
                        <button type="button" @click="startEnter()" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold"
                                x-text="selected.auth?.can_enter ? 'Ingresar autorizado' : 'Ingresar'"></button>
                    </template>
                    <button type="button" @click="cancel()" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">Cancelar</button>
                </div>
                <button type="button" x-show="step" @click="step = null" class="text-xs text-slate-400 hover:text-white">← Volver</button>
            </form>
        </div>
    </div>
    @else
    @include('modules.access.logs.partials.records')
    @endif

    @if($tab === 'movimiento')
    <script>
        function porteriaMove() {
            return {
                q: @json((string) request('q', '')),
                hits: [],
                selected: null,
                step: null,
                loading: false,
                searched: false,
                destId: '',
                destText: '',
                authId: '',
                authText: '',
                hosts: [],
                regKind: 'visitor',
                regFirst: '',
                regLast: '',
                regDoc: '',
                regPhone: '',
                regEmail: '',
                regCompany: '',
                regType: 'persona',
                regPlate: '',
                authSummary: null,
                scanner: { open: false, error: '', stream: null, timer: null, detector: null },
                lookupUrl: @json(route('access.logs.lookup')),
                hostsUrl: @json(route('access.logs.hosts')),
                jsQrUrl: @json(asset('js/vendor/jsQR.js')),
                boot() {
                    if (this.q.length >= 2) this.lookup();
                    if (/^\d{5,}$/.test(this.q.trim())) this.regDoc = this.q.trim();
                },
                pending() {
                    this.selected = null;
                    this.authSummary = null;
                    this.step = null;
                    this.searched = false;
                    this.loading = this.q.trim().length >= 2;
                },
                lookup() {
                    const term = this.q.trim();
                    this.selected = null;
                    this.step = null;
                    if (term.length < 2) { this.hits = []; this.loading = false; this.searched = false; return; }
                    this.loading = true;
                    if (/^\d{5,}$/.test(term)) this.regDoc = term;
                    fetch(this.lookupUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => {
                            if (term !== this.q.trim()) return;
                            this.hits = d.hits || [];
                            this.authSummary = d.authorization || null;
                            this.loading = false;
                            this.searched = true;
                            const exact = this.exactHit(term);
                            if (exact) this.pick(exact);
                        })
                        .catch(() => { this.loading = false; this.searched = true; });
                },
                exactHit(term) {
                    if (this.hits.length === 1) return this.hits[0];
                    const norm = term.toUpperCase().replace(/[\s.\-]/g, '');
                    const matches = this.hits.filter(h => {
                        const plate = (h.plate || '').toUpperCase().replace(/[\s\-]/g, '');
                        const doc = (h.document || '').toUpperCase().replace(/[\s.\-]/g, '');
                        return (plate && plate === norm) || (doc && doc.endsWith(norm) && norm.length >= 5);
                    });
                    return matches.length === 1 ? matches[0] : null;
                },
                typeLabel(row) {
                    const who = row.census ? 'Censo' : 'Visitante';
                    return who + ' · ' + (row.kind === 'vehicle' ? 'vehículo' : 'persona');
                },
                lastDestination() {
                    const l = this.selected?.last;
                    return l ? (l.destination_name || l.destination_text || '—') : '—';
                },
                lastAuthorizer() {
                    const l = this.selected?.last;
                    return l ? (l.authorized_name || l.authorized_text || '—') : '—';
                },
                startEnter() {
                    if (this.selected?.auth?.can_enter) { this.step = 'auth'; return; }
                    this.normalEnter();
                },
                normalEnter() {
                    const l = this.selected?.last;
                    const hasLast = l && (l.destination_structure_id || l.destination_text) && (l.authorized_member_id || l.authorized_text);
                    this.step = hasLast ? 'confirm' : 'form';
                },
                pick(row) {
                    this.selected = row;
                    this.step = null;
                    this.destId = row.last?.destination_structure_id ? String(row.last.destination_structure_id) : '';
                    this.destText = row.last?.destination_text || '';
                    this.authId = row.last?.authorized_member_id ? String(row.last.authorized_member_id) : '';
                    this.authText = row.last?.authorized_text || '';
                    if (this.destId) this.loadHosts();
                },
                cancel() { this.selected = null; this.step = null; },
                async openScanner() {
                    this.scanner.error = '';
                    this.scanner.open = true;
                    try {
                        this.scanner.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                    } catch (e) {
                        this.scanner.error = 'No se pudo abrir la cámara. Revisa los permisos o escribe la cédula.';
                        return;
                    }
                    const video = this.$refs.scanVideo;
                    video.srcObject = this.scanner.stream;
                    await video.play().catch(() => {});
                    if ('BarcodeDetector' in window) {
                        try {
                            const formats = await BarcodeDetector.getSupportedFormats();
                            if (formats.includes('qr_code')) this.scanner.detector = new BarcodeDetector({ formats: ['qr_code'] });
                        } catch (e) { this.scanner.detector = null; }
                    }
                    if (!this.scanner.detector && typeof jsQR === 'undefined') {
                        await new Promise((resolve) => {
                            const s = document.createElement('script');
                            s.src = this.jsQrUrl;
                            s.onload = resolve;
                            s.onerror = resolve;
                            document.head.appendChild(s);
                        });
                    }
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    const tick = async () => {
                        if (!this.scanner.open) return;
                        let text = null;
                        if (video.readyState >= 2) {
                            if (this.scanner.detector) {
                                const codes = await this.scanner.detector.detect(video).catch(() => []);
                                text = codes[0]?.rawValue || null;
                            } else if (typeof jsQR !== 'undefined') {
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                ctx.drawImage(video, 0, 0);
                                const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                text = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data || null;
                            }
                        }
                        if (text) {
                            this.closeScanner();
                            this.q = text.trim();
                            this.pending();
                            this.lookup();
                            return;
                        }
                        this.scanner.timer = setTimeout(tick, 200);
                    };
                    tick();
                },
                closeScanner() {
                    this.scanner.open = false;
                    clearTimeout(this.scanner.timer);
                    (this.scanner.stream?.getTracks() || []).forEach((t) => t.stop());
                    this.scanner.stream = null;
                    if (this.$refs.scanVideo) this.$refs.scanVideo.srcObject = null;
                },
                loadHosts() {
                    if (!this.destId) { this.hosts = []; return; }
                    fetch(this.hostsUrl + '?structure_id=' + this.destId, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => { this.hosts = d.hosts || []; });
                },
            };
        }
    </script>
    @endif
</x-access-layout>
