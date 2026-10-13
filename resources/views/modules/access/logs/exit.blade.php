<x-access-layout title="Kiosco de Salida">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.logs.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-400">Escanea o ingresa el documento (o placa) para registrar la salida.</p>

    <div class="max-w-2xl mx-auto">
        <div x-data="exitKiosk()" class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="p-6">
                <label class="block text-sm font-semibold text-slate-300 mb-2">Cédula del visitante / Documento / Placa</label>
                <input
                    type="text"
                    x-ref="scanInput"
                    x-model="document"
                    @keydown.enter.prevent="lookup()"
                    placeholder="Escanéa el QR o digita el documento..."
                    class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500 text-lg py-4"
                    :disabled="busy || confirming"
                    autofocus
                >
                <p class="mt-2 text-xs text-slate-500">Presiona Enter para buscar.</p>

                <template x-if="message">
                    <div class="mt-4 p-3 rounded-lg text-sm" :class="messageType === 'error' ? 'bg-red-900/40 border border-red-700 text-red-200' : 'bg-emerald-900/40 border border-emerald-700 text-emerald-200'" x-text="message"></div>
                </template>

                <template x-if="matches.length > 1">
                    <div class="mt-4">
                        <p class="text-sm font-semibold text-slate-300 mb-2">Se encontraron varios ingresos activos. Selecciona uno:</p>
                        <div class="space-y-2">
                            <template x-for="m in matches" :key="m.id">
                                <button @click="selectMatch(m)" class="w-full text-left px-4 py-3 bg-slate-800 hover:bg-indigo-900/40 rounded-lg border border-slate-700 transition-colors">
                                    <p class="text-sm font-medium text-white" x-text="m.name + ' — ' + m.destination"></p>
                                    <p class="text-xs text-slate-400" x-text="'Ingresó: ' + m.entry_time + ' · ' + m.duration_hours + ' h' + (m.has_vehicle ? ' · 🚗 ' + m.has_vehicle : '')"></p>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="selected">
                    <div class="mt-5 bg-slate-800 rounded-xl border border-slate-700 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-bold text-white" x-text="selected.name"></p>
                                <p class="text-xs text-slate-400" x-text="'Ingresó: ' + selected.entry_time + ' (' + selected.duration_hours + ' h)'"></p>
                                <p class="text-xs text-slate-400" x-text="'Destino: ' + selected.destination"></p>
                            </div>
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-300">Salida en curso</span>
                        </div>

                        <label class="mt-4 flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="hasCustody" class="rounded bg-slate-950 border-slate-700 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm text-slate-300">Registrar custodia / bienes entregados</span>
                        </label>

                        <template x-if="hasCustody">
                            <div class="mt-3 space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">Descripción de la custodia</label>
                                    <textarea x-model="custodyDescription" rows="2" class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">Recibe a nombre de</label>
                                    <input x-model="custodyReceiver" class="block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-emerald-500 focus:ring-emerald-500" placeholder="Nombre de quien recibe la cédula o bienes">
                                </div>
                            </div>
                        </template>

                        <div class="mt-5 flex justify-end gap-3">
                            <button @click="reset()" type="button" class="inline-flex items-center px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg font-semibold text-xs text-slate-200 hover:bg-slate-600 transition-colors">Cancelar</button>
                            <button @click="confirmExit()" :disabled="busy" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 transition-colors shadow-sm">
                                <span x-show="!busy">Confirmar Salida</span>
                                <span x-show="busy">Procesando...</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function exitKiosk() {
            return {
                document: '',
                matching: false,
                busy: false,
                confirming: false,
                message: '',
                messageType: 'error',
                matches: [],
                selected: null,
                hasCustody: false,
                custodyDescription: '',
                custodyReceiver: '',
                async lookup() {
                    const doc = this.document.trim();
                    if (!doc) return;
                    this.matching = true;
                    this.message = '';
                    this.matches = [];
                    this.selected = null;
                    this.confirming = false;
                    try {
                        const res = await fetch('{{ route("access.logs.scan-exit") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ document_number: doc })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.message = data.error || 'Error al consultar la salida.';
                            return;
                        }
                        if (!data.found) {
                            this.messageType = 'error';
                            this.message = data.message || 'Sin ingresos activos.';
                            this.document = '';
                            this.$refs.scanInput.focus();
                            return;
                        }
                        if (data.matches.length === 1) {
                            this.selectMatch(data.matches[0]);
                        } else {
                            this.matches = data.matches;
                        }
                    } catch (e) {
                        this.message = 'Error de conexión. Intenta de nuevo.';
                    } finally {
                        this.matching = false;
                    }
                },
                selectMatch(m) {
                    this.selected = m;
                    this.matches = [];
                    this.confirming = true;
                    this.document = '';
                },
                async confirmExit() {
                    if (!this.selected) return;
                    if (this.hasCustody && !this.custodyDescription.trim()) {
                        this.message = 'Describe la custodia o desactiva la opción.';
                        return;
                    }
                    this.busy = true;
                    this.message = '';
                    try {
                        const res = await fetch('{{ route("access.logs.scan-exit") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({
                                log_id: this.selected.id,
                                has_custody: this.hasCustody,
                                custody_description: this.custodyDescription,
                                custody_receiver_name: this.custodyReceiver
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.messageType = 'error';
                            this.message = data.error || 'No se pudo registrar la salida.';
                        } else {
                            this.messageType = 'success';
                            this.message = data.message || 'Salida registrada.';
                        }
                    } catch (e) {
                        this.messageType = 'error';
                        this.message = 'Error de conexión.';
                    } finally {
                        this.busy = false;
                        this.reset(false);
                    }
                },
                reset(clearMessage = true) {
                    this.selected = null;
                    this.matches = [];
                    this.confirming = false;
                    this.hasCustody = false;
                    this.custodyDescription = '';
                    this.custodyReceiver = '';
                    this.document = '';
                    if (clearMessage) this.message = '';
                    this.$nextTick(() => { if (this.$refs.scanInput) this.$refs.scanInput.focus(); });
                }
            }
        }
    </script>
    @endpush
</x-access-layout>