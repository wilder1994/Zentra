<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Exports\EmployeeImportTemplateExport;
use App\Models\Employee;
use App\Models\User;
use App\Support\Employee\EmployeeExcelSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

final class CompanyEmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_format_and_bulk_actions(): void
    {
        $this->seedWithPilot();

        $this->actingAs($this->admin())
            ->get(route('company.employees.index'))
            ->assertOk()
            ->assertSee('Formato')
            ->assertSee('Carga masiva')
            ->assertSee('open-employee-import')
            ->assertSee('Revisar datos');
    }

    public function test_template_has_data_and_instructions_sheets(): void
    {
        $this->seedWithPilot();

        $response = $this->actingAs($this->admin())->get(route('company.employees.template'));
        $response->assertOk();
        $response->assertDownload('formato-empleados-zentra.xlsx');

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'formato-empleados-'.uniqid().'.xlsx';
        file_put_contents($tmp, Excel::raw(new EmployeeImportTemplateExport, \Maatwebsite\Excel\Excel::XLSX));
        $spreadsheet = IOFactory::load($tmp);
        unlink($tmp);

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertNotNull($spreadsheet->getSheetByName(EmployeeExcelSchema::DATA_SHEET));
        $this->assertNotNull($spreadsheet->getSheetByName(EmployeeExcelSchema::INSTRUCTIONS_SHEET));

        $sheet = $spreadsheet->getSheetByName(EmployeeExcelSchema::DATA_SHEET);
        foreach (EmployeeExcelSchema::headers() as $index => $header) {
            $this->assertSame(
                $header,
                trim((string) $sheet->getCell(EmployeeExcelSchema::cellAddress($index + 1, 1))->getFormattedValue()),
            );
        }
    }

    public function test_preview_from_xlsx_file_then_commit(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();
        $path = $this->xlsxWithRow();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'file' => new UploadedFile($path, 'empleados.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $this->assertDatabaseHas('employees', [
            'email' => 'ana.import@sj-seguridad.test',
            'document_number' => '1098000111',
        ]);

        @unlink($path);
    }

    public function test_preview_and_commit_creates_employee_and_job_title(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(),
        ])->assertRedirect(route('company.employees.import.preview'));

        $preview = $this->actingAs($admin)->get(route('company.employees.import.preview'));
        $preview->assertOk();
        $preview->assertSee('Ana');
        $preview->assertSee('Aceptar y cargar');

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $this->assertDatabaseHas('company_job_titles', ['name' => 'ESCOLTA 6X1']);
        $this->assertDatabaseHas('company_collaborator_types', ['name' => 'OPERATIVO']);
        $this->assertDatabaseHas('employees', [
            'email' => 'ana.import@sj-seguridad.test',
            'document_number' => '1098000111',
            'first_names' => 'Ana',
        ]);
    }

    public function test_assignment_columns_are_ignored_with_a_warning(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow([
                'client_legal_name' => 'Palmas del Ingenio',
                'installation' => 'Sede norte',
                'post' => 'Portería',
            ]),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.employees.import.preview'))
            ->assertOk()
            ->assertSee('se ignoran')
            ->assertSee('Aceptar y cargar');

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'email' => 'ana.import@sj-seguridad.test',
        ]);
    }

    public function test_export_uses_the_import_format_with_active_employees(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(),
        ]);
        $this->actingAs($admin)->post(route('company.employees.import.commit'));

        $response = $this->actingAs($admin)->get(route('company.employees.export'));
        $response->assertOk();

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'empleados-'.uniqid().'.xlsx';
        file_put_contents($tmp, file_get_contents($response->getFile()->getPathname()));
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getSheetByName(EmployeeExcelSchema::DATA_SHEET);
        unlink($tmp);

        $rows = $sheet->toArray(null, false, false);
        $this->assertSame(EmployeeExcelSchema::headers(), $rows[0]);

        $documents = array_column(array_slice($rows, 1), 1);
        $this->assertContains('1098000111', array_map('strval', $documents));
    }

    public function test_email_is_required_even_if_grey_in_excel(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(['email' => '']),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.employees.import.preview'))
            ->assertSee('correo');
    }

    public function test_import_accepts_a_single_last_name(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(['last_name_maternal' => '']),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.employees.import.preview'))
            ->assertOk()
            ->assertSee('Aceptar y cargar')
            ->assertDontSee('Indica al menos un apellido');

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $this->assertDatabaseHas('employees', [
            'document_number' => '1098000111',
            'last_name_paternal' => 'Pérez',
            'last_name_maternal' => '',
        ]);
    }

    public function test_import_updates_existing_employee_job_title(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(),
        ])->assertRedirect(route('company.employees.import.preview'));
        $this->actingAs($admin)->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(['job_title' => 'GUARDA DE SEGURIDAD']),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.employees.import.preview'))
            ->assertOk()
            ->assertSee('Se actualizará el cargo')
            ->assertSee('Aceptar y cargar')
            ->assertDontSee('Ya existe un empleado con este documento.');

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $employee = Employee::query()->where('document_number', '1098000111')->firstOrFail();
        $this->assertSame('GUARDA DE SEGURIDAD', $employee->jobTitle?->name);
        $this->assertSame(1, Employee::query()->where('document_number', '1098000111')->count());
    }

    public function test_import_accepts_pending_blood_group(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow(['blood_group' => 'Pendiente']),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.employees.import.preview'))
            ->assertOk()
            ->assertSee('Aceptar y cargar')
            ->assertDontSee('Grupo sanguíneo no válido');

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $this->assertDatabaseHas('employees', [
            'document_number' => '1098000111',
            'blood_group' => 'Pendiente',
        ]);
    }

    public function test_import_rejects_when_both_last_names_are_empty(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow([
                'last_name_paternal' => '',
                'last_name_maternal' => '',
            ]),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.employees.import.preview'))
            ->assertSee('al menos un apellido')
            ->assertDontSee('Aceptar y cargar');
    }

    public function test_import_optional_sj_sig_columns(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.employees.import.preview.store'), [
            'paste' => $this->pasteRow([
                'phone' => '3009998877',
                'eps_name' => 'Nueva EPS',
                'hired_on' => '15-01-2024',
            ]),
        ])->assertRedirect(route('company.employees.import.preview'));

        $this->actingAs($admin)
            ->post(route('company.employees.import.commit'))
            ->assertRedirect(route('company.employees.index'));

        $this->assertDatabaseHas('employees', [
            'document_number' => '1098000111',
            'phone' => '3009998877',
            'eps_name' => 'Nueva EPS',
            'hired_on' => '2024-01-15',
        ]);
    }

    public function test_guard_cannot_download_template(): void
    {
        $this->seedWithPilot();
        $guard = User::query()->where('email', 'guardia@control-acceso.test')->firstOrFail();

        $this->actingAs($guard)->get(route('company.employees.template'))->assertForbidden();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
    }

    /** @param array<string, string> $overrides */
    private function pasteRow(array $overrides = []): string
    {
        $values = array_merge([
            'document_type' => 'CC',
            'document_number' => '1098000111',
            'last_name_paternal' => 'Pérez',
            'last_name_maternal' => 'Gómez',
            'first_names' => 'Ana',
            'sex' => 'Mujer',
            'age' => '',
            'collaborator_type' => 'OPERATIVO',
            'client_legal_name' => '',
            'installation' => '',
            'sector' => '',
            'post' => '',
            'job_title' => 'ESCOLTA 6X1',
            'same_cost_center' => '',
            'birth_date' => '12-05-1990',
            'birth_department' => '',
            'birth_city' => '',
            'emergency_phone' => '',
            'emergency_contact' => '',
            'nationality' => 'COLOMBIANA',
            'has_disability' => 'NO',
            'email' => 'ana.import@sj-seguridad.test',
            'document_issue_department' => '',
            'document_issue_city' => '',
            'document_issued_at' => '',
            'blood_group' => 'O+ (Más común)',
        ], $overrides);

        $line = [];
        foreach (EmployeeExcelSchema::keys() as $key) {
            $line[] = $values[$key] ?? '';
        }

        return implode("\t", EmployeeExcelSchema::headers())."\n".implode("\t", $line);
    }

    private function xlsxWithRow(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(EmployeeExcelSchema::DATA_SHEET);

        foreach (EmployeeExcelSchema::headers() as $index => $header) {
            $sheet->setCellValue(EmployeeExcelSchema::cellAddress($index + 1, 1), $header);
        }

        $values = [
            'CC', '1098000111', 'Pérez', 'Gómez', 'Ana', 'Mujer', '', 'OPERATIVO',
            '', '', '', '', 'ESCOLTA 6X1', '', '12-05-1990', '', '', '', '',
            'COLOMBIANA', 'NO', 'ana.import@sj-seguridad.test', '', '', '', 'O+ (Más común)',
        ];
        foreach ($values as $index => $value) {
            $sheet->setCellValue(EmployeeExcelSchema::cellAddress($index + 1, 2), $value);
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'empleados-import-'.uniqid().'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $path;
    }
}
