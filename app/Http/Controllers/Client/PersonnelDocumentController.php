<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\DocumentFolder;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Repositories\EmployeeRepository;
use App\Support\Catalog\CompanyEntitlements;
use App\Support\Files\StoredFileResponder;
use App\Support\Personnel\FolderChecklist;
use App\Support\Personnel\SpreadsheetPreviewResponse;
use App\Support\Personnel\XlsxPreviewHtml;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PersonnelDocumentController extends Controller
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): View
    {
        $client = $this->visibleClient();
        $q = $request->string('q')->toString();

        return view('modules.client.personnel-documents.index', [
            'employees' => $this->employees->paginateForPersonnelDocuments(
                (int) $client->security_company_id,
                $q !== '' ? $q : null,
                24,
                (int) $client->id,
                $this->tenantContext->installationIds(),
            ),
            'folderTotal' => count(DocumentFolder::cases()),
            'q' => $q,
            'canUpload' => false,
        ]);
    }

    public function folder(Employee $employee): View
    {
        $client = $this->visibleClient();
        $this->assertAssigned($client, $employee);
        $employee->load('documents');

        $indexedChecklists = [];
        foreach (DocumentFolder::cases() as $folder) {
            $indexedChecklists[] = [
                'folder' => $folder,
                'rows' => FolderChecklist::for($employee, $folder),
                'summary' => FolderChecklist::summary($employee, $folder),
                'panel' => $folder->value.'-list',
                'na' => null,
            ];
        }

        return view('modules.client.personnel-documents.folder', [
            'employee' => $employee,
            'canUpload' => false,
            'cargar' => false,
            'indexedChecklists' => $indexedChecklists,
        ]);
    }

    public function preview(EmployeeDocument $document): StreamedResponse|View
    {
        $file = $this->locate($document);
        abort_unless($file->hasFile(), 404);

        if (XlsxPreviewHtml::isSpreadsheet($file->mime, $file->disk_path)) {
            return SpreadsheetPreviewResponse::make((string) $file->disk_path, $file->label());
        }

        return StoredFileResponder::stream($file->disk_path, $file->label(), (string) $file->mime, true);
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        abort_unless(auth()->user()?->can('company.documents.manage'), 403);
        $file = $this->locate($document);
        abort_unless($file->hasFile(), 404);

        return StoredFileResponder::stream($file->disk_path, $file->label(), (string) $file->mime, false);
    }

    private function visibleClient(): Client
    {
        $client = Client::query()->with('securityCompany')->findOrFail((int) $this->tenantContext->clientId());
        abort_unless($client->has_access && $client->show_personnel_folders, 403);
        abort_unless(CompanyEntitlements::for($client->securityCompany)->hasIndexing(), 403);
        abort_unless(auth()->user()?->can('company.documents.view'), 403);

        return $client;
    }

    private function assertAssigned(Client $client, Employee $employee): void
    {
        abort_unless((int) $employee->security_company_id === (int) $client->security_company_id, 404);
        abort_unless($employee->supervisorPosts()->where('client_id', $client->id)->exists(), 404);
        $allowed = $this->tenantContext->installationIds();
        if ($allowed !== null) {
            abort_unless(
                $employee->supervisorPosts()->whereIn('installation_id', $allowed)->exists(),
                404,
            );
        }
    }

    private function locate(EmployeeDocument $document): EmployeeDocument
    {
        $client = $this->visibleClient();
        $document->loadMissing('employee');
        abort_unless($document->employee instanceof Employee, 404);
        $this->assertAssigned($client, $document->employee);

        return $document;
    }
}
