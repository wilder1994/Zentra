<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Enums\DocumentFolder;
use App\Enums\OtherDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\IndexLaborHistoryRequest;
use App\Http\Requests\Personnel\MarkLaborHistoryNaRequest;
use App\Http\Requests\Personnel\PreviewParafiscalPlanillaRequest;
use App\Http\Requests\Personnel\StoreLaborHistoryBatchRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentBatch;
use App\Models\PersonnelFolder;
use App\Models\PersonnelFolderType;
use App\Models\SecurityCompany;
use App\Repositories\EmployeeRepository;
use App\Services\Personnel\CommitParafiscalPlanillaService;
use App\Services\Personnel\EmployeeIndexingQuota;
use App\Services\Personnel\EnsurePersonnelCatalogService;
use App\Services\Personnel\PersonnelIndexingCatalog;
use App\Services\Personnel\DeleteEmployeeDocumentService;
use App\Services\Personnel\IndexLaborHistoryPdfService;
use App\Services\Personnel\MarkLaborHistoryNotApplicableService;
use App\Services\Personnel\PreviewParafiscalPlanillaService;
use App\Services\Personnel\StoreLaborHistoryBatchService;
use App\Support\Catalog\CompanyEntitlements;
use App\Support\Files\StorageUsage;
use App\Support\Files\StoredFileResponder;
use App\Support\Personnel\FolderChecklist;
use App\Support\Personnel\IndexedFolder;
use App\Support\Personnel\OtherSupportNamer;
use App\Support\Personnel\SpreadsheetPreviewResponse;
use App\Support\Personnel\XlsxPreviewHtml;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PersonnelDocumentController extends Controller
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly StoreLaborHistoryBatchService $historyBatch,
        private readonly IndexLaborHistoryPdfService $historyIndex,
        private readonly MarkLaborHistoryNotApplicableService $historyNa,
        private readonly DeleteEmployeeDocumentService $deleteDocument,
        private readonly PreviewParafiscalPlanillaService $parafiscalPreview,
        private readonly CommitParafiscalPlanillaService $parafiscalCommit,
        private readonly PersonnelIndexingCatalog $indexingCatalog,
        private readonly EnsurePersonnelCatalogService $personnelCatalog,
        private readonly EmployeeIndexingQuota $indexingQuota,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyId($request);
        $q = trim($request->string('q')->toString());
        $tenure = $this->tenureFilter($request);
        $indexing = $this->indexingFilter($request);
        $data = [
            'employees' => $this->employees->paginateForPersonnelDocuments(
                $companyId,
                $q !== '' ? $q : null,
                24,
                null,
                null,
                $tenure !== '' ? $tenure : null,
                $indexing !== '' ? $indexing : null,
            ),
            'folderTotal' => count(DocumentFolder::cases()),
            'q' => $q,
            'tenure' => $tenure,
            'indexing' => $indexing,
            'searching' => $q !== '' || $tenure !== '' || $indexing !== '',
        ];

        if ($request->ajax()) {
            return view('modules.company.personnel-documents.partials.results', $data);
        }

        $data['canUpload'] = $this->canUpload($request);
        $data['storageLabel'] = StorageUsage::label(StorageUsage::bytesForCompany($companyId));

        return view('modules.company.personnel-documents.index', $data);
    }

    public function folder(Request $request, Employee $employee): View
    {
        $this->assertCompany($request, $employee);
        $employee->load('documents');
        $this->personnelCatalog->ensure((int) $employee->security_company_id);
        $names = PersonnelFolder::query()
            ->where('security_company_id', $employee->security_company_id)
            ->pluck('name', 'slug');

        $indexedChecklists = [];
        foreach (DocumentFolder::cases() as $folder) {
            $indexedChecklists[] = [
                'folder' => $folder,
                'label' => (string) ($names[$folder->value] ?? $folder->label()),
                'rows' => FolderChecklist::for($employee, $folder),
                'summary' => FolderChecklist::summary($employee, $folder),
                'panel' => $folder->value.'-list',
                'na' => $folder->naRoute(),
            ];
        }

        $canUpload = $this->canUpload($request);

        return view('modules.company.personnel-documents.folder', [
            'employee' => $employee,
            'canUpload' => $canUpload && $employee->indexing_enabled,
            'canManageIndexing' => $canUpload,
            'cargar' => $request->boolean('cargar'),
            'indexedChecklists' => $indexedChecklists,
        ]);
    }

    public function toggleIndexing(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        abort_unless($this->canUpload($request), 403);

        try {
            if ($employee->indexing_enabled) {
                $this->indexingQuota->disable($employee);
                $message = 'Indexación desactivada. Los archivos se conservan.';
            } else {
                $this->indexingQuota->enable($employee);
                $message = 'Indexación activada.';
            }
        } catch (ValidationException $exception) {
            return redirect()
                ->route('company.personnel-documents.folder', $employee)
                ->with('error', $exception->validator->errors()->first('indexing') ?: 'No se pudo cambiar la indexación.');
        }

        return redirect()
            ->route('company.personnel-documents.folder', $employee)
            ->with('success', $message);
    }

    public function storeBatch(StoreLaborHistoryBatchRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->assertIndexingEnabled($employee);
        $file = $request->file('file');
        abort_if($file === null, 422);

        $batch = $this->historyBatch->execute($employee, $file);

        return redirect()->route('company.personnel-documents.batch.index', [
            'employee' => $employee,
            'batch' => $batch,
        ]);
    }

    public function batchIndex(Request $request, Employee $employee, EmployeeDocumentBatch $batch): View
    {
        $this->assertCompany($request, $employee);
        $lote = $this->batchForEmployee($employee, $batch);
        $employee->load('documents');

        $preview = route('company.personnel-documents.batch.preview', ['employee' => $employee, 'batch' => $lote]);
        $store = route('company.personnel-documents.batch.store', ['employee' => $employee, 'batch' => $lote]);

        return view('modules.company.personnel-documents.index-batch', [
            'employee' => $employee,
            'batch' => $lote,
            'storeUrl' => $store,
            'previewUrl' => $preview,
            'historyMeta' => [
                'page_count' => $lote->page_count,
                'preview_url' => $preview,
                'other_type' => OtherDocumentType::Otro->value,
                'name_suffix' => OtherSupportNamer::suffix($employee),
                'reserved' => OtherSupportNamer::reserved($employee),
                'existing_count' => OtherSupportNamer::loadedCount($employee),
                'max_others' => OtherDocumentType::MAX,
                'catalogs' => $this->indexingCatalog->forEmployee($employee),
            ],
        ]);
    }

    public function storeBatchIndex(IndexLaborHistoryRequest $request, Employee $employee, EmployeeDocumentBatch $batch): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->assertIndexingEnabled($employee);
        $lote = $this->batchForEmployee($employee, $batch);

        $slices = [];
        foreach ($request->validated('slices') as $row) {
            $pages = [];
            foreach ($row['pages'] as $page) {
                $page = (int) $page;
                abort_if($page > $lote->page_count, 422);
                if (! in_array($page, $pages, true)) {
                    $pages[] = $page;
                }
            }

            $stored = PersonnelFolder::query()
                ->where('security_company_id', $employee->security_company_id)
                ->where('slug', $row['folder'])
                ->where('is_active', true)
                ->with('types')
                ->first();
            abort_if($stored === null, 422);
            $type = $stored->types->first(
                fn (PersonnelFolderType $item): bool => $item->slug === $row['document_type'] && $item->is_active,
            );
            abort_if($type === null, 422);
            $slices[] = [
                'folder' => $stored->slug,
                'type_slug' => $type->slug,
                'repeatable' => $type->is_repeatable,
                'fallback_name' => $this->indexingCatalog->suggestedName($stored, $type, $employee),
                'display_name' => $row['display_name'],
                'pages' => $pages,
                'taken_on' => $row['taken_on'] ?? null,
                'provider' => $row['provider'] ?? null,
                'tipo' => $row['tipo'] ?? null,
            ];
        }

        $this->historyIndex->execute($lote, $employee, $slices);

        return redirect()
            ->route('company.personnel-documents.folder', $employee)
            ->with('success', 'Lote indexado: '.count($slices).' documento(s).');
    }

    public function previewBatch(Request $request, Employee $employee, EmployeeDocumentBatch $batch): BinaryFileResponse
    {
        $this->assertCompany($request, $employee);
        $lote = $this->batchForEmployee($employee, $batch);

        return StoredFileResponder::stream($lote->disk_path, $lote->original_name, (string) $lote->mime, true);
    }

    public function markNa(MarkLaborHistoryNaRequest $request, Employee $employee, string $folder): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->assertIndexingEnabled($employee);
        $resolved = DocumentFolder::tryFrom($folder);
        abort_if($resolved === null || $resolved === DocumentFolder::Otros || $resolved === DocumentFolder::Parafiscales, 404);

        $type = IndexedFolder::resolve($resolved, $request->string('document_type')->toString());
        $this->historyNa->execute($employee, $resolved, $type);

        return redirect()
            ->route('company.personnel-documents.folder', $employee)
            ->with('success', $type->label().' marcado como no aplica.');
    }

    public function preview(Request $request, EmployeeDocument $document): BinaryFileResponse|View
    {
        $file = $this->locate($request, $document);
        abort_unless($file->hasFile(), 404);

        if (XlsxPreviewHtml::isSpreadsheet($file->mime, $file->disk_path)) {
            return SpreadsheetPreviewResponse::make((string) $file->disk_path, $file->label());
        }

        return StoredFileResponder::stream($file->disk_path, $file->label(), (string) $file->mime, true);
    }

    public function download(Request $request, EmployeeDocument $document): BinaryFileResponse
    {
        abort_unless($this->canUpload($request), 403);
        $file = $this->locate($request, $document);
        abort_unless($file->hasFile(), 404);

        return StoredFileResponder::stream($file->disk_path, $file->label(), (string) $file->mime, false);
    }

    public function destroy(Request $request, EmployeeDocument $document): RedirectResponse
    {
        $file = $this->locate($request, $document);

        try {
            $this->deleteDocument->execute($file);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('success', 'Documento eliminado. Puede volver a indexarlo.');
    }

    public function storeParafiscalPreview(PreviewParafiscalPlanillaRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $file = $request->file('file');
        abort_if($file === null, 422);

        try {
            $preview = $this->parafiscalPreview->previewFile($file, $companyId, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('company.personnel-documents.index')
                ->with('error', $e->getMessage());
        }

        $this->parafiscalPreview->put($companyId, (int) $request->user()->id, $preview);

        return redirect()->route('company.personnel-documents.parafiscales.preview');
    }

    public function showParafiscalPreview(Request $request): View|RedirectResponse
    {
        abort_unless($this->canUpload($request), 403);
        $preview = $this->parafiscalPreview->get($this->companyId($request), (int) $request->user()->id);

        if ($preview === null) {
            return redirect()
                ->route('company.personnel-documents.index')
                ->with('error', 'No hay una revisión vigente. Vuelve a cargar la planilla.');
        }

        $conflicts = $this->parafiscalCommit->conflictCount($this->companyId($request), (int) $request->user()->id);

        return view('modules.company.personnel-documents.parafiscal-preview', compact('preview', 'conflicts'));
    }

    public function commitParafiscal(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->canUpload($request), 403);
        $companyId = $this->companyId($request);
        $userId = (int) $request->user()->id;

        $mode = $request->string('mode')->toString();
        $mode = in_array($mode, ['replace', 'version'], true) ? $mode : null;

        if ($request->expectsJson()) {
            try {
                $started = $this->parafiscalCommit->start($companyId, $userId, $mode);
            } catch (ValidationException $e) {
                return response()->json([
                    'ok' => false,
                    'message' => $e->validator->errors()->first() ?: 'No se pudo cargar la planilla.',
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'total' => $started['total'],
                'percent' => 0,
                'message' => 'Plantilla lista. Guardando recortes…',
            ]);
        }

        try {
            $count = $this->parafiscalCommit->execute($companyId, $userId, $mode);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.personnel-documents.index')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo cargar la planilla.');
        }

        $message = $count === 1
            ? '1 recorte de planilla guardado.'
            : $count.' recortes de planilla guardados.';

        return redirect()
            ->route('company.personnel-documents.index')
            ->with('success', $message);
    }

    public function tickParafiscal(Request $request): JsonResponse
    {
        abort_unless($this->canUpload($request), 403);

        try {
            return response()->json($this->parafiscalCommit->tick(
                $this->companyId($request),
                (int) $request->user()->id,
                max(1, min(40, $request->integer('limit', 8))),
            ));
        } catch (ValidationException $e) {
            return response()->json([
                'done' => false,
                'message' => $e->validator->errors()->first() ?: 'No se pudo continuar.',
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'done' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Falló un lote del recorte.',
            ], 422);
        }
    }

    public function progressParafiscal(Request $request): JsonResponse
    {
        abort_unless($this->canUpload($request), 403);

        return response()->json($this->parafiscalCommit->progress(
            $this->companyId($request),
            (int) $request->user()->id,
        ));
    }

    public function cancelParafiscal(Request $request): RedirectResponse
    {
        abort_unless($this->canUpload($request), 403);
        $this->parafiscalCommit->abort($this->companyId($request), (int) $request->user()->id);

        return redirect()->route('company.personnel-documents.index');
    }

    private function assertIndexingEnabled(Employee $employee): void
    {
        if ($employee->indexing_enabled) {
            return;
        }

        throw ValidationException::withMessages([
            'indexing' => 'La indexación de este empleado está desactivada.',
        ]);
    }

    private function tenureFilter(Request $request): string
    {
        $tenure = $request->string('tenure')->toString();

        return in_array($tenure, ['lt1', 'y1_3', 'y3_5', 'gt5', 'none'], true) ? $tenure : '';
    }

    private function indexingFilter(Request $request): string
    {
        $indexing = $request->string('indexing')->toString();

        return in_array($indexing, ['on', 'off'], true) ? $indexing : '';
    }

    private function canUpload(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && (
            $user->can('company.documents.manage')
            || $user->can('company.settings.manage')
        );
    }

    private function companyId(Request $request): int
    {
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $company = SecurityCompany::query()->findOrFail($companyId);
        abort_unless(CompanyEntitlements::for($company)->hasIndexing(), 403);

        return $companyId;
    }

    private function assertCompany(Request $request, Employee $employee): void
    {
        abort_unless((int) $employee->security_company_id === $this->companyId($request), 404);
    }

    private function batchForEmployee(Employee $employee, EmployeeDocumentBatch $batch): EmployeeDocumentBatch
    {
        abort_unless((int) $batch->employee_id === (int) $employee->id, 404);

        return $batch;
    }

    private function locate(Request $request, EmployeeDocument $document): EmployeeDocument
    {
        abort_unless((int) $document->security_company_id === $this->companyId($request), 404);

        return $document;
    }
}
