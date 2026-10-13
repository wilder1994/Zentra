@if (! empty($operateReturn['active']))
    <div class="operate-banner shrink-0 border-b border-amber-800/60 bg-amber-950/50">
        <div class="w-full px-4 sm:px-6 lg:px-8 py-2.5 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="operate-banner-title text-sm font-semibold text-amber-100 truncate">
                    Operando {{ $operateReturn['mode_label'] }} · {{ $operateReturn['client_name'] }}
                </p>
                <p class="operate-banner-hint text-xs text-amber-200/70">
                    Entraste desde el expediente de empresa · puedes volver cuando termines
                </p>
            </div>
            <form method="POST" action="{{ route('company.operate.exit') }}" class="shrink-0">
                @csrf
                <x-ui.button type="submit" variant="secondary" size="sm">
                    Volver al expediente
                </x-ui.button>
            </form>
        </div>
    </div>
@endif
