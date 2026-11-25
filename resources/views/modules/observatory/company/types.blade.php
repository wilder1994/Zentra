<x-company-layout title="Riesgos del Observatorio">
    @if (($section ?? '') === 'comunas')
        <x-slot:headerTabs>
            <div class="flex w-full items-center gap-3 pt-3">
                <a href="{{ route('company.observatory.types.index') }}" class="text-[11px] font-semibold uppercase tracking-wide text-indigo-300 hover:text-indigo-200">← Riesgos</a>
                <h2 class="text-sm font-semibold text-white">Delitos por comuna</h2>
                @if ($canEdit)
                    <button type="submit" form="crime-figures-form" class="ml-auto h-8 rounded-lg bg-teal-600 px-3 text-xs font-semibold text-white">Guardar</button>
                @endif
            </div>
        </x-slot:headerTabs>
    @elseif (($section ?? '') === '')
        <x-slot:headerTabs>
            @include('modules.observatory.partials.nav-tabs', [
                'indexRoute' => 'company.observatory.events.index',
                'typesRoute' => 'company.observatory.types.index',
                'vista' => 'tipos',
            ])
        </x-slot:headerTabs>
    @elseif (($section ?? '') === 'catalogo')
        <x-slot:headerTabs>
            @include('modules.observatory.partials.nav-tabs', [
                'indexRoute' => 'company.observatory.events.index',
                'typesRoute' => 'company.observatory.types.index',
                'vista' => 'tipos',
                'riskCatalog' => true,
                'riskAxis' => $axis ?? 'exogeno',
            ])
        </x-slot:headerTabs>
    @endif

    @if (($section ?? '') === '')
        @include('modules.observatory.partials.risk-hub')
    @else
        @if ($section !== 'comunas')
            <div class="mb-4">
                <a href="{{ route('company.observatory.types.index') }}" class="text-[11px] font-semibold uppercase tracking-wide text-indigo-300 hover:text-indigo-200">← Riesgos</a>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($section === 'motor')
            @include('modules.observatory.partials.risk-parameters', [
                'parameters' => $parameters,
                'canEdit' => $canEdit,
            ])
        @elseif ($section === 'catalogo' && ($axis ?? 'exogeno') === 'exogeno')
            @include('modules.observatory.partials.risk-crime-catalog', [
                'crimeTypes' => $crimeTypes,
                'parameters' => $parameters,
                'canEdit' => $canEdit,
            ])
        @elseif ($section === 'catalogo')
            @include('modules.observatory.partials.risk-report-catalog', [
                'types' => $types,
                'parameters' => $parameters,
                'canEdit' => $canEdit && $storeClient,
                'nextColor' => $nextColor,
                'storeUrl' => ($canEdit && $storeClient) ? route('company.observatory.types.store', $storeClient) : null,
                'updateRoute' => 'company.observatory.types.update',
                'destroyRoute' => 'company.observatory.types.destroy',
            ])
        @elseif ($section === 'comunas')
            @include('modules.observatory.partials.crime-figures')
        @else
            @include('modules.observatory.partials.risk-report-log', ['reportLog' => $reportLog])
        @endif
    @endif
</x-company-layout>
