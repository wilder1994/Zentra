<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StorePersonnelFolderRequest;
use App\Http\Requests\Company\StorePersonnelFolderTypeRequest;
use App\Models\EmployeeDocument;
use App\Models\PersonnelFolder;
use App\Models\PersonnelFolderType;
use App\Services\Personnel\EnsurePersonnelCatalogService;
use App\Services\Personnel\ManagePersonnelCatalogService;
use App\Support\Company\SettingsCatalog;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PersonnelFolderController extends Controller
{
    public function __construct(
        private readonly EnsurePersonnelCatalogService $ensure,
        private readonly ManagePersonnelCatalogService $catalog,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', PersonnelFolder::class);
        $this->ensure->ensure($this->companyId($request));

        return SettingsCatalog::hubRedirect('expediente');
    }

    public function store(StorePersonnelFolderRequest $request): RedirectResponse
    {
        $this->authorize('create', PersonnelFolder::class);
        $companyId = $this->companyId($request);
        $this->ensure->ensure($companyId);
        $this->catalog->createFolder($companyId, $request->validated('name'));

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Carpeta creada.');
    }

    public function update(Request $request, PersonnelFolder $personnelFolder): RedirectResponse
    {
        $this->assertCompany($request, $personnelFolder);
        $this->authorize('update', $personnelFolder);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->catalog->renameFolder($personnelFolder, $data['name']);

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Carpeta actualizada.');
    }

    public function deactivate(Request $request, PersonnelFolder $personnelFolder): RedirectResponse
    {
        $this->assertCompany($request, $personnelFolder);
        $this->authorize('update', $personnelFolder);
        $this->catalog->setFolderActive($personnelFolder, false);

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Carpeta desactivada. Ya no aparece al indexar.');
    }

    public function activate(Request $request, PersonnelFolder $personnelFolder): RedirectResponse
    {
        $this->assertCompany($request, $personnelFolder);
        $this->authorize('update', $personnelFolder);
        $this->catalog->setFolderActive($personnelFolder, true);

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Carpeta activada.');
    }

    public function destroy(Request $request, PersonnelFolder $personnelFolder): RedirectResponse
    {
        $this->assertCompany($request, $personnelFolder);
        $this->authorize('delete', $personnelFolder);

        try {
            $this->catalog->deleteFolder($personnelFolder);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('company.personnel-folders.index')
                ->with('error', $exception->validator->errors()->first() ?: 'No se pudo eliminar la carpeta.');
        }

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Carpeta eliminada.');
    }

    public function storeType(StorePersonnelFolderTypeRequest $request, PersonnelFolder $personnelFolder): RedirectResponse
    {
        $this->assertCompany($request, $personnelFolder);
        $this->authorize('create', PersonnelFolder::class);
        $this->catalog->createType($personnelFolder, $request->validated('name'));

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Tipo creado.');
    }

    public function updateType(Request $request, PersonnelFolderType $personnelFolderType): RedirectResponse
    {
        $folder = $this->folderOf($request, $personnelFolderType);
        $this->authorize('update', $folder);
        $data = $request->validate(['name' => ['required', 'string', 'max:160']]);
        $this->catalog->renameType($personnelFolderType, $data['name']);

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Tipo actualizado.');
    }

    public function deactivateType(Request $request, PersonnelFolderType $personnelFolderType): RedirectResponse
    {
        $folder = $this->folderOf($request, $personnelFolderType);
        $this->authorize('update', $folder);
        $this->catalog->setTypeActive($personnelFolderType, false);

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Tipo desactivado. Ya no aparece al indexar.');
    }

    public function activateType(Request $request, PersonnelFolderType $personnelFolderType): RedirectResponse
    {
        $folder = $this->folderOf($request, $personnelFolderType);
        $this->authorize('update', $folder);
        $this->catalog->setTypeActive($personnelFolderType, true);

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Tipo activado.');
    }

    public function destroyType(Request $request, PersonnelFolderType $personnelFolderType): RedirectResponse
    {
        $folder = $this->folderOf($request, $personnelFolderType);
        $this->authorize('delete', $folder);

        try {
            $this->catalog->deleteType($personnelFolderType);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('company.personnel-folders.index')
                ->with('error', $exception->validator->errors()->first() ?: 'No se pudo eliminar el tipo.');
        }

        return redirect()
            ->route('company.personnel-folders.index')
            ->with('success', 'Tipo eliminado.');
    }

    /**
     * @return array{folders: \Illuminate\Support\Collection<int, PersonnelFolder>, folderCounts: array<string, int>, typeCounts: array<string, int>}
     */
    public function panelData(int $companyId): array
    {
        $this->ensure->ensure($companyId);

        $folders = PersonnelFolder::query()
            ->where('security_company_id', $companyId)
            ->with('types')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $usage = EmployeeDocument::query()
            ->where('security_company_id', $companyId)
            ->selectRaw('folder, document_type, COUNT(*) as total')
            ->groupBy('folder', 'document_type')
            ->get();

        $folderCounts = [];
        $typeCounts = [];
        foreach ($usage as $row) {
            $slug = (string) $row->folder;
            $folderCounts[$slug] = ($folderCounts[$slug] ?? 0) + (int) $row->total;
            $typeCounts[$slug.'|'.(string) $row->document_type] = (int) $row->total;
        }

        return [
            'folders' => $folders,
            'folderCounts' => $folderCounts,
            'typeCounts' => $typeCounts,
        ];
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    private function assertCompany(Request $request, PersonnelFolder $folder): void
    {
        abort_unless((int) $folder->security_company_id === $this->companyId($request), 404);
    }

    private function folderOf(Request $request, PersonnelFolderType $type): PersonnelFolder
    {
        $type->loadMissing('folder');
        $folder = $type->folder;
        abort_if($folder === null, 404);
        $this->assertCompany($request, $folder);

        return $folder;
    }
}
