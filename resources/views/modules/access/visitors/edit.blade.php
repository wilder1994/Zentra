<x-access-layout title="Editar Visitante">
    <div class="porteria-form bg-slate-900 rounded-xl border border-slate-800 p-6 max-w-3xl" x-data="{
        regDoc: @js(old('document_number', $visitor->document_number)),
        regFirst: @js(old('first_name', $visitor->first_name)),
        regLast: @js(old('last_name', $visitor->last_name)),
        regPhone: @js(old('phone', $visitor->phone)),
        regEmail: @js(old('email', $visitor->email)),
        regCompany: @js(old('company', $visitor->company)),
        regType: @js(old('visitor_type', $visitor->visitor_type ?: 'persona')),
    }">
        <form method="POST" action="{{ route('access.visitors.update', $visitor) }}" class="space-y-4">
            @csrf @method('PATCH')

            @include('modules.access.partials.visitor-profile-fields', ['prefix' => 'reg'])

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300">Nacionalidad</label>
                    <input type="text" name="nationality" value="{{ old('nationality', $visitor->nationality) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300">Fecha nacimiento</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $visitor->birth_date?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300">Notas</label>
                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white">{{ old('notes', $visitor->notes) }}</textarea>
            </div>

            @if($visitor->photo_path)
                <div class="flex items-center gap-3">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($visitor->photo_path) }}" alt="" class="h-20 w-20 rounded-lg object-cover">
                    <p class="text-xs text-slate-500">Foto actual. Puedes actualizarla abajo (opcional).</p>
                </div>
            @endif

            @include('modules.access.partials.camera-capture', [
                'name' => 'person_photo_data',
                'label' => 'Actualizar foto',
                'required' => false,
                'hint' => 'Opcional si ya tiene foto',
            ])

            <div class="flex justify-end gap-3">
                <a href="{{ route('access.visitors.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-md font-semibold text-xs text-slate-300 uppercase tracking-widest hover:bg-slate-700">Cancelar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Actualizar</button>
            </div>
        </form>
    </div>
</x-access-layout>
