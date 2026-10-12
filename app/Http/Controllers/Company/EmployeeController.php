<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\Employee\Data\SaveEmployeeData;
use App\Enums\BloodGroup;
use App\Enums\Sex;
use App\Exports\EmployeeImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\PreviewEmployeeImportRequest;
use App\Http\Requests\Company\ReassignEmployeePostRequest;
use App\Http\Requests\Company\StoreEmployeeRequest;
use App\Http\Requests\Company\UpdateEmployeeRequest;
use App\Models\Client;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\Employee;
use App\Models\IdentityDocumentType;
use App\Repositories\EmployeeRepository;
use App\Services\Company\CommitEmployeeImportService;
use App\Services\Company\CreateEmployeeCatalogItemsService;
use App\Services\Company\LookupEmployeeForPostService;
use App\Services\Company\ManageEmployeeService;
use App\Services\Company\PreviewEmployeeImportService;
use App\Services\Company\ReassignEmployeePostService;
use App\Services\Company\StoreEmployeePhotoService;
use App\Support\Geo\ColombiaDivipola;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly ManageEmployeeService $manageEmployeeService,
        private readonly PreviewEmployeeImportService $previewEmployeeImportService,
        private readonly CommitEmployeeImportService $commitEmployeeImportService,
        private readonly CreateEmployeeCatalogItemsService $createEmployeeCatalogItemsService,
        private readonly StoreEmployeePhotoService $storeEmployeePhotoService,
        private readonly LookupEmployeeForPostService $lookupEmployeeForPostService,
        private readonly ReassignEmployeePostService $reassignEmployeePostService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);
        $companyId = $this->companyId($request);
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        if (! in_array($status, ['active', 'archived', 'all'], true)) {
            $status = 'active';
        }

        $perPage = (int) $request->integer('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $employees = $this->employeeRepository->paginateForCompany(
            $companyId,
            $perPage,
            $search !== '' ? $search : null,
            $status,
        );

        return view('modules.company.employees.index', compact('employees', 'search', 'status', 'perPage'));
    }

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $this->authorize('create', Employee::class);

        return Excel::download(new EmployeeImportTemplateExport, 'formato-empleados-zentra.xlsx');
    }

    public function storeImportPreview(PreviewEmployeeImportRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        try {
            $preview = $request->file('file') !== null
                ? $this->previewEmployeeImportService->previewFile($request->file('file'), $companyId)
                : $this->previewEmployeeImportService->previewPaste((string) $request->input('paste'), $companyId);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('company.employees.index')
                ->with('error', $e->getMessage());
        }

        $this->previewEmployeeImportService->put($companyId, (int) $request->user()->id, $preview);

        return redirect()->route('company.employees.import.preview');
    }

    public function showImportPreview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $preview = $this->previewEmployeeImportService->get($this->companyId($request), (int) $request->user()->id);

        if ($preview === null) {
            return redirect()
                ->route('company.employees.index')
                ->with('error', 'No hay una revisión vigente. Vuelve a cargar el archivo.');
        }

        return view('modules.company.employees.import-preview', compact('preview'));
    }

    public function commitImport(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);
        try {
            $count = $this->commitEmployeeImportService->execute(
                $this->companyId($request),
                (int) $request->user()->id,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.employees.import.preview')
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo cargar.');
        }

        return redirect()
            ->route('company.employees.index')
            ->with('success', $count === 1 ? '1 ficha aplicada.' : $count.' fichas aplicadas.');
    }

    public function cancelImport(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $this->previewEmployeeImportService->forget($this->companyId($request), (int) $request->user()->id);

        return redirect()->route('company.employees.index');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Employee::class);

        return view('modules.company.employees.create', $this->formPayload($this->companyId($request)));
    }

    public function storeCatalogStarter(Request $request): JsonResponse
    {
        $this->authorize('create', CompanyJobTitle::class);

        $result = $this->createEmployeeCatalogItemsService->execute(
            $this->companyId($request),
            $request->all(),
        );

        return response()->json($this->catalogStarterPayload($result));
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $employee = $this->manageEmployeeService->create(
            SaveEmployeeData::fromValidated($request->validated(), $companyId),
        );
        $this->storeUploadedPhoto($request, $employee);

        return redirect()
            ->route('company.employees.show', $employee)
            ->with('success', 'Empleado creado.');
    }

    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $exceptPost = $request->filled('except_post') ? (int) $request->integer('except_post') : null;

        return response()->json([
            'employees' => $this->lookupEmployeeForPostService->search(
                $this->companyId($request),
                $request->string('q')->toString(),
                $exceptPost,
            ),
        ]);
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->assertCompany($request, $employee);
        $this->authorize('view', $employee);
        $employee->load(['jobTitle', 'collaboratorType', 'supervisorPosts.client', 'supervisorPosts.installation']);

        return view('modules.company.employees.show', [
            'employee' => $employee,
            'currentPost' => $employee->supervisorPosts->first(),
            'assignmentTree' => $this->assignmentTree($this->companyId($request)),
        ]);
    }

    public function reassign(ReassignEmployeePostRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);

        try {
            $this->reassignEmployeePostService->execute(
                $employee,
                (int) $request->validated('client_id'),
                (int) $request->validated('installation_id'),
                (int) $request->validated('supervisor_post_id'),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.employees.show', $employee)
                ->with('error', $e->validator->errors()->first() ?: 'No se pudo reasignar.');
        }

        return redirect()
            ->route('company.employees.show', $employee)
            ->with('success', 'Puesto actualizado.');
    }

    public function edit(Request $request, Employee $employee): View
    {
        $this->assertCompany($request, $employee);
        $this->authorize('update', $employee);

        return view('modules.company.employees.edit', array_merge(
            $this->formPayload($this->companyId($request), $employee),
            ['employee' => $employee],
        ));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->manageEmployeeService->update(
            $employee,
            SaveEmployeeData::fromValidated($request->validated(), $this->companyId($request)),
        );
        $this->storeUploadedPhoto($request, $employee);

        return redirect()
            ->route('company.employees.show', $employee)
            ->with('success', 'Empleado actualizado.');
    }

    public function archive(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->authorize('archive', $employee);
        $this->manageEmployeeService->archive($employee);

        return redirect()
            ->route('company.employees.show', $employee)
            ->with('success', 'Empleado archivado.');
    }

    public function restore(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->authorize('restore', $employee);

        try {
            $this->manageEmployeeService->restore($employee);
        } catch (ValidationException $e) {
            return redirect()
                ->route('company.employees.show', $employee)
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('company.employees.show', $employee)
            ->with('success', 'Empleado restaurado.');
    }

    public function photo(Request $request, Employee $employee): BinaryFileResponse
    {
        $this->assertCompany($request, $employee);
        $this->authorize('view', $employee);

        return $employee->photoFileResponse() ?? abort(404);
    }

    public function storePhoto(Request $request, Employee $employee): RedirectResponse
    {
        $this->assertCompany($request, $employee);
        $this->authorize('update', $employee);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $this->storeEmployeePhotoService->store($employee, $request->file('photo'));

        return redirect()
            ->route('company.employees.show', $employee)
            ->with('success', 'Foto actualizada.');
    }

    /** @return array<string, mixed> */
    private function formPayload(int $companyId, ?Employee $employee = null): array
    {
        $jobTitles = CompanyJobTitle::query()
            ->where('security_company_id', $companyId)
            ->where(function ($query) use ($employee): void {
                $query->where('is_active', true);
                if ($employee !== null) {
                    $query->orWhere('id', $employee->job_title_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $collaboratorTypes = CompanyCollaboratorType::query()
            ->where('security_company_id', $companyId)
            ->where(function ($query) use ($employee): void {
                $query->where('is_active', true);
                if ($employee !== null) {
                    $query->orWhere('id', $employee->collaborator_type_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'jobTitles' => $jobTitles,
            'collaboratorTypes' => $collaboratorTypes,
            'documentTypes' => IdentityDocumentType::optionsForSelect(),
            'sexOptions' => Sex::options(),
            'bloodGroups' => BloodGroup::options(),
            'colombiaPlaces' => ColombiaDivipola::tree(),
            'catalogStarterUrl' => route('company.employees.catalog-starter'),
        ];
    }

    /**
     * @param  array{collaborator_type: ?CompanyCollaboratorType, job_title: ?CompanyJobTitle}  $result
     * @return array{collaborator_type: ?array{id: int, name: string}, job_title: ?array{id: int, name: string}, message: string}
     */
    private function catalogStarterPayload(array $result): array
    {
        return [
            'collaborator_type' => $result['collaborator_type'] === null ? null : [
                'id' => $result['collaborator_type']->id,
                'name' => $result['collaborator_type']->name,
            ],
            'job_title' => $result['job_title'] === null ? null : [
                'id' => $result['job_title']->id,
                'name' => $result['job_title']->name,
            ],
            'message' => 'Ya puedes seleccionar el tipo y el cargo.',
        ];
    }

    /** @return list<array{id: int, name: string, installations: list<array{id: int, name: string, posts: list<array{id: int, name: string}>}>}> */
    private function assignmentTree(int $companyId): array
    {
        return Client::query()
            ->where('security_company_id', $companyId)
            ->where(fn ($q) => $q->where('has_access', true)->orWhere('has_supervision', true))
            ->with(['installations' => fn ($q) => $q->orderBy('name')->with(['supervisorPosts' => fn ($p) => $p->orderBy('name')])])
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client) => [
                'id' => (int) $client->id,
                'name' => $client->name,
                'installations' => $client->installations->map(fn ($installation) => [
                    'id' => (int) $installation->id,
                    'name' => $installation->name,
                    'posts' => $installation->supervisorPosts->map(fn ($post) => [
                        'id' => (int) $post->id,
                        'name' => $post->name,
                    ])->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function companyId(Request $request): int
    {
        return app(ActingCompanyResolver::class)->requireId($request->user());
    }

    private function assertCompany(Request $request, Employee $employee): void
    {
        abort_unless((int) $employee->security_company_id === $this->companyId($request), 404);
    }

    private function storeUploadedPhoto(Request $request, Employee $employee): void
    {
        $photo = $request->file('photo');
        if ($photo === null) {
            return;
        }

        $this->storeEmployeePhotoService->store($employee, $photo);
    }
}
