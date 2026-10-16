@php
    $d = $defaults;
    $ta = 'w-full px-3 py-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600 focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30';
@endphp

<x-admin-layout title="Cotización comercial">
    <div
        class="max-w-3xl space-y-4"
        x-data="commercialQuoteForm(@js([
            'catalog' => $catalog,
            'manual' => (int) old('manual_seats', $d['manual_seats']),
            'hardware' => (int) old('hardware_seats', $d['hardware_seats']),
            'supervision' => (int) old('supervision_seats', $d['supervision_seats']),
            'indexing' => (int) old('indexing_seats', $d['indexing_seats']),
            'observatory' => (int) old('observatory_seats', $d['observatory_seats']),
            'cycle' => old('billing_cycle', $d['billing_cycle']),
            'enforceAvailability' => false,
            'commercialAmount' => old('commercial_amount', $d['commercial_amount']),
        ]))"
        x-effect="syncCommercial()"
    >
        <x-ui.button variant="secondary" :href="route('admin.commercial.index')" size="sm">← Comercial</x-ui.button>

        <p class="text-sm text-slate-400">
            Arma el plan, revisa el catálogo y define el total comercial de esta cotización. El PDF muestra ambos.
        </p>

        <form
            method="POST"
            action="{{ route('admin.commercial.quotes.pdf') }}"
            class="space-y-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4"
            @submit="if (isEmpty) { $event.preventDefault() }"
        >
            @csrf
            @include('modules.admin.commercial.partials.document-fields', ['defaults' => $d])

            <div class="border-t border-slate-800 pt-4 space-y-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Plan cotizado</p>
                <x-plans.seat-fields :cycle-options="$cycleOptions" id-prefix="quote" />
            </div>

            <div class="border-t border-slate-800 pt-4 space-y-3">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="flex-1 min-w-[12rem]">
                        <x-ui.label for="commercial_amount">Total comercial (esta cotización)</x-ui.label>
                        <x-ui.input
                            type="number"
                            id="commercial_amount"
                            name="commercial_amount"
                            min="0"
                            step="1"
                            accent="platform"
                            x-model="commercialAmount"
                            @input="commercialTouched = true"
                            placeholder="Igual al catálogo si se deja vacío"
                        />
                        <x-ui.field-error :messages="$errors->get('commercial_amount')" />
                    </div>
                    <button
                        type="button"
                        class="h-9 px-3 text-sm rounded-lg border border-slate-700 text-slate-300 hover:border-emerald-500/50 hover:text-emerald-300"
                        @click="useCatalog()"
                    >
                        Usar precio catálogo
                    </button>
                </div>
                <p class="text-xs text-slate-500">
                    Catálogo actual:
                    <span class="tabular-nums text-slate-300" x-text="money(total)"></span>
                    <span x-text="cycle === 'annual' ? '/año' : '/mes'"></span>
                </p>
                <div>
                    <x-ui.label for="commercial_note">Nota comercial (opcional)</x-ui.label>
                    <textarea id="commercial_note" name="commercial_note" rows="2" class="{{ $ta }}" placeholder="Ej. Alianza, vigencia 15 días, descuento por volumen…">{{ old('commercial_note', $d['commercial_note']) }}</textarea>
                    <x-ui.field-error :messages="$errors->get('commercial_note')" />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-ui.button type="submit" variant="platform" x-bind:disabled="isEmpty">Descargar PDF</x-ui.button>
                <a href="{{ route('admin.commercial.index') }}" class="text-sm text-slate-400 hover:text-white">Cancelar</a>
            </div>
        </form>
    </div>
</x-admin-layout>
