<x-access-layout title="Registrar Correspondencia">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.correspondence.index') }}">← Volver</x-ui.button>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Nuevo Paquete o Encomienda</h3>
                <p class="text-sm text-slate-500 mt-0.5">Registre la correspondencia recibida para su posterior entrega</p>
            </div>
            <div class="px-6 py-5">
                <div x-data="{ assignType: 'resident' }">
                    <label class="block text-sm font-semibold text-slate-300 mb-3">Asignar a</label>
                    <div class="flex flex-wrap gap-2 mb-6">
                        <label class="relative cursor-pointer">
                            <input type="radio" x-model="assignType" value="resident" class="sr-only peer">
                            <div class="px-4 py-2 border-2 rounded-lg text-sm font-medium transition-all peer-checked:border-pink-500 peer-checked:bg-pink-900/30 peer-checked:text-pink-300 border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-600">
                                Residente
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" x-model="assignType" value="apartment" class="sr-only peer">
                            <div class="px-4 py-2 border-2 rounded-lg text-sm font-medium transition-all peer-checked:border-pink-500 peer-checked:bg-pink-900/30 peer-checked:text-pink-300 border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-600">
                                Apartamento/Casa
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" x-model="assignType" value="host" class="sr-only peer">
                            <div class="px-4 py-2 border-2 rounded-lg text-sm font-medium transition-all peer-checked:border-pink-500 peer-checked:bg-pink-900/30 peer-checked:text-pink-300 border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-600">
                                Usuario del sistema
                            </div>
                        </label>
                    </div>

                    <form method="POST" action="{{ route('access.correspondence.store') }}">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div x-show="assignType === 'resident'">
                                <label class="block text-sm font-medium text-slate-300">Residente</label>
                                <select name="resident_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Seleccionar...</option>
                                    @foreach($residents as $r)
                                    <option value="{{ $r->id }}" {{ old('resident_id') == $r->id ? 'selected' : '' }}>{{ $r->full_name }} ({{ $r->document_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="assignType === 'apartment'">
                                <label class="block text-sm font-medium text-slate-300">Apartamento/Casa</label>
                                <select name="housing_unit_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Seleccionar...</option>
                                    @foreach($housingUnits as $hu)
                                    <option value="{{ $hu->id }}" {{ old('housing_unit_id') == $hu->id ? 'selected' : '' }}>{{ $hu->full_label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="assignType === 'host'">
                                <label class="block text-sm font-medium text-slate-300">Destinatario (usuario)</label>
                                <select name="host_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Seleccionar...</option>
                                    @foreach($hosts as $host)
                                    <option value="{{ $host->id }}" {{ old('host_id') == $host->id ? 'selected' : '' }}>{{ $host->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Ubicación</label>
                                <select name="location_id" required class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Seleccionar...</option>
                                    @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Tipo de Paquete</label>
                                <select name="package_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="documento">📄 Documento</option>
                                    <option value="sobre">✉️ Sobre</option>
                                    <option value="caja">📦 Caja</option>
                                    <option value="otro">📎 Otro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Transportadora</label>
                                <input type="text" name="carrier" value="{{ old('carrier') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ej: Servientrega, Envía, etc.">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Guía</label>
                                <input type="text" name="courier_guide" value="{{ old('courier_guide') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Número de guía">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300">Visitante (quien dejó)</label>
                                <select name="visitor_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">N/A</option>
                                    @foreach($visitors as $v)
                                    <option value="{{ $v->id }}" {{ old('visitor_id') == $v->id ? 'selected' : '' }}>{{ $v->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-300">Notas</label>
                            <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" placeholder="Observaciones adicionales...">{{ old('notes') }}</textarea>
                        </div>
                        <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-800">
                            <a href="{{ route('access.correspondence.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Cancelar</a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-pink-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-pink-700 transition-colors shadow-sm">
                                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Registrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-access-layout>
