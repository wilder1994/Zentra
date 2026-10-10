@php
    use App\Enums\CompanyPackageSku;
    use App\Enums\ManualPaymentIntent;
    use App\Enums\PaymentStatus;

    $fmt = fn (?float $n) => $n !== null ? '$'.number_format($n, 0, ',', '.') : '—';
    $statusLabel = $company->subscription_status?->label() ?? '—';
    $scheduledSkuLabel = CompanyPackageSku::tryFrom((string) $company->scheduled_package_sku)?->label()
        ?? $company->scheduled_package_sku;
@endphp

<x-company-layout title="Facturación">
    <div
        class="w-full space-y-4"
        x-data="{
            cancelOpen: {{ $errors->has('reason') ? 'true' : 'false' }},
            changeOpen: {{ $errors->has('package_sku') || $errors->has('billing_cycle') ? 'true' : 'false' }},
            reactivateOpen: false
        }"
    >
        @if ($company->hasPendingCancellation())
            <div class="rounded-lg border border-amber-800/50 bg-amber-950/30 px-4 py-3 text-sm text-amber-100">
                Cancelación programada
                @if ($company->package_ends_at)
                    · acceso hasta {{ $company->package_ends_at->format('d/m/Y') }}
                @endif
                @if ($company->cancellation_reason)
                    <span class="block text-xs text-amber-200/80 mt-1">Motivo: {{ $company->cancellation_reason }}</span>
                @endif
            </div>
        @endif

        @if ($company->hasScheduledPackageChange())
            <div class="rounded-lg border border-indigo-800/50 bg-indigo-950/30 px-4 py-3 text-sm text-indigo-100">
                Cambio programado
                @if ($company->scheduled_package_sku)
                    · Accesos <strong>{{ $scheduledSkuLabel }}</strong>
                    ({{ $company->scheduled_billing_cycle }})
                @endif
                @if ($company->scheduled_supervision_package_sku)
                    · Supervisión
                    <strong>
                        {{ $company->scheduled_supervision_package_sku === 'none'
                            ? 'sin Supervisión'
                            : (\App\Enums\SupervisionPackageSku::tryFrom($company->scheduled_supervision_package_sku)?->label() ?? $company->scheduled_supervision_package_sku) }}
                    </strong>
                @endif
                @if ($company->scheduled_change_at)
                    · aplica el {{ $company->scheduled_change_at->format('d/m/Y') }}
                @endif
            </div>
        @endif

        <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 sm:p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide">Membresía</p>
                    <h2 class="text-base font-semibold text-white mt-0.5">{{ $company->displayName() }}</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ $company->packageLabel() }}
                        @if ($company->supervision_package_sku)
                            · {{ $company->supervision_package_sku->label() }}
                        @else
                            · Sin Supervisión
                        @endif
                        · {{ $company->billing_cycle?->label() ?? '—' }}
                        · {{ $statusLabel }}
                    </p>
                </div>
                <div class="text-sm sm:text-right">
                    <p class="text-xs text-slate-500">Monto contratado</p>
                    <p class="text-lg font-semibold text-white tabular-nums">{{ $fmt($company->contractedAmount()) }}</p>
                    @if ($company->package_ends_at)
                        <p class="text-xs text-slate-500 mt-1">
                            Corte {{ $company->package_ends_at->format('d/m/Y') }}
                            @if ($isUpToDate)
                                <span class="text-emerald-400">· al día</span>
                            @else
                                <span class="text-amber-300">· vencido</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            <div class="rounded-lg border border-indigo-800/40 bg-indigo-950/20 p-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="text-xs text-slate-500 uppercase">Mensual (ref.)</p>
                    <p class="text-base font-semibold text-white tabular-nums">{{ $fmt($quote->priceMonthly) }}</p>
                </div>
                <div>
                    <p class="text-xs text-emerald-400/80 uppercase">Anual (recomendado)</p>
                    <p class="text-base font-semibold text-emerald-300 tabular-nums">{{ $fmt($quoteAnnual->priceAnnual) }}</p>
                    <p class="text-xs text-emerald-400/70">Ahorras {{ $fmt($quoteAnnual->annualSavings) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase">Desc. volumen</p>
                    <p class="text-base font-semibold text-white">−{{ $quote->volumeDiscountLabel() }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('company.billing.supervision.update') }}" class="rounded-lg border border-amber-800/40 bg-amber-950/10 p-4 space-y-3">
                @csrf
                <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <div class="flex-1">
                        <x-ui.label for="supervision_package_sku">Supervisión</x-ui.label>
                        <select id="supervision_package_sku" name="supervision_package_sku" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                            <option value="">Sin Supervisión</option>
                            @foreach ($supervisionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('supervision_package_sku', $company->supervision_package_sku?->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Se programa al corte. El paquete de 1 Accesos no admite Supervisión. Quitar no cobra.</p>
                    </div>
                    <x-ui.button type="submit" variant="secondary" size="sm">Programar Supervisión</x-ui.button>
                </div>
            </form>

            @if (! $hasAcceptance)
                <p class="text-sm text-amber-300 rounded-lg border border-amber-800/50 bg-amber-900/20 px-3 py-2">
                    Pendiente la aceptación contractual. Contacta a soporte Zentra para habilitar pagos online.
                </p>
            @endif

            @if ($pendingPayment)
                <div class="rounded-lg border border-indigo-700/50 bg-indigo-900/20 px-3 py-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-indigo-200">Tienes un pago online pendiente de confirmación.</p>
                    <x-ui.button variant="primary" size="sm" :href="route('billing.checkout.show', $pendingPayment)">
                        Continuar checkout
                    </x-ui.button>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-800">
                @if ($hasAcceptance && ! $pendingPayment)
                    <form method="POST" action="{{ route('company.billing.checkout') }}" class="inline">
                        @csrf
                        <input type="hidden" name="intent" value="{{ $defaultCheckoutIntent }}" />
                        <x-ui.button type="submit" variant="primary" size="md">
                            @if ($defaultCheckoutIntent === ManualPaymentIntent::Reactivate->value)
                                Reactivar con pago online
                            @elseif ($defaultCheckoutIntent === ManualPaymentIntent::Anticipate->value)
                                Anticipar / renovar online
                            @else
                                Pagar online
                            @endif
                        </x-ui.button>
                    </form>
                @elseif (! $hasAcceptance)
                    <x-ui.button type="button" variant="secondary" size="md" disabled class="opacity-40 cursor-not-allowed">
                        Pagar online
                    </x-ui.button>
                @endif

                @if ($isUpToDate && ! $company->hasPendingCancellation())
                    <x-ui.button type="button" variant="secondary" size="md" @click="cancelOpen = true">
                        Cancelar membresía
                    </x-ui.button>
                @endif

                @if ($isUpToDate && $hasAcceptance)
                    <x-ui.button type="button" variant="secondary" size="md" @click="changeOpen = true">
                        Programar cambio
                    </x-ui.button>
                @endif

                @if ($company->canUndoCancellation())
                    <x-ui.button type="button" variant="secondary" size="md" @click="reactivateOpen = true">
                        Reactivar membresía
                    </x-ui.button>
                @elseif ($company->needsPaidReactivation())
                    {{-- CTA cubierto por «Reactivar con pago online» --}}
                @else
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="md"
                        disabled
                        class="opacity-40 cursor-not-allowed hover:bg-transparent"
                        title="Disponible cuando la membresía esté cancelada"
                    >
                        Reactivar membresía
                    </x-ui.button>
                @endif
            </div>
        </section>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Pagos completados</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $completedCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Pendientes</p>
                <p class="mt-1 text-lg font-semibold {{ $pendingCount > 0 ? 'text-amber-300' : 'text-white' }} tabular-nums">{{ $pendingCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Facturas</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $invoicesCount }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Próximo corte</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">
                    {{ $company->package_ends_at?->format('d/m/Y') ?? '—' }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <section class="xl:col-span-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4 flex flex-col min-h-0 max-h-[28rem]">
                <h3 class="text-sm font-semibold text-white shrink-0">Línea de tiempo</h3>
                <p class="text-xs text-slate-500 mt-0.5 shrink-0">Eventos de tu ciclo comercial.</p>
                <div class="mt-3 flex-1 min-h-0 overflow-y-auto space-y-2">
                    @forelse ($timeline as $event)
                        <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-3 py-2">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-slate-200">{{ $event->event_type->label() }}</p>
                                <time class="text-[10px] text-slate-500 shrink-0">{{ $event->occurred_at->format('d/m/Y H:i') }}</time>
                            </div>
                            @if ($event->title)
                                <p class="text-xs text-slate-400 mt-1">{{ $event->title }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin eventos registrados.</p>
                    @endforelse
                </div>
            </section>

            <section class="xl:col-span-8 rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-white">Historial de pagos</h3>
                <p class="text-xs text-slate-500">Incluye pagos online y consignaciones registradas por soporte.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="pb-2 text-left font-medium">Fecha</th>
                                <th class="pb-2 text-right font-medium">Monto</th>
                                <th class="pb-2 text-left font-medium">Método</th>
                                <th class="pb-2 text-left font-medium">Estado</th>
                                <th class="pb-2 text-left font-medium">Referencia</th>
                                <th class="pb-2 text-left font-medium">Factura</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse ($payments as $payment)
                                @php $invoice = $invoiceByPaymentId->get((int) $payment->id); @endphp
                                <tr>
                                    <td class="py-2 text-slate-300 text-xs">
                                        {{ ($payment->paid_at ?? $payment->created_at)?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="py-2 text-right text-slate-200 tabular-nums">{{ $fmt((float) $payment->amount) }}</td>
                                    <td class="py-2 text-slate-400">{{ $payment->method?->label() ?? '—' }}</td>
                                    <td class="py-2">
                                        <span @class([
                                            'text-xs',
                                            'text-emerald-400' => $payment->status === PaymentStatus::Completed,
                                            'text-amber-300' => $payment->status === PaymentStatus::Pending,
                                            'text-slate-500' => ! in_array($payment->status, [PaymentStatus::Completed, PaymentStatus::Pending], true),
                                        ])>
                                            {{ $payment->status?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-xs font-mono text-slate-500">{{ $payment->reference ?: '—' }}</td>
                                    <td class="py-2 text-xs font-mono text-slate-500">{{ $invoice?->reference_number ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-500">Sin pagos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
            <h3 class="text-sm font-semibold text-white">Facturas</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-2 text-left font-medium">Título</th>
                            <th class="pb-2 text-left font-medium">Referencia</th>
                            <th class="pb-2 text-right font-medium">Importe</th>
                            <th class="pb-2 text-left font-medium">Emitido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($invoices as $doc)
                            <tr>
                                <td class="py-2 text-slate-200">
                                    {{ $doc->title }}
                                    @if ($doc->is_demo)
                                        <span class="ml-1 text-[10px] uppercase text-amber-400">demo</span>
                                    @endif
                                </td>
                                <td class="py-2 text-slate-500 font-mono text-xs">{{ $doc->reference_number ?? '—' }}</td>
                                <td class="py-2 text-right text-slate-300 tabular-nums">{{ $fmt($doc->amount !== null ? (float) $doc->amount : null) }}</td>
                                <td class="py-2 text-slate-500 text-xs">{{ $doc->issued_at?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-500">Sin facturas emitidas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Cancel modal --}}
        <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div class="fixed inset-0 bg-black/60" @click="cancelOpen = false"></div>
                <div class="relative w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5" @click.stop>
                    <h3 class="text-sm font-semibold text-white">Cancelar membresía</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Seguirán usando el servicio hasta
                        {{ $company->package_ends_at?->format('d/m/Y') ?? 'el fin del periodo' }}.
                    </p>
                    <form method="POST" action="{{ route('company.billing.membership.cancel') }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <x-ui.label for="cancel_reason">Motivo</x-ui.label>
                            <textarea
                                id="cancel_reason"
                                name="reason"
                                rows="3"
                                required
                                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"
                            >{{ old('reason') }}</textarea>
                            <x-ui.field-error name="reason" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" size="sm" @click="cancelOpen = false">Volver</x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">Confirmar cancelación</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Reactivate (undo cancel) modal --}}
        <div x-show="reactivateOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div class="fixed inset-0 bg-black/60" @click="reactivateOpen = false"></div>
                <div class="relative w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5" @click.stop>
                    <h3 class="text-sm font-semibold text-white">Reactivar membresía</h3>
                    <p class="text-sm text-slate-300 mt-3 leading-relaxed">
                        Su membresía se reactivará y seguirán usando el sistema con normalidad
                        @if ($company->package_ends_at)
                            hasta el <strong class="text-white">{{ $company->package_ends_at->format('d/m/Y') }}</strong>
                        @endif.
                    </p>
                    <p class="text-xs text-slate-500 mt-3 leading-relaxed">
                        Después de la fecha de corte deberán pagar nuevamente el plan vigente
                        ({{ $company->package_sku?->label() ?? 'paquete actual' }}
                        · {{ $company->billing_cycle?->label() ?? 'ciclo' }}
                        · {{ $fmt($company->contractedAmount()) }}).
                    </p>
                    <form method="POST" action="{{ route('company.billing.membership.undo-cancel') }}" class="mt-5">
                        @csrf
                        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" size="sm" @click="reactivateOpen = false">Volver</x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">Confirmar reactivación</x-ui.button>
                        </div>
                    </form>
                    <div class="mt-4 pt-4 border-t border-slate-800">
                        <p class="text-xs text-slate-500 mb-2">¿Prefieren otro cupo o ciclo?</p>
                        <x-ui.button type="button" variant="secondary" size="sm" @click="reactivateOpen = false; changeOpen = true">
                            Elegir otro plan
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Schedule package change (online pay) --}}
        <div x-show="changeOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 py-8">
                <div class="fixed inset-0 bg-black/60" @click="changeOpen = false"></div>
                <div class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 p-5" @click.stop>
                    <h3 class="text-sm font-semibold text-white">Programar cambio de plan</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Se cobra el nuevo plan online ahora; el cambio aplica el
                        {{ $company->package_ends_at?->format('d/m/Y') ?? 'fin del periodo' }}.
                    </p>
                    <form method="POST" action="{{ route('company.billing.package.schedule') }}" class="mt-4 space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <x-ui.label for="schedule_sku">Nuevo paquete Accesos</x-ui.label>
                                <select id="schedule_sku" name="package_sku" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                                    @foreach ($packageOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('package_sku') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-ui.field-error name="package_sku" />
                            </div>
                            <div>
                                <x-ui.label for="schedule_cycle">Ciclo</x-ui.label>
                                <select id="schedule_cycle" name="billing_cycle" required class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                                    @foreach ($cycleOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_cycle', $company->billing_cycle?->value) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-ui.field-error name="billing_cycle" />
                            </div>
                            <div>
                                <x-ui.label for="schedule_manual">Asientos sin hardware</x-ui.label>
                                <input type="number" min="0" name="manual_seats" id="schedule_manual" value="{{ old('manual_seats', $company->package_manual_seats) }}" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                            </div>
                            <div>
                                <x-ui.label for="schedule_hardware">Asientos con hardware</x-ui.label>
                                <input type="number" min="0" name="hardware_seats" id="schedule_hardware" value="{{ old('hardware_seats', $company->package_hardware_seats) }}" class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">La suma de asientos debe igualar el cupo. Desde 5 puedes mezclar.</p>
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" size="sm" @click="changeOpen = false">Volver</x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">Pagar online y programar</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-company-layout>
