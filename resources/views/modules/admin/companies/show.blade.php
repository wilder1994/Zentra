@php
    use App\Enums\ClientLifecycle;

    $fmt = fn (?float $n) => $n !== null ? '$'.number_format($n, 0, ',', '.') : '—';
    $riskClass = match ($riskLabel) {
        'Alto' => 'text-rose-300 border-rose-800/50 bg-rose-950/30',
        'Medio' => 'text-amber-300 border-amber-800/50 bg-amber-950/30',
        default => 'text-emerald-300 border-emerald-800/50 bg-emerald-950/30',
    };
@endphp

<x-admin-layout :title="'Empresa: '.$company->displayName()">
    @include('modules.admin.companies.partials.nav-slots', [
        'company' => $company,
        'companyNavActive' => 'show',
    ])

    <x-ui.issued-credentials :login="session('issued_login')" :password="session('issued_password')" />

    <div
        class="w-full space-y-4"
        x-data="{
            payOpen: {{ old('action_context') === 'pay' ? 'true' : 'false' }},
            cancelOpen: {{ old('action_context') === 'cancel' ? 'true' : 'false' }},
            changeOpen: {{ old('action_context') === 'schedule' ? 'true' : 'false' }},
            reactivateOpen: false,
            archiveOpen: false,
            cutOpen: false,
            reactivateServiceOpen: false,
            applyWhen: @js(old('apply_when', 'now')),
            lockOpen: {{ $errors->hasAny(['price_locked_until', 'price_lock_note']) ? 'true' : 'false' }},
        }"
    >
        @if ($company->archived_at !== null)
            <div class="rounded-lg border border-slate-600/60 bg-slate-900 px-4 py-3 text-sm text-slate-100">
                <p class="font-semibold">Servicio archivado</p>
                <p class="text-xs text-slate-400 mt-1">La empresa está fuera de cartera. Nadie opera. Usa «Reactivar servicio» si vuelve el acuerdo.</p>
            </div>
        @elseif (! $company->is_active)
            <div class="rounded-lg border border-rose-800/50 bg-rose-950/30 px-4 py-3 text-sm text-rose-100">
                <p class="font-semibold">Servicio suspendido</p>
                <p class="text-xs text-rose-200/80 mt-1">Solo el admin empresa entra (solo lectura). El resto ve: «Este usuario no tiene acceso al sistema. Comuníquese con el administrador.»</p>
            </div>
        @endif

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

        @if ($pendingRepriceAmount !== null)
            <div class="rounded-lg border border-sky-800/50 bg-sky-950/30 px-4 py-3 text-sm text-sky-100">
                Nuevo valor desde el {{ $company->pending_reprice_at->format('d/m/Y') }}:
                <strong class="tabular-nums">{{ $fmt($pendingRepriceAmount) }}</strong>
                (hoy {{ $fmt($company->contractedAmount()) }})
                @if ($company->pending_reprice_note)
                    · {{ $company->pending_reprice_note }}
                @endif
            </div>
        @endif

        @if ($company->hasScheduledPackageChange())
            <div class="rounded-lg border border-violet-800/50 bg-violet-950/30 px-4 py-3 text-sm text-violet-100">
                Cambio de plan programado
                @if ($company->scheduled_package_sku)
                    · Accesos {{ \App\Enums\CompanyPackageSku::tryFrom((string) $company->scheduled_package_sku)?->label() ?? $company->scheduled_package_sku }}
                @endif
                @if ($company->scheduled_supervision_package_sku)
                    · Supervisión {{ $company->scheduled_supervision_package_sku === 'none'
                        ? 'sin Supervisión'
                        : (\App\Enums\SupervisionPackageSku::tryFrom($company->scheduled_supervision_package_sku)?->label() ?? $company->scheduled_supervision_package_sku) }}
                @endif
                @if ($company->scheduled_change_at)
                    · aplica el {{ $company->scheduled_change_at->format('d/m/Y') }}
                @endif
            </div>
        @endif

        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 sm:p-5 space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-4 py-3">
                    <p class="text-xs text-slate-500">Instalaciones en uso</p>
                    <p class="mt-1 text-lg font-semibold text-white tabular-nums">
                        {{ $company->operational_clients_count ?? 0 }} / {{ $company->max_clients }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-4 py-3">
                    <p class="text-xs text-slate-500">Contratado {{ $company->billing_cycle?->value === 'annual' ? '/ año' : '/ mes' }}</p>
                    <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $fmt($company->contractedAmount()) }}</p>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-4 py-3">
                    <p class="text-xs text-slate-500">A renovación</p>
                    <p class="mt-1 text-lg font-semibold {{ ($daysToRenewal !== null && $daysToRenewal < 45) ? 'text-amber-300' : 'text-white' }} tabular-nums">
                        {{ $daysToRenewal !== null ? $daysToRenewal.' d' : '—' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-4 py-3">
                    <p class="text-xs text-slate-500">Pagos pendientes</p>
                    <p class="mt-1 text-lg font-semibold {{ $pendingPaymentsCount > 0 ? 'text-amber-300' : 'text-white' }} tabular-nums">
                        {{ $pendingPaymentsCount }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/50 px-4 py-3">
                    <p class="text-xs text-slate-500">Alertas ops</p>
                    <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $opsAlertsCount }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($company->hasAccessPackage())
                    <span class="ui-chip ui-chip-accent">Accesos · {{ $company->accessMetal()?->label() }}</span>
                @endif
                @if ($company->supervision_package_sku)
                    <span class="ui-chip ui-chip-warning">Supervisión · {{ $company->supervisionMetal()?->label() }}</span>
                @endif
                @if ($company->isPriceLocked())
                    <span class="ui-chip ui-chip-success" title="{{ $company->price_lock_note }}">
                        Precio pactado{{ $company->price_locked_until ? ' · hasta '.$company->price_locked_until->format('d/m/Y') : '' }}
                    </span>
                @endif
                <span class="ui-chip {{ $company->billing_cycle?->value === 'annual' ? 'ui-chip-success' : '' }}">
                    Ciclo · {{ $company->billingPeriodLabel() }}
                </span>
                @if ($company->package_ends_at)
                    <span class="ui-chip">
                        Vigencia {{ $company->package_starts_at?->format('d/m/Y') ?? '—' }}
                        → {{ $company->package_ends_at->format('d/m/Y') }}
                    </span>
                @endif
                <span class="rounded-full border px-2.5 py-0.5 text-xs {{ $riskClass }}">
                    Riesgo · {{ $riskLabel }}
                </span>
            </div>

            <div>
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span>Uso de cupo</span>
                    <span class="tabular-nums">{{ $quotaPct }}%</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full bg-violet-500" style="width: {{ max($quotaPct, $quotaPct > 0 ? 2 : 0) }}%"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="relative min-h-0 max-h-[min(36rem,calc(100dvh-16rem))] lg:max-h-none">
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 min-w-0 flex flex-col overflow-hidden h-full max-h-full lg:absolute lg:inset-0">
                <div class="flex items-center justify-between gap-2 mb-3 shrink-0">
                    <h3 class="text-sm font-semibold text-white">Cartera de clientes</h3>
                    <span class="text-xs text-slate-500">
                        {{ $portfolioClients->where('lifecycle', ClientLifecycle::Active)->count() }} activos
                    </span>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500 sticky top-0 bg-slate-900/95">
                            <tr>
                                <th class="pb-2 text-left font-medium">Cliente</th>
                                <th class="pb-2 text-left font-medium">Lifecycle</th>
                                <th class="pb-2 text-center font-medium">Geo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse ($portfolioClients as $client)
                                <tr>
                                    <td class="py-2 text-slate-200">{{ $client->name }}</td>
                                    <td class="py-2">
                                        <span @class([
                                            'text-xs',
                                            'text-emerald-400' => $client->lifecycle === ClientLifecycle::Active,
                                            'text-slate-500' => $client->lifecycle !== ClientLifecycle::Active,
                                        ])>
                                            {{ $client->lifecycle?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-center text-xs text-slate-400">
                                        {{ ($client->latitude && $client->longitude) ? 'Sí' : 'No' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-500">Sin clientes en cartera.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-slate-600 shrink-0">Usa «Entrar como empresa» para operar clientes y portería.</p>
            </section>
            </div>

            <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-4 min-w-0 h-full">
                <div>
                    <h3 class="text-sm font-semibold text-white">Plan y ciclo</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Cada producto por cantidad de instalaciones; cada uno toma su plan. El cobro va en Pagar factura.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-500">Plan actual</p>
                        <p class="mt-1 text-sm font-medium text-white">{{ $company->packageLabel() }}</p>
                        @if ($company->supervision_package_sku)
                            <p class="text-sm font-medium text-amber-200">{{ $company->supervision_package_sku->label() }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Ciclo de facturación</p>
                        <p class="mt-1 text-sm font-medium text-white">{{ $company->billing_cycle?->label() ?? '—' }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-violet-800/40 bg-violet-950/20 p-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-slate-500 uppercase">Valor por instalación</p>
                        <p class="text-base font-semibold text-white tabular-nums">{{ $fmt((float) $company->package_price_monthly / max(1, $company->accessSeats()->size())) }}</p>
                        <p class="text-xs text-slate-500">Lista {{ $fmt((float) $company->unit_price_snapshot) }} · −{{ number_format((float) $company->volume_discount_pct * 100, 0) }}%</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase">Total contratado</p>
                        <p class="text-base font-semibold text-white tabular-nums">{{ $fmt($company->contractedAmount()) }}</p>
                        <p class="text-xs text-slate-500">{{ $company->billing_cycle?->value === 'annual' ? 'por año' : 'por mes' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase">Con catálogo de hoy</p>
                        <p class="text-base font-semibold text-emerald-300 tabular-nums">{{ $fmt($catalogAmount) }}</p>
                        <p class="text-xs text-slate-500">Referencia si se recalcula</p>
                    </div>
                </div>

                @can('platform.companies.manage')
                    <div class="rounded-lg border border-sky-900/50 bg-sky-950/15 p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-white">Precio pactado</p>
                                <p class="text-xs text-slate-400">Si está activo, los cambios de catálogo y el incremento anual no tocan a esta empresa.</p>
                            </div>
                            <button type="button" class="ui-chip-link" @click="lockOpen = ! lockOpen">{{ $company->price_locked ? 'Editar' : 'Activar' }}</button>
                        </div>
                        <form method="POST" action="{{ route('admin.companies.price-lock.update', $company) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end" x-show="lockOpen" x-cloak>
                            @csrf
                            @method('PUT')
                            <label class="flex items-center gap-2 text-sm text-slate-200">
                                <input type="hidden" name="price_locked" value="0">
                                <input type="checkbox" name="price_locked" value="1" @checked(old('price_locked', $company->price_locked))>
                                Pactado
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
                            <div class="sm:col-span-3 flex justify-end">
                                <x-ui.button type="submit" variant="secondary" size="sm">Guardar</x-ui.button>
                            </div>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.companies.plan.apply', $company) }}" class="rounded-lg border border-amber-800/40 bg-amber-950/10 p-4 space-y-3"
                        x-data="planPicker(@js([
                            'catalog' => $planCatalog,
                            'manual' => (int) old('manual_seats', $company->accessSeats()->manual),
                            'hardware' => (int) old('hardware_seats', $company->accessSeats()->hardware),
                            'supervision' => (int) old('supervision_seats', $company->supervision_package_sku?->size() ?? 0),
                            'indexing' => (int) old('indexing_seats', $company->indexing_seats ?? 0),
                            'observatory' => (int) old('observatory_seats', $company->observatory_seats ?? 0),
                            'cycle' => old('billing_cycle', $company->billing_cycle?->value ?? 'monthly'),
                            'enforceAvailability' => false,
                        ]))">
                        @csrf
                        <x-plans.seat-fields :cycle-options="$cycleOptions" id-prefix="plan" />
                        <fieldset class="space-y-2">
                            <legend class="text-xs text-slate-400">Cuándo aplica</legend>
                            <label class="flex items-center gap-2 text-sm text-slate-200">
                                <input type="radio" name="apply_when" value="now" x-model="applyWhen">
                                Ya — cupo nuevo al instante
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-200">
                                <input type="radio" name="apply_when" value="on_date" x-model="applyWhen">
                                El día
                                <input type="date" name="effective_on" value="{{ old('effective_on') }}" class="h-8 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-200">
                                <input type="radio" name="apply_when" value="period_end" x-model="applyWhen" @disabled(! $company->package_ends_at)>
                                Al corte{{ $company->package_ends_at ? ' ('.$company->package_ends_at->format('d/m/Y').')' : '' }}
                            </label>
                            <x-ui.field-error name="apply_when" />
                            <x-ui.field-error name="effective_on" />
                        </fieldset>
                        <x-ui.button type="submit" variant="secondary" size="sm" x-bind:disabled="isEmpty">Aplicar plan</x-ui.button>
                    </form>
                @endcan

                @can('platform.companies.manage')
                    <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-800">
                        <x-ui.button type="button" variant="secondary" size="md" @click="payOpen = true">
                            Pagar factura
                        </x-ui.button>
                        @if ($isUpToDate && ! $company->hasPendingCancellation())
                            <x-ui.button type="button" variant="secondary" size="md" @click="cancelOpen = true">
                                Cancelar membresía
                            </x-ui.button>
                        @endif
                        @if ($isUpToDate)
                            <x-ui.button type="button" variant="secondary" size="md" @click="changeOpen = true">
                                Cobrar y programar al corte
                            </x-ui.button>
                        @endif

                        @if ($company->canUndoCancellation())
                            <x-ui.button type="button" variant="secondary" size="md" @click="reactivateOpen = true">
                                Reactivar membresía
                            </x-ui.button>
                        @elseif ($company->needsPaidReactivation())
                            <x-ui.button type="button" variant="secondary" size="md" @click="payOpen = true">
                                Reactivar membresía
                            </x-ui.button>
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

                    <div class="rounded-lg border border-rose-900/40 bg-rose-950/15 p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-rose-300/80">Acceso al sistema</p>
                                <p class="text-sm font-semibold text-white">
                                    @if ($company->archived_at !== null)
                                        Archivado
                                    @elseif (! $company->is_active)
                                        Suspendido
                                    @else
                                        Activo
                                    @endif
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    No es la membresía. Aquí se apaga o se prende el acceso de todos los usuarios.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($company->is_active && $company->archived_at === null)
                                <x-ui.button type="button" variant="secondary" size="md" @click="cutOpen = true">
                                    Suspender acceso
                                </x-ui.button>
                            @else
                                <x-ui.button type="button" variant="secondary" size="md" @click="reactivateServiceOpen = true">
                                    Reactivar acceso
                                </x-ui.button>
                            @endif

                            @if ($company->archived_at === null)
                                <x-ui.button type="button" variant="secondary" size="md" @click="archiveOpen = true">
                                    Archivar empresa
                                </x-ui.button>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-xs text-slate-500">No tienes permiso para gestionar la membresía.</p>
                @endcan

            </section>
        </div>

        @include('modules.admin.companies.partials.quotas')

        <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h3 class="text-sm font-semibold text-white">Historial de precios</h3>
                <span class="text-xs text-slate-500">Últimos {{ $priceChanges->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="pb-2 text-left font-medium">Fecha</th>
                            <th class="pb-2 text-left font-medium">Motivo</th>
                            <th class="pb-2 text-left font-medium">Plan</th>
                            <th class="pb-2 text-right font-medium">Antes</th>
                            <th class="pb-2 text-right font-medium">Después</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($priceChanges as $change)
                            <tr>
                                <td class="py-2 text-xs text-slate-400 whitespace-nowrap">{{ $change->effective_at->format('d/m/Y') }}</td>
                                <td class="py-2 text-slate-300">
                                    {{ $change->reasonLabel() }}
                                    @if ($change->note)
                                        <span class="block text-[11px] text-slate-500">{{ $change->note }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-xs text-slate-400">{{ $change->new_plan }}</td>
                                <td class="py-2 text-right text-slate-500 tabular-nums">{{ $fmt((float) $change->previous_amount) }}</td>
                                <td class="py-2 text-right text-white tabular-nums">{{ $fmt((float) $change->new_amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-500">Sin cambios de precio registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Historial de pagos</h3>
                        <p class="text-xs text-slate-500 mt-1">Facturas pagadas registradas</p>
                    </div>
                    <a href="{{ route('admin.companies.historial', $company) }}" class="ui-chip-link shrink-0">
                        Ver historial
                    </a>
                </div>
                <p class="mt-4 text-3xl font-semibold text-white tabular-nums">{{ $paidInvoicesCount }}</p>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $paidInvoicesCount === 1 ? 'pago completado' : 'pagos completados' }}
                </p>
            </section>

            <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                <h3 class="text-sm font-semibold text-white mb-3">Contacto y soporte</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-slate-500">Admin empresa</p>
                        <p class="mt-1 font-medium text-slate-200 break-all">{{ $companyAdminEmail }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Último pago</p>
                        <p class="mt-1 font-medium text-slate-200">
                            @if ($latestPayment)
                                {{ ($latestPayment->paid_at ?? $latestPayment->created_at)?->format('d/m/Y') }}
                                · {{ $latestPayment->method?->label() ?? '—' }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Docs legales</p>
                        <a href="{{ route('admin.documents.expedientes.show', $company) }}" class="mt-1 inline-block font-medium text-violet-400 hover:text-violet-300">
                            Abrir expediente →
                        </a>
                    </div>
                </div>
            </section>
        </div>

        @include('modules.admin.companies.partials.pay-invoice-modal', [
            'company' => $company,
            'hasAcceptance' => $hasAcceptance,
            'isUpToDate' => $isUpToDate,
        ])
        @include('modules.admin.companies.partials.cancel-membership-modal', ['company' => $company])
        @include('modules.admin.companies.partials.reactivate-membership-modal', ['company' => $company])
        @include('modules.admin.companies.partials.schedule-package-modal', [
            'company' => $company,
            'planCatalog' => $planCatalog,
            'cycleOptions' => $cycleOptions,
        ])
        @include('modules.admin.companies.partials.archive-company-modal', ['company' => $company])
        @include('modules.admin.companies.partials.cut-service-modal', ['company' => $company])
        @include('modules.admin.companies.partials.reactivate-service-modal', ['company' => $company])
    </div>
</x-admin-layout>
