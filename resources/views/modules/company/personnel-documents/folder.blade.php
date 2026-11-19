<x-company-layout :title="'Carpeta · '.$employee->fullName()">
    @include('modules.personnel-documents.partials.folder-body', [
        'employee' => $employee,
        'indexedChecklists' => $indexedChecklists,
        'canUpload' => $canUpload,
        'canManageIndexing' => $canManageIndexing ?? false,
        'canDownload' => $canUpload,
        'cargar' => $cargar,
        'indexRoute' => route('company.personnel-documents.index'),
        'previewRoute' => 'company.personnel-documents.preview',
        'downloadRoute' => 'company.personnel-documents.download',
        'destroyRoute' => 'company.personnel-documents.destroy',
        'batchCreateRoute' => route('company.personnel-documents.batch.create', $employee),
    ])
</x-company-layout>
