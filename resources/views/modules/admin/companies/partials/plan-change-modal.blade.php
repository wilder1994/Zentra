@can('platform.companies.manage')
    @php
        $seats = $company->accessSeats();
        $planFormConfig = [
            'catalog' => $planCatalog,
            'scope' => 'admin-plan',
            'cycle' => old('billing_cycle', $company->billing_cycle?->value ?? 'monthly'),
            'items' => [
                'access' => (int) old('manual_seats', $seats->manual),
                'hardware' => (int) old('hardware_seats', $seats->hardware),
                'supervision' => (int) old('supervision_seats', $company->supervision_package_sku?->size() ?? 0),
                'indexing' => (int) old('indexing_seats', $company->indexing_seats ?? 0),
                'observatory' => (int) old('observatory_seats', $company->observatory_seats ?? 0),
            ],
        ];
    @endphp
    <div x-show="planOpen" x-cloak class="fixed inset-0 z-50" style="display: none;" @keydown.escape.window="planOpen = false">
        <div class="absolute inset-0 bg-black/70" @click="planOpen = false"></div>
        <form method="POST" action="{{ route('admin.companies.plan.apply', $company) }}"
            class="relative mx-auto my-4 flex h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-6xl flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-950"
            x-data="planForm(@js($planFormConfig))" @click.stop>
            @csrf
            <input type="hidden" name="manual_seats" :value="items.access || 0">
            <input type="hidden" name="hardware_seats" :value="items.hardware || 0">
            <input type="hidden" name="supervision_seats" :value="items.supervision || 0">
            <input type="hidden" name="indexing_seats" :value="items.indexing || 0">
            <input type="hidden" name="observatory_seats" :value="items.observatory || 0">
            <input type="hidden" name="billing_cycle" :value="cycle">

            <div class="flex items-start justify-between gap-3 border-b border-slate-800 px-5 py-4">
                <div>
                    <h3 class="text-base font-semibold text-white">Cambiar plan · {{ $company->displayName() }}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Marca productos y ajusta cantidades; cada producto salta solo al plan de su cantidad. Aquí se pueden usar planes que no están a la venta.</p>
                </div>
                <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white" @click="planOpen = false" aria-label="Cerrar">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5">
                @if ($errors->hasAny(['manual_seats', 'hardware_seats', 'supervision_seats', 'indexing_seats', 'observatory_seats', 'billing_cycle', 'apply_when', 'effective_on']))
                    <div class="mb-4 rounded-lg border border-rose-800/50 bg-rose-950/30 px-3 py-2 text-xs text-rose-200">
                        @foreach (['manual_seats', 'hardware_seats', 'supervision_seats', 'indexing_seats', 'observatory_seats', 'billing_cycle', 'apply_when', 'effective_on'] as $field)
                            @error($field)<p>{{ $message }}</p>@enderror
                        @endforeach
                    </div>
                @endif
                @include('modules.public.plans.partials.metal-cards', ['scope' => 'admin-plan'])
            </div>

            <div class="border-t border-slate-800 bg-slate-900/95 px-5 py-3 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm min-w-0">
                        <span class="plan-name-chip" :data-metal="identity.key" x-show="keys.length" x-text="identity.name"></span>
                        <span class="text-slate-500" x-show="!keys.length">Sin productos</span>
                        <template x-for="key in keys" :key="key">
                            <span class="ui-chip" x-text="labels[key] + ' ' + (metalOf(key)?.label || '') + ' ' + Number(items[key]).toLocaleString('es-CO')"></span>
                        </template>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-slate-400">
                            <span class="text-slate-500 line-through tabular-nums" x-show="quote.savings > 0" x-text="money(quote.listAmount)"></span>
                            <span class="text-lg font-bold text-white tabular-nums" x-text="money(quote.amount) + (cycle === 'annual' ? '/año' : '/mes')"></span>
                        </p>
                        <p class="text-xs text-slate-500">Hoy paga {{ $fmt($company->contractedAmount()) }} {{ $company->billing_cycle?->value === 'annual' ? '/año' : '/mes' }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-200">
                        <div class="inline-flex rounded-lg border border-slate-700 p-0.5">
                            <button type="button" class="h-8 rounded-md px-3 text-xs font-medium" :class="cycle === 'monthly' ? 'bg-cyan-600 text-white' : 'text-slate-300'" @click="setCycle('monthly')">Mensual</button>
                            <button type="button" class="h-8 rounded-md px-3 text-xs font-medium" :class="cycle === 'annual' ? 'bg-cyan-600 text-white' : 'text-slate-300'" @click="setCycle('annual')">Anual</button>
                        </div>
                        <span class="text-xs text-slate-500">Aplica:</span>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="apply_when" value="now" x-model="applyWhen"> Ya</label>
                        <label class="inline-flex items-center gap-1.5">
                            <input type="radio" name="apply_when" value="on_date" x-model="applyWhen"> El día
                            <input type="date" name="effective_on" value="{{ old('effective_on') }}" class="h-8 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white" @focus="applyWhen = 'on_date'">
                        </label>
                        <label class="inline-flex items-center gap-1.5 {{ $company->package_ends_at ? '' : 'opacity-40' }}">
                            <input type="radio" name="apply_when" value="period_end" x-model="applyWhen" @disabled(! $company->package_ends_at)>
                            Al corte{{ $company->package_ends_at ? ' ('.$company->package_ends_at->format('d/m/Y').')' : '' }}
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button type="button" variant="secondary" size="sm" @click="reset()" x-show="changed">Deshacer</x-ui.button>
                        <x-ui.button type="submit" size="sm" x-bind:disabled="!keys.length">Aplicar plan</x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endcan
