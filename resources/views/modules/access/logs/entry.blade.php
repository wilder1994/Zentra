<x-access-layout title="Registrar ingreso">
    <div class="max-w-3xl" x-data="porteriaEntry()">
        <p class="text-sm text-slate-400 mb-4">Puerta: <span class="text-white font-medium">{{ $door?->name ?? '—' }}</span></p>

        @if($errors->any())
            <div class="mb-4 rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('access.logs.entry.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="subject_kind" :value="kind">
            <input type="hidden" name="member_id" :value="memberId">
            <input type="hidden" name="visitor_id" :value="visitorId">
            <input type="hidden" name="vehicle_id" :value="vehicleId">
            <input type="hidden" name="person_photo_data" :value="personPhoto">
            <input type="hidden" name="vehicle_photo_data" :value="vehiclePhoto">

            <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 space-y-3">
                <label class="block text-sm font-medium text-slate-300">Buscar documento, nombre o placa</label>
                <input type="search" x-model="q" @input.debounce.400ms="lookup()" placeholder="Cédula, nombre o placa"
                       class="w-full rounded-lg bg-slate-950 border-slate-700 text-white">
                <p class="text-xs text-red-400 font-semibold" x-show="blocked" x-text="blockReason || 'No puede ingresar.'"></p>
                <div class="space-y-1" x-show="results.length">
                    <template x-for="row in results" :key="row.kind + '-' + row.id">
                        <button type="button" @click="pick(row)" class="w-full text-left px-3 py-2 rounded-lg bg-slate-800 hover:bg-indigo-900/40 text-sm">
                            <span class="text-white" x-text="row.name || row.plate"></span>
                            <span class="text-xs text-slate-500 ml-2" x-text="row.document || row.owner || ''"></span>
                            <span class="text-xs text-red-400" x-show="row.blocked">Bloqueado</span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900 p-3 cursor-pointer">
                    <input type="radio" x-model="kind" value="member" class="text-indigo-500">
                    <span class="text-sm">Censo (persona del nodo)</span>
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900 p-3 cursor-pointer">
                    <input type="radio" x-model="kind" value="visitor" class="text-indigo-500">
                    <span class="text-sm">Visitante</span>
                </label>
            </div>

            <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-500">Nombre</label>
                    <input name="first_name" x-model="firstName" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Apellido</label>
                    <input name="last_name" x-model="lastName" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Tipo doc.</label>
                    <input name="document_type" x-model="documentType" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Documento</label>
                    <input name="document_number" x-model="documentNumber" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                </div>
                <div x-show="kind === 'member'" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-slate-500">Nodo</label>
                        <select name="structure_id" x-model="structureId" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                            <option value="">Seleccionar</option>
                            @foreach($nodes as $node)
                                <option value="{{ $node->id }}">{{ $node->installation?->name }} · {{ $node->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Tipo de persona</label>
                        <select name="member_type_id" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                            <option value="">Automático</option>
                            @foreach($memberTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="with_vehicle" value="1" x-model="withVehicle" @checked($withVehicle) class="rounded bg-slate-950 border-slate-700 text-indigo-500">
                Ingresa con vehículo
            </label>

            <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 grid grid-cols-1 md:grid-cols-3 gap-3" x-show="withVehicle">
                <div>
                    <label class="text-xs text-slate-500">Placa</label>
                    <input name="plate" x-model="plate" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm uppercase">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Marca</label>
                    <input name="vehicle_brand" x-model="vehicleBrand" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Color</label>
                    <input name="vehicle_color" x-model="vehicleColor" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-500">Motivo</label>
                <input name="purpose" class="mt-1 w-full rounded-lg bg-slate-950 border-slate-700 text-white text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
                    <p class="text-xs text-slate-500 mb-2">Foto persona (opcional)</p>
                    <video x-ref="personVideo" autoplay playsinline class="w-full rounded-lg bg-black aspect-video"></video>
                    <div class="mt-2 flex gap-2">
                        <button type="button" @click="startCam('person')" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800">Cámara</button>
                        <button type="button" @click="snap('person')" class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 text-white">Capturar</button>
                    </div>
                    <img x-show="personPhoto" :src="personPhoto" alt="" class="mt-2 h-20 rounded-lg object-cover">
                </div>
                <div class="bg-slate-900 rounded-xl border border-slate-800 p-4" x-show="withVehicle">
                    <p class="text-xs text-slate-500 mb-2">Foto vehículo (opcional)</p>
                    <video x-ref="vehicleVideo" autoplay playsinline class="w-full rounded-lg bg-black aspect-video"></video>
                    <div class="mt-2 flex gap-2">
                        <button type="button" @click="startCam('vehicle')" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800">Cámara</button>
                        <button type="button" @click="snap('vehicle')" class="text-xs px-3 py-1.5 rounded-lg bg-indigo-600 text-white">Capturar</button>
                    </div>
                    <img x-show="vehiclePhoto" :src="vehiclePhoto" alt="" class="mt-2 h-20 rounded-lg object-cover">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('access.logs.index') }}" class="px-4 py-2 text-sm rounded-lg bg-slate-800">Cancelar</a>
                <button type="submit" :disabled="blocked" class="px-4 py-2 text-sm rounded-lg bg-emerald-600 text-white font-semibold disabled:opacity-40">Registrar ingreso</button>
            </div>
        </form>
    </div>
@push('scripts')
<script>
function porteriaEntry() {
    return {
        q: '',
        kind: 'visitor',
        withVehicle: {{ $withVehicle ? 'true' : 'false' }},
        memberId: '',
        visitorId: '',
        vehicleId: '',
        firstName: '',
        lastName: '',
        documentType: 'CC',
        documentNumber: '',
        structureId: '',
        plate: '',
        vehicleBrand: '',
        vehicleColor: '',
        personPhoto: '',
        vehiclePhoto: '',
        results: [],
        blocked: false,
        blockReason: '',
        async lookup() {
            if (this.q.length < 2) { this.results = []; return; }
            const res = await fetch('{{ route('access.logs.lookup') }}?q=' + encodeURIComponent(this.q));
            const data = await res.json();
            this.results = [...(data.members || []), ...(data.visitors || []), ...(data.vehicles || []), ...(data.authorizations || [])];
        },
        pick(row) {
            if (row.blocked) {
                this.blocked = true;
                this.blockReason = row.block_message || 'No puede ingresar.';
                return;
            }
            this.blocked = false;
            if (row.kind === 'member') {
                this.kind = 'member';
                this.memberId = row.id;
                this.visitorId = '';
                this.firstName = row.name;
                this.documentNumber = row.document || '';
            } else if (row.kind === 'visitor' || row.kind === 'authorization') {
                this.kind = 'visitor';
                this.visitorId = row.kind === 'visitor' ? row.id : '';
                this.memberId = '';
                this.firstName = row.name;
                this.documentNumber = row.document || '';
            } else if (row.kind === 'vehicle') {
                this.withVehicle = true;
                this.vehicleId = row.id;
                this.plate = row.plate;
                if (row.census) this.kind = 'member';
            }
            this.results = [];
        },
        async startCam(which) {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            this.$refs[which + 'Video'].srcObject = stream;
        },
        snap(which) {
            const video = this.$refs[which + 'Video'];
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            canvas.getContext('2d').drawImage(video, 0, 0);
            if (which === 'person') this.personPhoto = canvas.toDataURL('image/jpeg', 0.8);
            else this.vehiclePhoto = canvas.toDataURL('image/jpeg', 0.8);
        }
    }
}
</script>
@endpush
</x-access-layout>
