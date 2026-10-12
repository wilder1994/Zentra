@can('platform.companies.manage')
    <div x-show="lockOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" @keydown.escape.window="lockOpen = false">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/60" @click="lockOpen = false"></div>
            <div class="relative w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5" @click.stop>
                <h3 class="text-sm font-semibold text-white">Precio pactado</h3>
                <p class="text-xs text-slate-500 mt-1">Si está activo, los cambios de catálogo y el incremento anual no tocan a esta empresa.</p>
                <form method="POST" action="{{ route('admin.companies.price-lock.update', $company) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <label class="flex items-center gap-2 text-sm text-slate-200">
                        <input type="hidden" name="price_locked" value="0">
                        <input type="checkbox" name="price_locked" value="1" @checked(old('price_locked', $company->price_locked))>
                        Precio pactado activo
                    </label>
                    <div>
                        <x-ui.label for="price_locked_until">Hasta (opcional)</x-ui.label>
                        <input type="date" id="price_locked_until" name="price_locked_until" value="{{ old('price_locked_until', $company->price_locked_until?->format('Y-m-d')) }}" class="w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                        <x-ui.field-error name="price_locked_until" />
                    </div>
                    <div>
                        <x-ui.label for="price_lock_note">Nota del acuerdo</x-ui.label>
                        <input type="text" id="price_lock_note" name="price_lock_note" maxlength="255" value="{{ old('price_lock_note', $company->price_lock_note) }}" class="w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                        <x-ui.field-error name="price_lock_note" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" size="sm" @click="lockOpen = false">Volver</x-ui.button>
                        <x-ui.button type="submit" size="sm">Guardar</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan
