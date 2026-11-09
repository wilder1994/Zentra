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
use App\Models\SecurityCompany;
use App\Repositories\EmployeeRepository;
use App\Support\Catalog\CompanyEntitlements;
use App\Services\Personnel\CommitParafiscalPlanillaService;
use App\Services\Personnel\DeleteEmployeeDocumentService;
use App\Services\Personnel\IndexLaborHistoryPdfService;
use App\Services\Personnel\MarkLaborHistoryNotApplicableService;
use App\Services\Personnel\PreviewParafiscalPlanillaService;
use App\Services\Personnel\StoreLaborHistoryBatchService;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyId($request);
        $q = $request->string('q')->toString();

        return view('modules.company.personnel-documents.index', [
            'employees' => $this->employees->paginateForPersonnelDocuments(
                $companyId,
                $q !== '' ? $q : null,
            ),
            'folderTotal' => count(DocumentFolder::cases()),
            'q' => $q,
            'canUpload' => $this->canUpload($request),
        ]);
    }

    public function folder(Request $request, Employee $employee): View
    {
        $this->assertCompany($request, $employee);
        $employee->load('documents');

        $indexedChecklists = [];
        foreach (DocumentFolder::cases() as $folder) {
            $indexedChecklists[] = [
                'folder' => $folder,
                'rows' => FolderChecklist::for($employee, $folder),
                'summary' => FolderChecklist::summary($employee, $folder),
                'panel' => $folder->value.'-list',
                'na' => $folder->naRoute(),
            ];
        }

        return view('modules.company.personnel-documents.folder', [
            'employee' => $employee,
            'canUpload' => $this->canUpload($request),
            'cargar' => $request->boolean('cargar'),
            'indexedChecklists' => $indexedChecklists,
        ]);
    }

    public function storeBatch(StoreLaborHistoryBatchRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
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
                'catalogs' => collect(DocumentFolder::cases())
                    ->filter(fn (DocumentFolder $folder) => $folder->isIndexed())
                    ->map(fn (DocumentFolder $folder) => [
                    'value' => $folder->value,
                    'label' => $folder->label(),
                    'course_fields' => $folder === DocumentFolder::Cursos,
                    'other_fields' => $folder === DocumentFolder::Otros,
                    'types' => collect(IndexedFolder::types($folder))->map(fn ($type) => [
                        'value' => $type->value,
                        'label' => $type->label(),
                        'name' => $type->suggestedName($employee),
                        'req' => $type->requirement()->label(),
                        'other' => method_exists($type, 'isRepeatable') && $type->isRepeatable(),
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    public function storeBatchIndex(IndexLaborHistoryRequest $request, Employee $employee, EmployeeDocumentBatch $batch): RedirectResponse
    {
        $this->assertCompany($request, $employee);
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

            $folder = DocumentFolder::from($row['folder']);
            $slices[] = [
                'folder' => $folder,
                'type' => IndexedFolder::resolve($folder, $row['document_type']),
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

    public function previewBatch(Request $request, Employee $employee, EmployeeDocumentBatch $batch): StreamedResponse
    {
        $this->assertCompany($request, $employee);
        $lote = $this->batchForEmployee($employee, $batch);

        return StoredFileResponder::stream($lote->disk_path, $lote->original_name, (string) $lote->mime, true);
    }

    public function markNa(MarkLaborHistoryNaRequest $request, Employee $employee, string $folder): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $resolved = DocumentFolder::tryFrom($folder);
        abort_if($resolved === null || $resolved === DocumentFolder::Otros || $resolved === DocumentFolder::Parafiscales, 404);

        $type = IndexedFolder::resolve($resolved, $request->string('document_type')->toString());
        $this->historyNa->execute($employee, $resolved, $type);

        return redirect()
            ->route('company.personnel-documents.folder', $employee)
            ->with('success', $type->label().' marcado como no aplica.');
    }

    public function preview(Request $request, EmployeeDocument $document): StreamedResponse|View
    {
        $file = $this->locate($request, $document);
        abort_unless($file->hasFile(), 404);

        if (XlsxPreviewHtml::isSpreadsheet($file->mime, $file->disk_path)) {
            return SpreadsheetPreviewResponse::make((string) $file->disk_path, $file->label());
        }

        return StoredFileResponder::stream($file->disk_path, $file->label(), (string) $file->mime, true);
    }

    public function download(Request $request, EmployeeDocument $document): StreamedResponse
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

        return view('modules.company.personnel-documents.parafiscal-preview', compact('preview'));
    }

    public function commitParafiscal(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->canUpload($request), 403);
        $companyId = $this->companyId($request);
        $userId = (int) $request->user()->id;

        if ($request->expectsJson()) {
            try {
                $started = $this->parafiscalCommit->start($companyId, $userId);
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
            $count = $this->parafiscalCommit->execute($companyId, $userId);
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
