@can('platform.companies.manage')
    <div
        x-show="changeOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="fixed inset-0 bg-black/60" @click="changeOpen = false"></div>
            <div class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 p-5" @click.stop>
                <h3 class="text-sm font-semibold text-white">Programar cambio de plan</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Se cobra el nuevo plan ahora; el cambio aplica el
                    {{ $company->package_ends_at?->format('d/m/Y') ?? 'fin del periodo' }}.
                </p>
                <form
                    method="POST"
                    action="{{ route('admin.companies.package.schedule', $company) }}"
                    enctype="multipart/form-data"
                    class="mt-4 space-y-3"
                    x-data="planPicker(@js([
                        'catalog' => $planCatalog,
                        'manual' => (int) old('manual_seats', $company->accessSeats()->manual),
                        'hardware' => (int) old('hardware_seats', $company->accessSeats()->hardware),
                        'supervision' => (int) old('supervision_seats', $company->supervision_package_sku?->size() ?? 0),
                        'cycle' => old('billing_cycle', $company->billing_cycle?->value ?? 'monthly'),
                            'enforceAvailability' => false,
                        'initialCycle' => $company->billing_cycle?->value ?? 'monthly',
                        'blockUnchanged' => true,
                    ]))"
                >
                    @csrf
                    <input type="hidden" name="action_context" value="schedule" />
                    <x-plans.seat-fields :cycle-options="$cycleOptions" id-prefix="admin_schedule" />
                    <div>
                        <x-ui.label for="schedule_reference">Referencia de consignación</x-ui.label>
                        <x-ui.input id="schedule_reference" name="reference" accent="platform" value="{{ old('reference') }}" required />
                        <x-ui.field-error name="reference" />
                    </div>
                    <div>
                        <x-ui.label for="schedule_proof">Soporte de pago</x-ui.label>
                        <input
                            id="schedule_proof"
                            type="file"
                            name="proof"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            required
                            class="block w-full text-xs text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-600 file:px-3 file:py-2 file:text-xs file:font-medium file:text-white"
                        />
                        <x-ui.field-error name="proof" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" size="sm" @click="changeOpen = false">Volver</x-ui.button>
                        <x-ui.button type="submit" variant="platform" size="sm" x-bind:disabled="!canSubmit">Pagar y programar</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan
