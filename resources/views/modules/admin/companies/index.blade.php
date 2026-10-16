@php
    use App\Domain\Pricing\Data\PlanSelection;
    use App\Enums\CommercialProduct;
    use App\Support\Catalog\InstallationQuota;
    use App\Support\Tenancy\CompanySubscriptionState;

    $fmt = fn (float $n) => '$'.number_format($n, 0, ',', '.');
    $num = fn (int $n) => number_format($n, 0, ',', '.');
@endphp

<x-admin-layout title="Empresas">
    <x-slot:actions>
        @can('platform.companies.manage')
            <x-ui.button variant="platform" :href="route('admin.companies.create')" size="sm">Crear empresa</x-ui.button>
        @endcan
    </x-slot:actions>

    <div class="flex flex-col flex-1 min-h-0 gap-3">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-2.5 shrink-0">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/90 px-3 py-2.5 min-w-0 flex flex-col">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-200">Riesgo comercial</span>
                    </div>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-3 items-center relative flex-1">
                    <div class="flex items-center justify-center min-h-[88px]">
                        <p class="text-3xl font-bold text-white tabular-nums leading-none">{{ $kpis['companies_risk_total'] }}</p>
                    </div>
                    <div class="pointer-events-none absolute left-1/2 top-2 bottom-2 w-px -translate-x-1/2 bg-gradient-to-b from-transparent via-slate-600/70 to-transparent" aria-hidden="true"></div>
                    <div class="min-w-0 space-y-1.5">
                        <div class="rounded-md bg-amber-600/90 px-2.5 py-1.5">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-amber-100/90 leading-none">Suspendidas</p>
                            <p class="text-sm font-bold text-white tabular-nums leading-tight mt-0.5">{{ $kpis['companies_suspended'] }}</p>
                        </div>
                        <div class="rounded-md bg-rose-900/80 px-2.5 py-1.5">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-rose-200/90 leading-none">Vencidas</p>
                            <p class="text-sm font-bold text-white tabular-nums leading-tight mt-0.5">{{ $kpis['companies_overdue'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-700/80 bg-slate-900/90 px-3 py-2.5 min-w-0 flex flex-col">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-violet-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 21V5a1 1 0 011-1h7v17H4zm9 0V9h6a1 1 0 011 1v11h-7z" />
                    </svg>
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-200">Total empresas</span>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-3 items-center relative flex-1">
                    <div class="flex items-center justify-center min-h-[88px]">
                        <p class="text-3xl font-bold text-white tabular-nums leading-none">{{ $kpis['companies_total'] }}</p>
                    </div>
                    <div class="pointer-events-none absolute left-1/2 top-2 bottom-2 w-px -translate-x-1/2 bg-gradient-to-b from-transparent via-slate-600/70 to-transparent" aria-hidden="true"></div>
                    <div class="min-w-0 space-y-1.5">
                        <div class="rounded-md bg-violet-600 px-2.5 py-1.5">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-violet-100/90 leading-none">Activas</p>
                            <p class="text-sm font-bold text-white tabular-nums leading-tight mt-0.5">{{ $kpis['companies_active'] }}</p>
                        </div>
                        <div class="rounded-md bg-slate-800 px-2.5 py-1.5">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400 leading-none">Archivadas</p>
                            <p class="text-sm font-bold text-slate-100 tabular-nums leading-tight mt-0.5">{{ $kpis['companies_archived'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-700/80 bg-slate-900/90 px-3 py-2.5 min-w-0 flex flex-col">
                <div class="flex items-center gap-2 min-w-0">
                    <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6"/>
                    </svg>
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-200">Clientes</span>
                </div>
                <div class="mt-2 grid grid-cols-2 gap-3 items-center relative flex-1">
                    <div class="flex items-center justify-center min-h-[88px]">
                        <p class="text-3xl font-bold text-white tabular-nums leading-none">{{ $kpis['clients_total'] }}</p>
                    </div>
                    <div class="pointer-events-none absolute left-1/2 top-2 bottom-2 w-px -translate-x-1/2 bg-gradient-to-b from-transparent via-slate-600/70 to-transparent" aria-hidden="true"></div>
                    <div class="min-w-0 space-y-1.5">
                        <div class="rounded-md bg-cyan-700 px-2.5 py-1.5">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-cyan-100/90 leading-none">Activos</p>
                            <p class="text-sm font-bold text-white tabular-nums leading-tight mt-0.5">{{ $kpis['clients_operational'] }}</p>
                        </div>
                        <div class="rounded-md bg-slate-800 px-2.5 py-1.5">
                            <p class="text-[9px] font-semibold uppercase tracking-wide text-slate-400 leading-none">Archivados</p>
                            <p class="text-sm font-bold text-slate-100 tabular-nums leading-tight mt-0.5">{{ $kpis['clients_archived'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 min-h-0 rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden flex flex-col">
            <div class="flex-1 overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-500 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Empresa</th>
                            <th class="px-4 py-2 text-left font-medium">Paquete</th>
                            <th class="px-4 py-2 text-left font-medium">Ciclo</th>
                            <th class="px-4 py-2 text-left font-medium">Cupo</th>
                            <th class="px-4 py-2 text-left font-medium">Contratado</th>
                            <th class="px-4 py-2 text-left font-medium">Estado</th>
                            <th class="px-4 py-2 text-right font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($companies as $company)
                            @php
                                $bucket = CompanySubscriptionState::bucket($company);
                                $plan = PlanSelection::fromCompany($company);
                                $amounts = $company->contractedAmounts();
                                $quota = InstallationQuota::for($company);
                                $suffix = $company->billing_cycle?->value === 'annual' ? '/año' : '/mes';
                                $catalog = app(\App\Services\Pricing\CompanyPlanPricer::class)->amount($company);
                                $isCustom = $company->isPriceLocked() || abs($catalog - $company->contractedAmount()) > 0.5;
                                $productRows = [];
                                foreach (CommercialProduct::cases() as $product) {
                                    $seats = $plan->seats($product);
                                    if ($seats < 1) {
                                        continue;
                                    }
                                    $line = match ($product) {
                                        CommercialProduct::Access => InstallationQuota::ACCESS,
                                        CommercialProduct::Supervision => InstallationQuota::SUPERVISION,
                                        CommercialProduct::Observatory => InstallationQuota::OBSERVATORY,
                                        default => null,
                                    };
                                    $productRows[] = [
                                        'product' => $product,
                                        'seats' => $seats,
                                        'metal' => $plan->metal($product)?->label(),
                                        'unit' => $product->unitLabel($seats),
                                        'hardware' => $product === CommercialProduct::Access ? $plan->access->hardware : 0,
                                        'used' => $product === CommercialProduct::Indexing
                                            ? (int) ($company->employees_active_count ?? 0)
                                            : ($line ? $quota->used($line) : 0),
                                    ];
                                }
                            @endphp
                            <tr class="hover:bg-slate-800/30 align-top">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-slate-200 whitespace-nowrap">{{ $company->trade_name }}</p>
                                    <p class="text-xs text-slate-600">{{ $company->tax_id }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-slate-300">
                                    <div class="space-y-1">
                                        @forelse ($productRows as $row)
                                            <p class="text-xs leading-snug">
                                                <span class="text-slate-200">{{ $row['product']->label() }}</span>
                                                <span class="text-slate-500">·</span>
                                                {{ $row['metal'] }}
                                                <span class="tabular-nums">{{ $num($row['seats']) }}</span>
                                                <span class="text-slate-500">{{ $row['unit'] }}</span>
                                                @if ($row['hardware'] > 0)
                                                    <span class="text-slate-500">({{ $row['hardware'] }} hw)</span>
                                                @endif
                                            </p>
                                        @empty
                                            <p class="text-xs text-slate-500">Sin productos</p>
                                        @endforelse
                                        @if ($isCustom)
                                            <span class="ui-chip mt-0.5">Personalizado</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="ui-chip {{ $company->billing_cycle?->value === 'annual' ? 'ui-chip-success' : '' }}">
                                        {{ $company->billingPeriodLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-300">
                                    <div class="space-y-0.5 text-xs tabular-nums">
                                        @foreach ($productRows as $row)
                                            <p>
                                                <span class="text-slate-500">{{ $row['product']->label() }}</span>
                                                {{ $num($row['used']) }} / {{ $num($row['seats']) }}
                                            </p>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-300">
                                    <p class="tabular-nums text-white">{{ $fmt($company->contractedAmount()) }} <span class="text-slate-600 text-xs">{{ $suffix }}</span></p>
                                    <div class="mt-0.5 space-y-0.5 text-[11px] text-slate-500">
                                        @foreach (['access' => 'Accesos', 'supervision' => 'Supervisión', 'indexing' => 'Indexación', 'observatory' => 'Observatorio'] as $key => $label)
                                            @continue(($amounts[$key] ?? 0) < 0.01)
                                            <p>{{ $label }} {{ $fmt((float) $amounts[$key]) }}</p>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="ui-chip
                                        @if($bucket->value === 'current') ui-chip-success
                                        @elseif($bucket->value === 'due_soon') ui-chip-caution
                                        @elseif($bucket->value === 'overdue') ui-chip-danger
                                        @elseif($bucket->value === 'suspended') ui-chip-warning
                                        @endif">
                                        {{ $bucket->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <a href="{{ route('admin.companies.show', $company) }}" class="ui-chip-link">Gestionar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-slate-500">No hay empresas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($companies->hasPages())
            <div class="shrink-0">{{ $companies->links() }}</div>
        @endif
    </div>
</x-admin-layout>
