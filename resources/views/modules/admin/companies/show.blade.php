@php
    use App\Enums\CommercialProduct;
    use App\Domain\Pricing\Data\PlanSelection;

    $fmt = fn (?float $n) => $n !== null ? '$'.number_format($n, 0, ',', '.') : '—';
    $companyPlan = PlanSelection::fromCompany($company);
    $planKey = $companyPlan->uniformMetal()?->value ?? 'personalizado';
    $perCycle = $company->billing_cycle?->value === 'annual' ? '/ año' : '/ mes';
    $q = $quotaOverview;
    $installPct = (int) $q['capacity'] > 0 ? (int) min(100, round($q['used'] / $q['capacity'] * 100)) : 0;
    $expandContext = old('action_context') === 'expand';
    $planErrors = ! $expandContext && $errors->hasAny(['manual_seats', 'hardware_seats', 'supervision_seats', 'indexing_seats', 'observatory_seats', 'billing_cycle', 'apply_when', 'effective_on']);

    $alerts = [];
    if ($company->archived_at !== null) {
        $alerts[] = ['tone' => 'slate', 'title' => 'Servicio archivado', 'text' => 'Fuera de cartera; nadie opera. Usa «Reactivar acceso» si vuelve el acuerdo.'];
    } elseif (! $company->is_active) {
        $alerts[] = ['tone' => 'rose', 'title' => 'Servicio suspendido', 'text' => 'Solo el admin empresa entra, en solo lectura.'];
    }
    if ($company->hasPendingCancellation()) {
        $alerts[] = ['tone' => 'amber', 'title' => 'Cancelación programada', 'text' => trim(($company->package_ends_at ? 'Acceso hasta '.$company->package_ends_at->format('d/m/Y').'. ' : '').($company->cancellation_reason ? 'Motivo: '.$company->cancellation_reason : ''))];
    }
    if ($pendingRepriceAmount !== null) {
        $alerts[] = ['tone' => 'sky', 'title' => 'Nuevo valor desde el '.$company->pending_reprice_at->format('d/m/Y'), 'text' => $fmt($pendingRepriceAmount).' (hoy '.$fmt($company->contractedAmount()).')'.($company->pending_reprice_note ? ' · '.$company->pending_reprice_note : '')];
    }
    if ($company->hasScheduledPackageChange()) {
        $parts = [];
        if ($company->scheduled_package_sku) {
            $parts[] = 'Accesos '.(\App\Enums\CompanyPackageSku::tryFrom((string) $company->scheduled_package_sku)?->label() ?? $company->scheduled_package_sku);
        }
        if ($company->scheduled_supervision_package_sku) {
            $parts[] = 'Supervisión '.($company->scheduled_supervision_package_sku === 'none'
                ? 'sin Supervisión'
                : (\App\Enums\SupervisionPackageSku::tryFrom($company->scheduled_supervision_package_sku)?->label() ?? $company->scheduled_supervision_package_sku));
        }
        if ($company->scheduled_change_at) {
            $parts[] = 'aplica el '.$company->scheduled_change_at->format('d/m/Y');
        }
        $alerts[] = ['tone' => 'violet', 'title' => 'Cambio de plan programado', 'text' => implode(' · ', $parts)];
    }
    $alertTone = [
        'slate' => 'border-slate-600/60 bg-slate-900 text-slate-100',
        'rose' => 'border-rose-800/50 bg-rose-950/30 text-rose-100',
        'amber' => 'border-amber-800/50 bg-amber-950/30 text-amber-100',
        'sky' => 'border-sky-800/50 bg-sky-950/30 text-sky-100',
        'violet' => 'border-violet-800/50 bg-violet-950/30 text-violet-100',
    ];
    $movementTone = [
        'success' => 'bg-emerald-400',
        'warning' => 'bg-amber-400',
        'danger' => 'bg-rose-400',
        'info' => 'bg-sky-400',
    ];
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
            planOpen: {{ $planErrors ? 'true' : 'false' }},
            quotaOpen: {{ ($expandContext && $errors->any()) || $errors->hasAny(['extra_employees', 'posts_per_installation', 'employees_per_post', 'quota_note', 'max_posts', 'max_employees', 'product', 'quantity', 'agreed_amount', 'expand_note']) ? 'true' : 'false' }},
            lockOpen: {{ $errors->hasAny(['price_locked_until', 'price_lock_note']) ? 'true' : 'false' }},
            menuOpen: false,
        }"
    >
        @if ($alerts !== [])
            <div class="space-y-2">
                @foreach ($alerts as $alert)
                    <div class="rounded-lg border px-4 py-2 text-sm {{ $alertTone[$alert['tone']] }}">
                        <span class="font-semibold">{{ $alert['title'] }}</span>
                        @if ($alert['text'] !== '')
                            <span class="opacity-80"> · {{ $alert['text'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="col-span-2 md:col-span-1 rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Plan</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    @if ($companyPlan->isEmpty())
                        <span class="text-sm text-slate-400">Sin plan</span>
                    @else
                        <span class="plan-name-chip" data-metal="{{ $planKey }}">{{ $companyPlan->planName() }}</span>
                    @endif
                </div>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $fmt($company->contractedAmount()) }} <span class="text-xs font-normal text-slate-500">{{ $perCycle }}</span></p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Instalaciones</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $q['used'] }} <span class="text-sm text-slate-500">/ {{ $q['capacity'] }}</span></p>
                <div class="mt-1.5 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                    <div class="h-full rounded-full bg-cyan-500" style="width: {{ max($installPct, $installPct > 0 ? 2 : 0) }}%"></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Clientes</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ $company->operational_clients_count ?? 0 }} <span class="text-sm text-slate-500">activos</span></p>
                <p class="text-xs text-slate-500">{{ $company->clients_count ?? 0 }} en total</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Renovación</p>
                <p class="mt-1 text-lg font-semibold tabular-nums {{ ($daysToRenewal !== null && $daysToRenewal < 45) ? 'text-amber-300' : 'text-white' }}">
                    {{ $daysToRenewal !== null ? $daysToRenewal.' días' : '—' }}
                </p>
                <p class="text-xs text-slate-500">{{ $company->package_ends_at?->format('d/m/Y') ?? 'Sin fecha de corte' }}</p>
            </div>
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-4 py-3">
                <p class="text-xs text-slate-500">Pagos pendientes</p>
                <p class="mt-1 text-lg font-semibold tabular-nums {{ $pendingPaymentsCount > 0 ? 'text-amber-300' : 'text-white' }}">{{ $pendingPaymentsCount }}</p>
                <p class="text-xs text-slate-500">{{ $isUpToDate ? 'Al día' : 'Revisar cobro' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            <section class="plan-metal plan-metal-static lg:col-span-3 rounded-lg border p-4 space-y-4 min-w-0" data-metal="{{ $planKey }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="plan-metal-title text-lg font-bold">{{ $companyPlan->planName() }}</h3>
                        <p class="text-xs text-slate-400">
                            {{ $company->billing_cycle?->label() ?? '—' }}
                            @if ($company->package_ends_at)
                                · vigencia {{ $company->package_starts_at?->format('d/m/Y') ?? '—' }} → {{ $company->package_ends_at->format('d/m/Y') }}
                            @endif
                        </p>
                    </div>
                </div>

                <table class="min-w-full text-sm">
                    <thead class="text-xs text-slate-500">
                        <tr>
                            <th class="pb-2 text-left font-medium">Producto</th>
                            <th class="pb-2 text-left font-medium">Plan</th>
                            <th class="pb-2 text-right font-medium">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        @foreach (CommercialProduct::cases() as $product)
                            @php($seats = $companyPlan->seats($product))
                            @continue($seats < 1)
                            <tr>
                                <td class="py-2 text-slate-200">
                                    {{ $product->label() }}
                                    @if ($product === CommercialProduct::Access && $companyPlan->access->hardware > 0)
                                        <span class="block text-[11px] text-slate-500">{{ $companyPlan->access->hardware }} con hardware</span>
                                    @endif
                                </td>
                                <td class="py-2"><span class="plan-name-chip" data-metal="{{ $companyPlan->metal($product)?->value }}">{{ $companyPlan->metal($product)?->label() }}</span></td>
                                <td class="py-2 text-right text-white tabular-nums">{{ number_format($seats, 0, ',', '.') }} <span class="text-xs text-slate-500">{{ $product->unitLabel($seats) }}</span></td>
                            </tr>
                        @endforeach
                        @if ($companyPlan->isEmpty())
                            <tr><td colspan="3" class="py-4 text-center text-slate-500">Sin productos contratados.</td></tr>
                        @endif
                    </tbody>
                </table>

                <div class="flex flex-wrap items-end justify-between gap-3 border-t border-slate-800/80 pt-3">
                    <div>
                        <p class="text-xs text-slate-500">Total contratado</p>
                        <p class="text-2xl font-bold text-white tabular-nums">{{ $fmt($company->contractedAmount()) }} <span class="text-sm font-normal text-slate-500">{{ $perCycle }}</span></p>
                    </div>
                    <p class="text-xs text-slate-500 text-right">
                        Con catálogo de hoy <span class="text-emerald-300 tabular-nums">{{ $fmt($catalogAmount) }}</span>
                        <span class="block">Referencia si se recalcula</span>
                    </p>
                </div>

                @can('platform.companies.manage')
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button type="button" size="sm" @click="planOpen = true">Cambiar plan</x-ui.button>
                        <x-ui.button type="button" variant="secondary" size="sm" @click="payOpen = true">Pagar factura</x-ui.button>
                        <div class="relative ml-auto" @click.outside="menuOpen = false">
                            <x-ui.button type="button" variant="secondary" size="sm" @click="menuOpen = ! menuOpen" x-bind:aria-expanded="menuOpen">
                                Más acciones
                                <svg class="ml-1 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.5 7.5L10 12l4.5-4.5z"/></svg>
                            </x-ui.button>
                            <div x-show="menuOpen" x-cloak x-transition.opacity
                                class="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-slate-700 bg-slate-900 p-1 shadow-xl text-sm">
                                <p class="px-3 pt-2 pb-1 text-[11px] uppercase tracking-wide text-slate-500">Membresía</p>
                                @if ($isUpToDate)
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-slate-200 hover:bg-slate-800" @click="menuOpen = false; changeOpen = true">Cobrar y programar al corte</button>
                                @endif
                                @if ($isUpToDate && ! $company->hasPendingCancellation())
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-slate-200 hover:bg-slate-800" @click="menuOpen = false; cancelOpen = true">Cancelar membresía</button>
                                @endif
                                @if ($company->canUndoCancellation())
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-slate-200 hover:bg-slate-800" @click="menuOpen = false; reactivateOpen = true">Reactivar membresía</button>
                                @elseif ($company->needsPaidReactivation())
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-slate-200 hover:bg-slate-800" @click="menuOpen = false; payOpen = true">Reactivar membresía</button>
                                @endif

                                <p class="mt-1 border-t border-slate-800 px-3 pt-2 pb-1 text-[11px] uppercase tracking-wide text-rose-300/80">Acceso al sistema</p>
                                @if ($company->is_active && $company->archived_at === null)
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-rose-200 hover:bg-rose-950/40" @click="menuOpen = false; cutOpen = true">Suspender acceso</button>
                                @else
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-emerald-200 hover:bg-slate-800" @click="menuOpen = false; reactivateServiceOpen = true">Reactivar acceso</button>
                                @endif
                                @if ($company->archived_at === null)
                                    <button type="button" class="w-full rounded-md px-3 py-2 text-left text-rose-200 hover:bg-rose-950/40" @click="menuOpen = false; archiveOpen = true">Archivar empresa</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-slate-500">No tienes permiso para gestionar la membresía.</p>
                @endcan
            </section>

            <div class="lg:col-span-2 min-w-0 flex">
                @include('modules.admin.companies.partials.quotas')
            </div>
        </div>

        <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
            <div class="flex items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-white">Movimientos</h3>
                    <p class="text-xs text-slate-500">Pagos y cambios de precio recientes</p>
                </div>
                <a href="{{ route('admin.companies.historial', $company) }}" class="ui-chip-link shrink-0">Ver todo</a>
            </div>
            @if ($movements->isEmpty())
                <p class="py-4 text-center text-sm text-slate-500">Sin movimientos registrados.</p>
            @else
                <ul class="divide-y divide-slate-800">
                    @foreach ($movements as $move)
                        <li class="flex items-center gap-3 py-2 text-sm">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $movementTone[$move['tone']] }}"></span>
                            <span class="w-20 shrink-0 text-xs text-slate-500 tabular-nums">{{ $move['date']?->format('d/m/Y') ?? '—' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="text-slate-200">{{ $move['title'] }}</span>
                                @if ($move['detail'])
                                    <span class="block truncate text-[11px] text-slate-500">{{ $move['detail'] }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-right tabular-nums">
                                @if ($move['previous'] !== null)
                                    <span class="text-xs text-slate-500 line-through">{{ $fmt($move['previous']) }}</span>
                                @endif
                                <span class="text-white">{{ $fmt($move['amount']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @include('modules.admin.companies.partials.plan-change-modal')
        @include('modules.admin.companies.partials.price-lock-modal')
        @include('modules.admin.companies.partials.quotas-modal')
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
