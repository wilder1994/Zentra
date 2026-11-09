<x-layouts.resident-app title="Nueva autorización">
    <form method="POST" action="{{ route('resident-app.authorizations.store') }}" class="space-y-3 pb-4"
          x-data="{ subject: @js(old('subject', 'person')) }">
        @csrf
        <a href="{{ route('resident-app.authorizations') }}" class="text-xs text-slate-400">← Mis autorizaciones</a>
        <p class="text-xs text-slate-500">Destino: {{ $appUser->member?->structure?->full_path }}</p>

        <div>
            <p class="mb-2 text-xs text-slate-400">¿Qué autorizas?</p>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm"
                       :class="subject === 'person' ? 'border-teal-600 bg-teal-950/40 text-white' : 'border-slate-700 text-slate-400'">
                    <input type="radio" name="subject" value="person" class="sr-only" x-model="subject">
                    Persona
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm"
                       :class="subject === 'vehicle' ? 'border-teal-600 bg-teal-950/40 text-white' : 'border-slate-700 text-slate-400'">
                    <input type="radio" name="subject" value="vehicle" class="sr-only" x-model="subject">
                    Vehículo
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs text-slate-400 mb-1">Tipo</label>
            <select name="type" required class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', 'visit') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Desde</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', now()->toDateString()) }}" required
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Hasta</label>
                <input type="date" name="valid_until" value="{{ old('valid_until', now()->toDateString()) }}" required
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Hora desde</label>
                <input type="time" name="entry_time_from" value="{{ old('entry_time_from') }}"
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Hora hasta</label>
                <input type="time" name="entry_time_until" value="{{ old('entry_time_until') }}"
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3 space-y-3">
            <p class="text-xs font-medium text-slate-300" x-text="subject === 'vehicle' ? 'Conductor' : 'Visitante'"></p>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Tipo doc.</label>
                    <select name="guest_document_type" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                        <option value="CC" @selected(old('guest_document_type', 'CC') === 'CC')>CC</option>
                        <option value="CE" @selected(old('guest_document_type') === 'CE')>CE</option>
                        <option value="PA" @selected(old('guest_document_type') === 'PA')>PA</option>
                        <option value="TI" @selected(old('guest_document_type') === 'TI')>TI</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Documento</label>
                    <input type="text" name="guest_document_number" value="{{ old('guest_document_number') }}" required
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Nombres</label>
                    <input type="text" name="guest_first_name" value="{{ old('guest_first_name') }}" required
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Apellidos</label>
                    <input type="text" name="guest_last_name" value="{{ old('guest_last_name') }}" required
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                </div>
            </div>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">Teléfono</label>
                <input type="text" name="guest_phone" value="{{ old('guest_phone') }}"
                       class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3 space-y-3" x-show="subject === 'vehicle'" x-cloak>
            <p class="text-xs font-medium text-slate-300">Vehículo</p>
            <div>
                <label class="block text-[11px] text-slate-500 mb-1">Placa</label>
                <input type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" maxlength="15"
                       :required="subject === 'vehicle'"
                       class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm uppercase text-white">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Marca</label>
                    <input type="text" name="vehicle_brand" value="{{ old('vehicle_brand') }}"
                           :required="subject === 'vehicle'"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Modelo</label>
                    <input type="text" name="vehicle_model" value="{{ old('vehicle_model') }}"
                           :required="subject === 'vehicle'"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Color</label>
                    <input type="text" name="vehicle_color" value="{{ old('vehicle_color') }}"
                           :required="subject === 'vehicle'"
                           class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Tipo</label>
                    <select name="vehicle_type" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white"
                            :required="subject === 'vehicle'">
                        @foreach (['carro' => 'Carro', 'moto' => 'Moto', 'camioneta' => 'Camioneta', 'camion' => 'Camión', 'otro' => 'Otro'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('vehicle_type', 'carro') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs text-slate-400 mb-1">Notas</label>
            <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">{{ old('notes') }}</textarea>
        </div>

        @if ($errors->any())
            <ul class="rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-xs text-red-200 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <button type="submit" class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white">Crear autorización</button>
    </form>
</x-layouts.resident-app>
