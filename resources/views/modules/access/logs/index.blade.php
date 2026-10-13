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
        @if($tab === 'registros' && $activeCount > 0)
            @can('access.register.exit')
                <form action="{{ route('access.logs.bulk-exit') }}" method="POST" class="ml-auto"
                      onsubmit="return confirm('¿Registrar la salida de las {{ $activeCount }} personas o vehículos que siguen adentro?')">
                    @csrf
                    <button class="px-4 py-2 rounded-lg bg-red-700 text-white text-sm font-semibold">Salida masiva ({{ $activeCount }})</button>
                </form>
            @endcan
        @endif
    </div>

    @if($tab === 'movimiento')
    <div class="porteria-form max-w-2xl space-y-4" x-data="porteriaMove()" x-init="boot()">
        <label class="block text-sm font-medium text-slate-300">Documento, nombre o placa</label>
        <input type="search" x-model="q" @input.debounce.350ms="lookup()" placeholder="Buscar…"
               class="w-full rounded-lg bg-slate-950 border-slate-700 text-white">

        <div class="space-y-2" x-show="!selected && hits.length">
            <template x-for="row in hits" :key="row.kind + '-' + row.id">
                <button type="button" @click="pick(row)" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-indigo-500">
                    <img x-show="row.photo_url" :src="row.photo_url" alt="" class="h-12 w-12 rounded-lg object-cover bg-slate-800">
                    <div class="h-12 w-12 rounded-lg bg-slate-800" x-show="!row.photo_url"></div>
                    <div class="min-w-0">
                        <p class="text-white text-sm font-medium truncate" x-text="row.title"></p>
                        <p class="text-xs text-slate-500" x-text="(row.census ? 'Censo' : 'Visitante') + ' · ' + (row.document || '')"></p>
                    </div>
                    <span class="ml-auto text-xs text-red-400" x-show="row.blocked">Bloqueado</span>
                </button>
            </template>
        </div>

        <div x-show="!selected && q.length >= 2 && !loading && hits.length === 0" class="porteria-form rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-4">
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
                <div>
                    <p class="text-lg font-semibold text-white" x-text="selected?.title"></p>
                    <p class="text-sm text-slate-400" x-text="selected?.document"></p>
                    <p class="text-xs text-slate-500" x-text="selected?.census ? 'Censo' : 'Visitante'"></p>
                    <p class="text-xs text-red-400" x-show="selected?.blocked" x-text="selected?.block_reason"></p>
                </div>
            </div>

            <form method="POST" action="{{ route('access.logs.move') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="kind" :value="selected?.kind">
                <input type="hidden" name="id" :value="selected?.id">

                <template x-if="selected && !selected.census && !selected.inside && !selected.photo_url">
                    <div>
                        <p class="text-xs text-amber-300 mb-2">Sin foto en la ficha — tómalo antes de ingresar.</p>
                        @include('modules.access.partials.camera-capture', [
                            'name' => 'person_photo_data',
                            'label' => 'Foto',
                            'required' => true,
                        ])
                    </div>
                </template>

                <template x-if="selected && !selected.census && !selected.inside">
                    <div class="grid grid-cols-1 gap-3">
                        <label class="text-xs text-slate-400">¿A dónde va? (apartamento o zona)
                            <select name="destination_structure_id" x-model="destId" @change="loadHosts()" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                <option value="">Elegir nodo o usar texto abajo</option>
                                @foreach($nodes as $node)
                                    <option value="{{ $node->id }}">{{ $node->name }}@if($node->structureType) · {{ $node->structureType->name }}@endif</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs text-slate-400">Destino (si no está en la lista)
                            <input name="destination_text" x-model="destText" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm" placeholder="Zona común, administración…">
                        </label>
                        <label class="text-xs text-slate-400">¿Quién autoriza? (residente del nodo)
                            <select name="authorized_member_id" x-model="authId" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                                <option value="">— Escribir nombre abajo —</option>
                                <template x-for="h in hosts" :key="h.id">
                                    <option :value="h.id" x-text="h.name"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-slate-400">Nombre de quien autoriza
                            <input name="authorized_text" x-model="authText" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm"
                                   placeholder="Administración, persona externa…">
                        </label>
                    </div>
                </template>

                <div class="flex flex-wrap gap-2">
                    <button x-show="selected && !selected.inside && !selected.blocked" name="action" value="enter" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">Ingresa</button>
                    <button x-show="selected && selected.inside" name="action" value="exit" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold">Sale</button>
                    <button type="button" @click="cancel()" class="px-4 py-2 rounded-lg bg-slate-800 text-slate-200 text-sm">Cancelar</button>
                </div>
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
                loading: false,
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
                lookupUrl: @json(route('access.logs.lookup')),
                hostsUrl: @json(route('access.logs.hosts')),
                boot() {
                    if (this.q.length >= 2) this.lookup();
                    if (/^\d{5,}$/.test(this.q.trim())) this.regDoc = this.q.trim();
                },
                lookup() {
                    this.selected = null;
                    if (this.q.trim().length < 2) { this.hits = []; return; }
                    this.loading = true;
                    if (/^\d{5,}$/.test(this.q.trim())) this.regDoc = this.q.trim();
                    fetch(this.lookupUrl + '?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => { this.hits = d.hits || []; this.loading = false; })
                        .catch(() => { this.loading = false; });
                },
                pick(row) {
                    this.selected = row;
                    this.destId = row.last?.destination_structure_id ? String(row.last.destination_structure_id) : '';
                    this.destText = row.last?.destination_text || '';
                    this.authId = row.last?.authorized_member_id ? String(row.last.authorized_member_id) : '';
                    this.authText = row.last?.authorized_text || '';
                    if (this.destId) this.loadHosts();
                },
                cancel() { this.selected = null; },
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
