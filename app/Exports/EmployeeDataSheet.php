<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Employee;
use App\Support\Employee\EmployeeExcelSchema;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class EmployeeDataSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly int $companyId) {}

    public function title(): string
    {
        return EmployeeExcelSchema::DATA_SHEET;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return EmployeeExcelSchema::headers();
    }

    /** @return list<list<string>> */
    public function array(): array
    {
        return Employee::query()
            ->with(['jobTitle', 'collaboratorType', 'supervisorPosts.installation.client'])
            ->where('security_company_id', $this->companyId)
            ->where('is_active', true)
            ->orderBy('last_name_paternal')
            ->orderBy('first_names')
            ->get()
            ->map(fn (Employee $employee): array => $this->row($employee))
            ->values()
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }

    /** @return list<string> */
    private function row(Employee $employee): array
    {
        $post = $employee->supervisorPosts->first();
        $installation = $post?->installation;
        $client = $installation?->client;

        $values = [
            'document_type' => $employee->document_type,
            'document_number' => $employee->document_number,
            'last_name_paternal' => $employee->last_name_paternal,
            'last_name_maternal' => $employee->last_name_maternal,
            'first_names' => $employee->first_names,
            'sex' => $employee->sex?->label(),
            'age' => $employee->age(),
            'collaborator_type' => $employee->collaboratorType?->name ?? $employee->collaborator_type,
            'client_legal_name' => $client ? ($client->legal_name ?: $client->name) : null,
            'installation' => $installation?->name,
            'sector' => $installation?->city,
            'post' => $post?->name,
            'job_title' => $employee->jobTitle?->name,
            'same_cost_center' => $this->flag($employee->same_cost_center),
            'birth_date' => $this->date($employee->birth_date),
            'birth_department' => $employee->birth_department,
            'birth_city' => $employee->birth_city,
            'emergency_phone' => $employee->emergency_phone,
            'emergency_contact' => $employee->emergency_contact,
            'nationality' => $employee->nationality,
            'has_disability' => $this->flag($employee->has_disability),
            'email' => $employee->email,
            'document_issue_department' => $employee->document_issue_department,
            'document_issue_city' => $employee->document_issue_city,
            'document_issued_at' => $this->date($employee->document_issued_at),
            'blood_group' => $employee->blood_group?->value,
            'phone' => $employee->phone,
            'residence_city' => $employee->residence_city,
            'address' => $employee->address,
            'education' => $employee->education,
            'marital_status' => $employee->marital_status,
            'children_count' => $employee->children_count,
            'engagement_type' => $employee->engagement_type,
            'contributor_type' => $employee->contributor_type,
            'labor_contract_type' => $employee->labor_contract_type,
            'hired_on' => $this->date($employee->hired_on),
            'labor_contract_ends_on' => $this->date($employee->labor_contract_ends_on),
            'left_on' => $this->date($employee->left_on),
            'eps_code' => $employee->eps_code,
            'eps_name' => $employee->eps_name,
            'afp_code' => $employee->afp_code,
            'afp_name' => $employee->afp_name,
            'compensation_fund' => $employee->compensation_fund,
            'arl_name' => $employee->arl_name,
            'arl_risk_level' => $employee->arl_risk_level,
        ];

        return array_map(
            fn (string $key): string => (string) ($values[$key] ?? ''),
            EmployeeExcelSchema::keys(),
        );
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->format('d/m/Y');
    }

    private function flag(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'SI' : 'NO');
    }
}
