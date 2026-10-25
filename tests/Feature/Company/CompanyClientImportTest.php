<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Exports\ClientImportTemplateExport;
use App\Models\Client;
use App\Models\Installation;
use App\Models\User;
use App\Support\Client\ClientExcelSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class CompanyClientImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_format_and_bulk_actions(): void
    {
        $this->seedWithPilot();

        $this->actingAs($this->admin())
            ->get(route('company.clients.index'))
            ->assertOk()
            ->assertSee('Formato')
            ->assertSee('Carga masiva')
            ->assertSee('open-client-import');
    }

    public function test_template_has_data_and_instructions_sheets(): void
    {
        $this->seedWithPilot();

        $response = $this->actingAs($this->admin())->get(route('company.clients.template'));
        $response->assertOk();
        $response->assertDownload('formato-clientes-zentra.xlsx');

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'formato-clientes-'.uniqid().'.xlsx';
        file_put_contents($tmp, Excel::raw(new ClientImportTemplateExport, \Maatwebsite\Excel\Excel::XLSX));
        $spreadsheet = IOFactory::load($tmp);
        unlink($tmp);

        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertNotNull($spreadsheet->getSheetByName(ClientExcelSchema::DATA_SHEET));
        $this->assertNotNull($spreadsheet->getSheetByName(ClientExcelSchema::INSTRUCTIONS_SHEET));
    }

    public function test_preview_and_commit_creates_catalog_client(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.clients.import.preview.store'), [
            'paste' => $this->pasteRow(),
        ])->assertRedirect(route('company.clients.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.clients.import.preview'))
            ->assertOk()
            ->assertSee('Residencial Importado')
            ->assertSee('Aceptar y cargar');

        $this->actingAs($admin)
            ->post(route('company.clients.import.commit'))
            ->assertRedirect(route('company.clients.index'));

        $client = Client::query()->where('tax_id', '901888777-6')->firstOrFail();
        $this->assertSame('Residencial Importado', $client->name);
        $this->assertFalse($client->has_access);
        $this->assertFalse($client->has_supervision);
    }

    public function test_export_uses_the_import_format_with_registered_clients(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('company.clients.import.preview.store'), [
            'paste' => $this->pasteRow(),
        ]);
        $this->actingAs($admin)->post(route('company.clients.import.commit'));

        $response = $this->actingAs($admin)->get(route('company.clients.export'));
        $response->assertOk();

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clientes-'.uniqid().'.xlsx';
        file_put_contents($tmp, file_get_contents($response->getFile()->getPathname()));
        $spreadsheet = IOFactory::load($tmp);
        unlink($tmp);

        $rows = $spreadsheet->getSheetByName(ClientExcelSchema::DATA_SHEET)->toArray(null, false, false);
        $this->assertSame(ClientExcelSchema::headers(), $rows[0]);

        $imported = collect(array_slice($rows, 1))->first(fn (array $row) => (string) $row[4] === '901888777-6');
        $this->assertNotNull($imported);
        $this->assertSame('Residencial Importado', $imported[1]);
        $this->assertSame('Jurídica', $imported[0]);
        $this->assertSame('NO', $imported[13]);
    }

    public function test_import_rejects_access_when_quota_full(): void
    {
        $this->seedWithPilot();
        $admin = $this->admin();
        $company = $admin->securityCompany;
        $site = Installation::query()
            ->withoutGlobalScopes()
            ->whereHas('client', fn ($q) => $q->where('security_company_id', $company->id))
            ->where('is_active', true)
            ->first();
        $site?->update(['is_active' => false]);
        $company->update(['max_clients' => max(1, $company->accessSeatsCount())]);

        $this->actingAs($admin)->post(route('company.clients.import.preview.store'), [
            'paste' => $this->pasteRow(['has_access' => 'SI']),
        ])->assertRedirect(route('company.clients.import.preview'));

        $this->actingAs($admin)
            ->get(route('company.clients.import.preview'))
            ->assertSee('Sin cupo de Accesos')
            ->assertDontSee('Aceptar y cargar');
    }

    private function admin(): User
    {
        return User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
    }

    /** @param array<string, string> $overrides */
    private function pasteRow(array $overrides = []): string
    {
        $values = array_merge([
            'party_type' => 'Persona jurídica',
            'name' => 'Residencial Importado',
            'legal_name' => 'Residencial Importado PH',
            'document_type' => 'NIT',
            'tax_id' => '901888777-6',
            'email' => 'importado@norte.test',
            'phone' => '3000000000',
            'representative_name' => 'Ana Import',
            'representative_email' => 'ana.import@norte.test',
            'structure_type' => 'ph',
            'address' => 'Calle 1 # 2-3',
            'city' => 'Cali',
            'department' => 'Valle',
            'has_access' => 'NO',
            'has_supervision' => 'NO',
        ], $overrides);

        $line = [];
        foreach (ClientExcelSchema::keys() as $key) {
            $line[] = $values[$key];
        }

        return implode("\t", ClientExcelSchema::headers())."\n".implode("\t", $line);
    }
}
