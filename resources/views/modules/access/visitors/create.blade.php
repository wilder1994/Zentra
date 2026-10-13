<x-access-layout title="Nuevo Visitante">
    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
        <form method="POST" action="{{ route('access.visitors.store') }}" x-data="{
            scanBuffer: '',
            handleScan() {
                let parts = this.scanBuffer.trim().split(/[|\t]/);
                if (parts.length < 5) return;
                let numero = parts[0], apellido1 = parts[1] || '', apellido2 = parts[2] || '';
                let nombre1 = parts[3] || '', nombre2 = parts[4] || '';
                let fechaRaw = parts[6] || '';
                this.scanBuffer = '';
                $el.querySelector('[name=document_number]').value = numero;
                $el.querySelector('[name=first_name]').value = (nombre1 + ' ' + nombre2).trim();
                $el.querySelector('[name=last_name]').value = (apellido1 + ' ' + apellido2).trim();
                if (fechaRaw) {
                    let ds = fechaRaw.replace(/\//g, '');
                    if (ds.length >= 8) {
                        $el.querySelector('[name=birth_date]').value = ds.substring(0, 4) + '-' + ds.substring(4, 6) + '-' + ds.substring(6, 8);
                    }
                }
                $el.querySelector('[name=document_type]').value = 'CC';
                $el.querySelector('[name=visitor_type]').value = 'persona';
            }
        }">
            @csrf

            <div class="mb-6 p-4 bg-slate-950/60 border border-slate-700/50 rounded-lg">
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Escáner QR Cédula</label>
                @include('modules.access.partials.qr-scan-field')
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300">Tipo Documento</label>
                    <select name="document_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(['CC','NIT','CE','Pasaporte'] as $dt)
                        <option value="{{ $dt }}" {{ old('document_type') == $dt ? 'selected' : '' }}>{{ $dt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Número Documento</label>
                    <input type="text" name="document_number" value="{{ old('document_number') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                    @error('document_number') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Nombres</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Apellidos</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Teléfono</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Empresa</label>
                    <input type="text" name="company" value="{{ old('company') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Tipo Visitante</label>
                    <select name="visitor_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="persona">Persona</option>
                        <option value="contratista">Contratista</option>
                        <option value="proveedor">Proveedor</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Nacionalidad</label>
                    <input type="text" name="nationality" value="{{ old('nationality', 'Colombiana') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Fecha Nacimiento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-300">Notas</label>
                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <a href="{{ route('access.visitors.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Guardar</button>
            </div>
        </form>
    </div>
</x-access-layout>
