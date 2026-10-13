<x-access-layout title="Nuevo Visitante">
    <p class="mb-4 text-sm text-slate-400">Pre-registro desde administración o portería. La foto es opcional aquí; si llega sin foto, se pedirá al ingresar.</p>

    <div class="porteria-form bg-slate-900 rounded-xl border border-slate-800 p-6 max-w-3xl" x-data="{
        scanBuffer: '',
        regDoc: @js(old('document_number', '')),
        regFirst: @js(old('first_name', '')),
        regLast: @js(old('last_name', '')),
        regPhone: @js(old('phone', '')),
        regEmail: @js(old('email', '')),
        regCompany: @js(old('company', '')),
        regType: @js(old('visitor_type', 'persona')),
        handleScan() {
            let parts = this.scanBuffer.trim().split(/[|\t]/);
            if (parts.length < 5) return;
            let numero = parts[0], apellido1 = parts[1] || '', apellido2 = parts[2] || '';
            let nombre1 = parts[3] || '', nombre2 = parts[4] || '';
            let fechaRaw = parts[6] || '';
            this.scanBuffer = '';
            this.regDoc = numero;
            this.regFirst = (nombre1 + ' ' + nombre2).trim();
            this.regLast = (apellido1 + ' ' + apellido2).trim();
            if (fechaRaw) {
                let ds = fechaRaw.replace(/\//g, '');
                if (ds.length >= 8) {
                    $el.querySelector('[name=birth_date]').value = ds.substring(0, 4) + '-' + ds.substring(4, 6) + '-' + ds.substring(6, 8);
                }
            }
            $el.querySelector('[name=document_type]').value = 'CC';
        }
    }">
        <form method="POST" action="{{ route('access.visitors.store') }}" class="space-y-4">
            @csrf

            <div class="p-4 bg-slate-950/60 border border-slate-700/50 rounded-lg">
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Escáner QR Cédula</label>
                @include('modules.access.partials.qr-scan-field')
            </div>

            @include('modules.access.partials.visitor-profile-fields', ['prefix' => 'reg'])

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300">Nacionalidad</label>
                    <input type="text" name="nationality" value="{{ old('nationality', 'Colombiana') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Fecha nacimiento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300">Notas</label>
                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>

            @include('modules.access.partials.camera-capture', [
                'name' => 'person_photo_data',
                'label' => 'Foto',
                'required' => false,
                'hint' => 'Opcional en pre-registro. Si el visitante está presente, tómalo ahora.',
            ])

            <div class="flex justify-end gap-3">
                <a href="{{ route('access.visitors.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Guardar ficha</button>
            </div>
        </form>
    </div>
</x-access-layout>
