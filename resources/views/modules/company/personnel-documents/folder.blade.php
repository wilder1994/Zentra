<x-company-layout :title="'Carpeta · '.$employee->fullName()">
    <x-slot:header>
        <h2 class="truncate text-base font-semibold text-white">
            Carpeta
            <span class="font-normal text-slate-500"> · </span>{{ $employee->fullName() }}
        </h2>
        <p class="mt-0.5 truncate text-xs text-slate-400">
            <span>{{ $employee->document_type }} {{ $employee->document_number }}</span>
            <span class="ml-3 {{ $employee->indexing_enabled ? 'text-emerald-300' : 'text-slate-500' }}">Indexación {{ $employee->indexing_enabled ? 'activa' : 'desactivada' }}</span>
        </p>
    </x-slot:header>
    <x-slot:headerTabs>
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2">
            <div class="min-w-[16rem] flex-1">
                <input type="search" class="folder-search is-toolbar" data-expediente-search placeholder="Buscar documento" autocomplete="off">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" size="sm" :href="route('company.personnel-documents.index')">Volver</x-ui.button>
                @if ($canManageIndexing ?? false)
                    <form method="POST" action="{{ route('company.personnel-documents.indexing', $employee) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-700 px-4 text-sm font-medium text-slate-200 hover:bg-slate-800">
                            {{ $employee->indexing_enabled ? 'Desactivar indexación' : 'Activar indexación' }}
                        </button>
                    </form>
                @endif
                @if ($canUpload)
                    <button class="inline-flex h-9 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-500" type="button" data-open-upload>Cargar documentos</button>
                @endif
            </div>
        </div>
    </x-slot:headerTabs>
    @include('modules.personnel-documents.partials.folder-body', [
        'employee' => $employee,
        'indexedChecklists' => $indexedChecklists,
        'canUpload' => $canUpload,
        'canManageIndexing' => $canManageIndexing ?? false,
        'canDownload' => $canUpload,
        'cargar' => $cargar,
        'showIdentity' => false,
        'indexRoute' => route('company.personnel-documents.index'),
        'previewRoute' => 'company.personnel-documents.preview',
        'downloadRoute' => 'company.personnel-documents.download',
        'destroyRoute' => 'company.personnel-documents.destroy',
        'batchCreateRoute' => route('company.personnel-documents.batch.create', $employee),
    ])
</x-company-layout>
