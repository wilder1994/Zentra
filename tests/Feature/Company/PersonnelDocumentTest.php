<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\AccessGrantLevel;
use App\Enums\AccessGrantScope;
use App\Enums\DocumentFolder;
use App\Enums\LaborHistoryDocumentType;
use App\Enums\ParafiscalDocumentType;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentBatch;
use App\Models\SupervisorPost;
use App\Models\User;
use App\Models\UserModuleGrant;
use App\Support\Files\SimplePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class PersonnelDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_sees_documentos_listing(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->pilotVigilante();

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.index'))
            ->assertOk()
            ->assertSee('Carpetas de empleados')
            ->assertSee('Carga masiva planilla')
            ->assertSee($employee->document_number)
            ->assertSee('Ver carpeta');
    }

    public function test_company_admin_can_index_non_contiguous_pages(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->pilotVigilante();
        $type = LaborHistoryDocumentType::FotocopiaCedula;
        $batch = $this->uploadLote($admin, $employee, ['Pagina uno', 'Pagina dos', 'Pagina tres'], 'hv-lote.pdf');

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.batch.index', [$employee, $batch]))
            ->assertOk()
            ->assertSee('Indexar lote')
            ->assertSee('Historia Laboral');

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.batch.preview', [$employee, $batch]))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('company.personnel-documents.batch.store', [$employee, $batch]), [
                'slices' => [[
                    'folder' => DocumentFolder::HojaVida->value,
                    'document_type' => $type->value,
                    'display_name' => $type->suggestedName($employee),
                    'pages' => [1, 3],
                ]],
            ])
            ->assertRedirect(route('company.personnel-documents.folder', $employee));

        $document = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('document_type', $type->value)
            ->firstOrFail();

        $this->assertSame([1, 3], $document->pages);
        $this->assertTrue(is_file(storage_path('app/'.$document->disk_path)));

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.folder', $employee))
            ->assertOk()
            ->assertSee('1 de 26');
    }

    public function test_supervision_only_client_cannot_enable_personnel_folders(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $client->update(['has_access' => false, 'has_supervision' => true, 'show_personnel_folders' => false]);

        $this->actingAs($admin)
            ->put(route('company.clients.update', $client), $this->clientPayload($client, [
                'has_access' => '0',
                'has_supervision' => '1',
                'show_personnel_folders' => '1',
            ]));

        $this->assertFalse($client->fresh()->show_personnel_folders);
    }

    public function test_client_panel_only_lists_employees_assigned_to_its_posts(): void
    {
        $this->seedWithPilot();
        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $torres = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $palmas->update(['show_personnel_folders' => true]);
        $torres->update(['has_access' => true, 'show_personnel_folders' => true]);

        $assigned = $this->pilotVigilante();
        $other = $assigned->replicate();
        $other->document_number = '1099007788';
        $other->first_names = 'Otro';
        $other->last_name_paternal = 'Gómez';
        $other->email = 'otro.gomez@sj-seguridad.test';
        $other->save();

        $post = SupervisorPost::query()->where('client_id', $palmas->id)->firstOrFail();
        $post->employees()->sync([$assigned->id]);

        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();
        $this->grantPersonnelDocuments($clientAdmin, 'view');
        UserModuleGrant::query()->create([
            'user_id' => $clientAdmin->id,
            'scope' => AccessGrantScope::Client,
            'scope_id' => $palmas->id,
            'module' => 'documents',
            'level' => AccessGrantLevel::View,
        ]);
        $clientAdmin->unsetRelation('moduleGrants');

        $this->actingAs($clientAdmin)
            ->withSession(['tenancy.active_client_id' => $palmas->id])
            ->get(route('client.personnel-documents.index'))
            ->assertOk()
            ->assertSee($assigned->document_number)
            ->assertDontSee($other->document_number);

        $this->actingAs($clientAdmin)
            ->withSession(['tenancy.active_client_id' => $palmas->id])
            ->get(route('client.personnel-documents.folder', $other))
            ->assertNotFound();

        $this->actingAs($clientAdmin)
            ->withSession(['tenancy.active_client_id' => $palmas->id])
            ->get(route('client.personnel-documents.folder', $assigned))
            ->assertOk()
            ->assertSee('Historia Laboral')
            ->assertDontSee('Cargar documentos')
            ->assertDontSee('Descargar');
    }

    public function test_documents_view_cannot_download_manage_can(): void
    {
        $this->seedWithPilot();
        $palmas = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $palmas->update(['show_personnel_folders' => true]);
        $assigned = $this->pilotVigilante();
        $post = SupervisorPost::query()->where('client_id', $palmas->id)->firstOrFail();
        $post->employees()->sync([$assigned->id]);
        $document = $this->storedPersonnelPdf($assigned);
        $session = ['tenancy.active_client_id' => $palmas->id];
        $clientAdmin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        $this->grantPersonnelDocuments($clientAdmin, 'view');
        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.personnel-documents.preview', $document))
            ->assertOk();
        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.personnel-documents.download', $document))
            ->assertForbidden();
        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.personnel-documents.folder', $assigned))
            ->assertOk()
            ->assertDontSee('Descargar');

        $this->grantPersonnelDocuments($clientAdmin, 'manage');
        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.personnel-documents.download', $document))
            ->assertOk();
        $this->actingAs($clientAdmin)->withSession($session)
            ->get(route('client.personnel-documents.folder', $assigned))
            ->assertOk()
            ->assertSee('Descargar');
    }

    public function test_client_without_flag_cannot_open_personnel_documents(): void
    {
        $this->seedWithPilot();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $client->update(['show_personnel_folders' => false]);
        $admin = User::query()->where('email', 'admin@palmasdelingenio.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenancy.active_client_id' => $client->id])
            ->get(route('client.personnel-documents.index'))
            ->assertForbidden();
    }

    public function test_company_admin_can_commit_parafiscal_planilla_and_replace_previous(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->pilotVigilante();
        $missing = '999888777';

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.folder', $employee))
            ->assertOk()
            ->assertSee('Parafiscales');

        $first = $this->planillaUpload('pila-agosto.xlsx', [
            ['document' => $employee->document_number, 'name' => 'PILOT UNO', 'totals' => [180400, 100]],
            ['document' => $missing, 'name' => 'AUSENTE', 'totals' => [50]],
        ]);

        $this->actingAs($admin)
            ->post(route('company.personnel-documents.parafiscales.preview.store'), ['file' => $first])
            ->assertRedirect(route('company.personnel-documents.parafiscales.preview'));

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.parafiscales.preview'))
            ->assertOk()
            ->assertSee($employee->document_number)
            ->assertSee($missing)
            ->assertSee('Aceptar y cargar');

        $this->actingAs($admin)
            ->post(route('company.personnel-documents.parafiscales.commit'))
            ->assertRedirect(route('company.personnel-documents.index'));

        $this->assertSame(0, Employee::query()->where('document_number', $missing)->count());

        $docs = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('folder', DocumentFolder::Parafiscales)
            ->where('document_type', ParafiscalDocumentType::Planilla->value)
            ->get();
        $this->assertCount(1, $docs);
        $document = $docs->first();
        $this->assertSame('2026-08-01', $document->taken_on?->format('Y-m-d'));
        $absolute = storage_path('app/'.$document->disk_path);
        $this->assertTrue(is_file($absolute));

        $sheet = IOFactory::load($absolute)->getActiveSheet();
        $this->assertSame(180500.0, (float) $sheet->getCell('BJ15')->getCalculatedValue());
        $this->assertSame((float) $employee->document_number, (float) $sheet->getCell('E21')->getCalculatedValue());
        $this->assertSame((float) $employee->document_number, (float) $sheet->getCell('E22')->getCalculatedValue());
        $this->assertSame('', trim((string) $sheet->getCell('E23')->getFormattedValue()));
        $this->assertNotSame((float) $missing, (float) $sheet->getCell('E21')->getCalculatedValue());
        $this->assertNotSame((float) $missing, (float) $sheet->getCell('E22')->getCalculatedValue());

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.preview', $document))
            ->assertOk()
            ->assertSee('PILOT UNO');

        $second = $this->planillaUpload('pila-octubre.xlsx', [
            ['document' => $employee->document_number, 'name' => 'PILOT UNO', 'totals' => [200000]],
        ], '2026-10', '2026-10');

        $this->actingAs($admin)
            ->post(route('company.personnel-documents.parafiscales.preview.store'), ['file' => $second])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('company.personnel-documents.parafiscales.commit'))
            ->assertRedirect(route('company.personnel-documents.index'));

        $docs = EmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('folder', DocumentFolder::Parafiscales)
            ->get();
        $this->assertCount(1, $docs);
        $replaced = storage_path('app/'.$docs->first()->disk_path);
        $this->assertSame(200000.0, (float) IOFactory::load($replaced)->getActiveSheet()->getCell('BJ15')->getCalculatedValue());
        $this->assertFalse(is_file($absolute));
    }

    public function test_parafiscal_preview_finds_cedulas_on_pila_sheet_after_aportante_header(): void
    {
        $this->seedWithPilot();
        $admin = $this->companyAdmin();
        $employee = $this->pilotVigilante();

        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Portada');
        $book->getActiveSheet()->setCellValue('B8', 'Identificación del aportante');
        $sheet = $book->createSheet();
        $sheet->setTitle('mafars191');
        $sheet->setCellValue('B8', 'Identificación del aportante');
        $sheet->setCellValue('B14', 'Pensión');
        $sheet->setCellValue('H14', 'Salud');
        $sheet->setCellValue('BJ14', 'Valor');
        $sheet->setCellValue('B15', '2026-08');
        $sheet->setCellValue('H15', '2026-09');
        $sheet->setCellValue('D20', 'Identificación');
        $sheet->setCellValue('I20', 'Nombre');
        $sheet->setCellValue('BW20', 'Total Aportes');
        $sheet->setCellValue('D147', 'CC');
        $sheet->setCellValue('E147', $employee->document_number);
        $sheet->setCellValue('I147', 'PILOTO');
        $sheet->setCellValue('BW147', 180400);

        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'pila-mafars.xlsx';
        (new Xlsx($book))->save($path);
        $file = new UploadedFile(
            $path,
            'pila-mafars.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $this->actingAs($admin)
            ->post(route('company.personnel-documents.parafiscales.preview.store'), ['file' => $file])
            ->assertRedirect(route('company.personnel-documents.parafiscales.preview'));

        $this->actingAs($admin)
            ->get(route('company.personnel-documents.parafiscales.preview'))
            ->assertOk()
            ->assertSee($employee->document_number)
            ->assertSee('Aceptar y cargar');
    }

    /**
     * @param  list<array{document: string, name: string, totals: list<float>}>  $people
     */
    private function planillaUpload(string $filename, array $people, string $pension = '2026-08', string $salud = '2026-09'): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('B14', 'Pensión');
        $sheet->setCellValue('H14', 'Salud');
        $sheet->setCellValue('BJ14', 'Valor');
        $sheet->setCellValue('B15', $pension);
        $sheet->setCellValue('H15', $salud);
        $sheet->setCellValue('BJ15', 0);
        $sheet->setCellValue('D20', 'Identificación');
        $sheet->setCellValue('I20', 'Nombre');
        $sheet->setCellValue('BW20', 'Total Aportes');

        $row = 21;
        foreach ($people as $person) {
            foreach ($person['totals'] as $total) {
                $sheet->setCellValue('D'.$row, 'CC');
                $sheet->setCellValue('E'.$row, $person['document']);
                $sheet->setCellValue('I'.$row, $person['name']);
                $sheet->setCellValue('BW'.$row, $total);
                $row++;
            }
        }

        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.$filename;
        (new Xlsx($book))->save($path);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function companyAdmin(): User
    {
        return User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
    }

    private function grantPersonnelDocuments(User $user, string $level): void
    {
        $user->givePermissionTo('company.documents.view');
        if ($level === 'manage') {
            $user->givePermissionTo('company.documents.manage');
        } elseif ($user->hasPermissionTo('company.documents.manage')) {
            $user->revokePermissionTo('company.documents.manage');
        }
        $user->unsetRelation('permissions');
        $user->load('permissions');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function storedPersonnelPdf(Employee $employee): EmployeeDocument
    {
        $relative = 'testing/personnel/'.$employee->id.'/cedula.pdf';
        $absolute = storage_path('app/'.$relative);
        SimplePdf::write($absolute, 'Cedula', (string) $employee->document_number);

        return EmployeeDocument::query()->create([
            'security_company_id' => $employee->security_company_id,
            'employee_id' => $employee->id,
            'folder' => DocumentFolder::HojaVida,
            'document_type' => LaborHistoryDocumentType::FotocopiaCedula->value,
            'display_name' => 'Cédula',
            'original_name' => 'cedula.pdf',
            'disk_path' => $relative,
            'mime' => 'application/pdf',
            'size_bytes' => filesize($absolute) ?: 0,
            'not_applicable' => false,
        ]);
    }

    private function pilotVigilante(): Employee
    {
        return Employee::query()->where('document_number', '1144001122')->firstOrFail();
    }

    /** @param list<string> $pages */
    private function uploadLote(User $user, Employee $employee, array $pages, string $filename): EmployeeDocumentBatch
    {
        $path = storage_path('framework/testing/'.$filename);
        SimplePdf::writePages($path, $pages);
        $file = new UploadedFile($path, $filename, 'application/pdf', null, true);

        $this->actingAs($user)
            ->post(route('company.personnel-documents.batch.create', $employee), ['file' => $file])
            ->assertRedirect();

        return EmployeeDocumentBatch::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function clientPayload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'party_type' => $client->party_type->value,
            'name' => $client->name,
            'legal_name' => $client->legal_name,
            'document_type' => $client->document_type,
            'tax_id' => $client->tax_id,
            'email' => $client->email,
            'phone' => $client->phone,
            'representative_name' => $client->representative_name,
            'representative_email' => $client->representative_email,
            'structure_type_id' => $client->structure_type_id,
            'address' => $client->address,
            'city' => $client->city,
            'department' => $client->department,
            'is_active' => $client->is_active ? '1' : '0',
            'has_access' => $client->has_access ? '1' : '0',
            'has_supervision' => $client->has_supervision ? '1' : '0',
            'show_personnel_folders' => $client->show_personnel_folders ? '1' : '0',
        ], $overrides);
    }
}
