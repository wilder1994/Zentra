{{-- Slots de nav compartidos: show / profile / expediente de una empresa --}}
@php
    use App\Enums\SubscriptionStatus;

    $statusLabel = $company->subscription_status?->label() ?? ($company->is_active ? 'Activa' : 'Inactiva');
    $isActiveStatus = $company->is_active
        && ($company->subscription_status === null || $company->subscription_status === SubscriptionStatus::Active);
    $statusTone = $isActiveStatus ? 'text-emerald-400' : 'text-amber-300';
    $locationBits = array_filter([(string) $company->city, (string) $company->department]);
    $locationLabel = $locationBits !== [] ? implode(', ', $locationBits) : null;
    $activeTab = $companyNavActive ?? 'show';
@endphp

<x-slot:subtitle>
    <span class="text-slate-500">
        NIT {{ $company->tax_id }}
        @if ($locationLabel)
            · {{ $locationLabel }}
        @endif
    </span>
    <span class="{{ $statusTone }} font-medium pl-3 sm:pl-4">{{ $statusLabel }}</span>
</x-slot:subtitle>

<x-slot:actions>
    <x-ui.button variant="secondary" :href="route('admin.companies.index')" size="sm">← Empresas</x-ui.button>
    @can('platform.companies.manage')
        <form method="POST" action="{{ route('admin.companies.enter', $company) }}" class="inline-flex">
            @csrf
            <button type="submit"
                    class="admin-enter-btn group inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-500/60 bg-emerald-500/10 px-3.5 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20 hover:text-emerald-200"
                    title="Entrar al panel de {{ $company->displayName() }} como soporte">
                <svg class="h-4 w-4 group-hover:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17" />
                    <path d="M3 21h18" />
                    <circle cx="15" cy="12.5" r="0.9" fill="currentColor" stroke="none" />
                </svg>
                <svg class="hidden h-4 w-4 group-hover:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13 4h5a1 1 0 0 1 1 1v16" />
                    <path d="M3 21h18" />
                    <path d="M5 21V5.2a1 1 0 0 1 .76-.97l6-1.5A1 1 0 0 1 13 3.7V21" />
                    <circle cx="10" cy="12.5" r="0.9" fill="currentColor" stroke="none" />
                </svg>
                Entrar
            </button>
        </form>
    @endcan
</x-slot:actions>

<x-slot:headerTabs>
    <a
        href="{{ route('admin.companies.show', $company) }}"
        @class(['admin-header-tab', 'is-active' => $activeTab === 'show'])
    >
        Resumen
    </a>

    @can('updateProfile', $company)
        <a
            href="{{ route('admin.companies.profile.edit', $company) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'profile'])
        >
            Perfil y ubicación
        </a>
    @endcan

    @can('platform.documents.view')
        <a
            href="{{ route('admin.documents.expedientes.show', $company) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'expediente'])
        >
            Expediente docs
        </a>
    @endcan

    @can('platform.companies.view')
        <a
            href="{{ route('admin.companies.historial', $company) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'historial'])
        >
            Historial
        </a>
    @endcan

    @can('platform.companies.view')
        <a
            href="{{ route('admin.companies.integrations.index', $company) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'integrations'])
        >
            Integraciones API
        </a>
    @endcan
</x-slot:headerTabs>
