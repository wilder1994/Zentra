@php
    use App\Enums\PaymentStatus;

    $fmt = fn (?float $n) => $n !== null ? '$'.number_format($n, 0, ',', '.') : '—';
@endphp

<x-admin-layout :title="'Empresa: '.$company->displayName()">
    @include('modules.admin.companies.partials.nav-slots', [
        'company' => $company,
        'companyNavActive' => 'historial',
    ])

    <div class="w-full space-y-4">
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
            <div class="rounded-lg border border-violet-800/50 bg-violet-950/30 px-4 py-3 text-sm text-violet-100">
                Cambio de plan programado a
                <strong>{{ $company->scheduled_package_sku ? (\App\Enums\CompanyPackageSku::tryFrom((string) $company->scheduled_package_sku)?->label() ?? $company->scheduled_package_sku) : 'plan actual' }}</strong>
                ({{ \App\Enums\BillingCycle::tryFrom((string) $company->scheduled_billing_cycle)?->label() ?? $company->scheduled_billing_cycle }})
                @if ($company->scheduled_change_at)
                    · aplica el {{ $company->scheduled_change_at->format('d/m/Y') }}
                @endif
            </div>
        @endif

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
                <p class="text-xs text-slate-500">Monto contratado</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $fmt($company->contractedAmount()) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <section class="xl:col-span-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4 flex flex-col min-h-0 max-h-[28rem]">
                <h3 class="text-sm font-semibold text-white shrink-0">Línea de tiempo</h3>
                <p class="text-xs text-slate-500 mt-0.5 shrink-0">Evidencias del ciclo comercial.</p>
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
    </div>
</x-admin-layout>
