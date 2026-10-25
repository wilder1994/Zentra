<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\BloodGroup;
use App\Enums\Sex;
use App\Models\CompanyCollaboratorType;
use App\Models\CompanyJobTitle;
use App\Models\Employee;
use App\Models\IdentityDocumentType;
use App\Support\Employee\EmployeeExcelSchema;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class PreviewEmployeeImportService
{
    public const CACHE_TTL_SECONDS = 1800;

    public const MAX_ROWS = 2000;

    public function cacheKey(int $companyId, int $userId): string
    {
        return "employee-import.{$companyId}.{$userId}";
    }

    /** @return array<string, mixed> */
    public function previewFile(UploadedFile $file, int $companyId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName(EmployeeExcelSchema::DATA_SHEET)
            ?? $spreadsheet->getSheetByName('WM')
            ?? $spreadsheet->getSheet(0);

        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headerCount = max($highestColumnIndex, count(EmployeeExcelSchema::headers()));
        for ($column = 1; $column <= $headerCount; $column++) {
            $headers[] = trim((string) $sheet->getCell(EmployeeExcelSchema::cellAddress($column, 1))->getFormattedValue());
        }

        $map = EmployeeExcelSchema::mapHeaderIndexes($headers);
        $highestRow = (int) $sheet->getHighestDataRow();
        $lookup = $this->companyLookup($companyId);
        $rows = [];

        for ($line = 2; $line <= $highestRow; $line++) {
            $values = [];
            foreach ($map as $key => $index) {
                $cell = $sheet->getCell(EmployeeExcelSchema::cellAddress($index + 1, $line));
                $values[$key] = $this->cellString($cell, $key);
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $rows[] = $this->validateRow($values, $line, $lookup);
        }

        return $this->finalize($rows, $companyId);
    }

    /** @return array<string, mixed> */
    public function previewPaste(string $paste, int $companyId): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($paste)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            throw new InvalidArgumentException('No hay filas para revisar.');
        }

        $headers = $this->splitRow($lines[0]);
        $map = EmployeeExcelSchema::mapHeaderIndexes($headers);
        $lookup = $this->companyLookup($companyId);
        $rows = [];

        foreach (array_slice($lines, 1) as $offset => $line) {
            $cells = $this->splitRow($line);
            $values = [];
            foreach ($map as $key => $index) {
                $values[$key] = trim((string) ($cells[$index] ?? ''));
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $rows[] = $this->validateRow($values, $offset + 2, $lookup);
        }

        return $this->finalize($rows, $companyId);
    }

    /** @param array<string, mixed> $preview */
    public function put(int $companyId, int $userId, array $preview): void
    {
        Cache::put($this->cacheKey($companyId, $userId), $preview, self::CACHE_TTL_SECONDS);
    }

    /** @return array<string, mixed>|null */
    public function get(int $companyId, int $userId): ?array
    {
        $preview = Cache::get($this->cacheKey($companyId, $userId));

        return is_array($preview) ? $preview : null;
    }

    public function forget(int $companyId, int $userId): void
    {
        Cache::forget($this->cacheKey($companyId, $userId));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function finalize(array $rows, int $companyId): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('El archivo no tiene filas de empleados.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Máximo '.self::MAX_ROWS.' filas por carga.');
        }

        $this->flagInternalDuplicates($rows);

        $ok = 0;
        $error = 0;
        $warning = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'error') {
                $error++;
            } elseif ($row['status'] === 'warning') {
                $warning++;
                $ok++;
            } else {
                $ok++;
            }
        }

        return [
            'company_id' => $companyId,
            'ok' => $ok,
            'error' => $error,
            'warning' => $warning,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, string>  $values
     * @param  array{titles: array<string, true>, types: array<string, true>, documents: array<string, array{id: int, email: string, job_title: string}>, emails: array<string, array{id: int}>, document_types: array<string, string>}  $lookup
     * @return array<string, mixed>
     */
    private function validateRow(array $values, int $line, array $lookup): array
    {
        $errors = [];
        $warnings = [];

        $documentTypeKey = mb_strtolower(trim($values['document_type']));
        $documentType = $lookup['document_types'][$documentTypeKey] ?? null;
        if ($documentType === null) {
            $errors[] = 'Tipo de documento no válido.';
        }

        $documentNumber = trim($values['document_number']);
        if ($documentNumber === '') {
            $errors[] = 'Falta el número de documento.';
        }

        $lastNamePaternal = trim($values['last_name_paternal']);
        $lastNameMaternal = trim($values['last_name_maternal']);
        $firstNames = trim($values['first_names']);
        if ($firstNames === '') {
            $errors[] = 'Faltan los nombres.';
        }
        if ($lastNamePaternal === '' && $lastNameMaternal === '') {
            $errors[] = 'Indica al menos un apellido (paterno o materno).';
        }

        $sex = Sex::tryParse($values['sex']);
        if ($sex === null) {
            $errors[] = 'Sexo debe ser Hombre o Mujer.';
        }

        $collaboratorType = trim($values['collaborator_type']);
        if ($collaboratorType === '') {
            $errors[] = 'Falta el tipo de colaborador.';
        }

        $createsCollaboratorType = false;
        if ($collaboratorType !== '') {
            if (! isset($lookup['types'][mb_strtolower($collaboratorType)])) {
                $createsCollaboratorType = true;
                $warnings[] = 'Se creará el tipo de colaborador «'.$collaboratorType.'».';
            }
        }

        $jobTitle = trim($values['job_title']);
        if ($jobTitle === '') {
            $errors[] = 'Falta el cargo.';
        }

        $createsJobTitle = false;
        if ($jobTitle !== '') {
            if (! isset($lookup['titles'][mb_strtolower($jobTitle)])) {
                $createsJobTitle = true;
                $warnings[] = 'Se creará el cargo «'.$jobTitle.'».';
            }
        }

        $assignmentFilled = [];
        foreach (EmployeeExcelSchema::assignmentKeys() as $key) {
            if (trim($values[$key]) !== '') {
                $assignmentFilled[] = $key;
            }
        }
        if ($assignmentFilled !== []) {
            $warnings[] = 'Razón social, instalaciones, sector y puesto se ignoran: en Zentra el puesto se asigna en la ficha del cliente.';
        }

        $birthDate = $this->parseDateString($values['birth_date']);
        if ($birthDate === null) {
            $errors[] = 'Fecha de nacimiento inválida.';
        } elseif ($birthDate >= now()->toDateString()) {
            $errors[] = 'La fecha de nacimiento debe ser anterior a hoy.';
        }

        $email = mb_strtolower(trim($values['email']));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo (Email Ficha) es obligatorio y debe ser válido.';
        }

        $nationality = trim($values['nationality']);
        if ($nationality === '') {
            $errors[] = 'Falta la nacionalidad.';
        }

        $bloodGroup = BloodGroup::tryParse($values['blood_group']);
        if ($bloodGroup === null) {
            $errors[] = 'Grupo sanguíneo no válido.';
        }

        $issuedAt = trim($values['document_issued_at']) === ''
            ? null
            : $this->parseDateString($values['document_issued_at']);
        if (trim($values['document_issued_at']) !== '' && $issuedAt === null) {
            $errors[] = 'Fecha de expedición inválida.';
        }

        $existingByDocument = ($documentNumber !== '' && isset($lookup['documents'][$documentNumber]))
            ? $lookup['documents'][$documentNumber]
            : null;
        $existingByEmail = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && isset($lookup['emails'][$email]))
            ? $lookup['emails'][$email]
            : null;

        if ($existingByDocument !== null) {
            $previousTitle = (string) ($existingByDocument['job_title'] ?? '');
            if ($jobTitle !== '' && mb_strtolower($previousTitle) !== mb_strtolower($jobTitle)) {
                $warnings[] = 'Ya existe. Se actualizará el cargo de «'.$previousTitle.'» a «'.$jobTitle.'».';
            } else {
                $warnings[] = 'Ya existe un empleado con este documento. Se actualizará la ficha.';
            }
        }

        if ($existingByEmail !== null) {
            $emailOwnerId = (int) $existingByEmail['id'];
            $documentOwnerId = $existingByDocument !== null ? (int) $existingByDocument['id'] : null;
            if ($documentOwnerId === null) {
                $errors[] = 'Ya existe un empleado con este correo.';
            } elseif ($emailOwnerId !== $documentOwnerId) {
                $errors[] = 'Este correo ya pertenece a otro empleado.';
            }
        }

        $status = $errors !== [] ? 'error' : ($warnings !== [] ? 'warning' : 'ok');

        return [
            'line' => $line,
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'name' => trim($firstNames.' '.$lastNamePaternal.' '.$lastNameMaternal),
            'document' => trim(($documentType ?? $values['document_type']).' '.$documentNumber),
            'email' => $email,
            'job_title' => $jobTitle,
            'payload' => $status === 'error' ? null : [
                'employee_id' => $existingByDocument !== null ? $existingByDocument['id'] : null,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'last_name_paternal' => $lastNamePaternal,
                'last_name_maternal' => $lastNameMaternal,
                'first_names' => $firstNames,
                'sex' => $sex?->value,
                'birth_date' => $birthDate,
                'collaborator_type_name' => $collaboratorType,
                'create_collaborator_type' => $createsCollaboratorType,
                'job_title_name' => $jobTitle,
                'create_job_title' => $createsJobTitle,
                'email' => $email,
                'nationality' => $nationality,
                'blood_group' => $bloodGroup?->value,
                'birth_department' => $this->nullable($values['birth_department']),
                'birth_city' => $this->nullable($values['birth_city']),
                'emergency_phone' => $this->nullable($values['emergency_phone']),
                'emergency_contact' => $this->nullable($values['emergency_contact']),
                'has_disability' => $this->parseBoolean($values['has_disability']) ?? false,
                'document_issue_department' => $this->nullable($values['document_issue_department']),
                'document_issue_city' => $this->nullable($values['document_issue_city']),
                'document_issued_at' => $issuedAt,
                'same_cost_center' => $this->parseBoolean($values['same_cost_center']),
                'phone' => $this->nullable($values['phone'] ?? ''),
                'residence_city' => $this->nullable($values['residence_city'] ?? ''),
                'address' => $this->nullable($values['address'] ?? ''),
                'education' => $this->nullable($values['education'] ?? ''),
                'marital_status' => $this->nullable($values['marital_status'] ?? ''),
                'children_count' => $this->nullableInt($values['children_count'] ?? ''),
                'engagement_type' => $this->nullable($values['engagement_type'] ?? ''),
                'contributor_type' => $this->nullable($values['contributor_type'] ?? ''),
                'labor_contract_type' => $this->nullable($values['labor_contract_type'] ?? ''),
                'hired_on' => $this->optionalDate($values['hired_on'] ?? ''),
                'labor_contract_ends_on' => $this->optionalDate($values['labor_contract_ends_on'] ?? ''),
                'left_on' => $this->optionalDate($values['left_on'] ?? ''),
                'eps_code' => $this->nullable($values['eps_code'] ?? ''),
                'eps_name' => $this->nullable($values['eps_name'] ?? ''),
                'afp_code' => $this->nullable($values['afp_code'] ?? ''),
                'afp_name' => $this->nullable($values['afp_name'] ?? ''),
                'compensation_fund' => $this->nullable($values['compensation_fund'] ?? ''),
                'arl_name' => $this->nullable($values['arl_name'] ?? ''),
                'arl_risk_level' => $this->nullable($values['arl_risk_level'] ?? ''),
            ],
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function flagInternalDuplicates(array &$rows): void
    {
        $documents = [];
        $emails = [];

        foreach ($rows as $index => $row) {
            $payload = $row['payload'] ?? null;
            if (! is_array($payload)) {
                continue;
            }

            $doc = (string) $payload['document_number'];
            $email = (string) $payload['email'];

            if (isset($documents[$doc])) {
                $rows[$index]['errors'][] = 'Documento duplicado en la fila '.$documents[$doc].'.';
                $rows[$index]['status'] = 'error';
                $rows[$index]['payload'] = null;
            } else {
                $documents[$doc] = $row['line'];
            }

            if (isset($emails[$email])) {
                $rows[$index]['errors'][] = 'Correo duplicado en la fila '.$emails[$email].'.';
                $rows[$index]['status'] = 'error';
                $rows[$index]['payload'] = null;
            } else {
                $emails[$email] = $row['line'];
            }
        }
    }

    /**
     * @return array{titles: array<string, true>, types: array<string, true>, documents: array<string, array{id: int, email: string, job_title: string}>, emails: array<string, array{id: int}>, document_types: array<string, string>}
     */
    private function companyLookup(int $companyId): array
    {
        $documentTypes = [];
        foreach (IdentityDocumentType::query()->active()->get(['code', 'name']) as $type) {
            $documentTypes[mb_strtolower($type->code)] = $type->code;
            $documentTypes[mb_strtolower($type->name)] = $type->code;
        }

        $employees = Employee::query()
            ->where('security_company_id', $companyId)
            ->with('jobTitle:id,name')
            ->get(['id', 'document_number', 'email', 'job_title_id']);

        $documents = [];
        $emails = [];
        foreach ($employees as $employee) {
            $documents[(string) $employee->document_number] = [
                'id' => $employee->id,
                'email' => mb_strtolower((string) $employee->email),
                'job_title' => (string) ($employee->jobTitle?->name ?? ''),
            ];
            $emails[mb_strtolower((string) $employee->email)] = [
                'id' => $employee->id,
            ];
        }

        return [
            'titles' => CompanyJobTitle::query()
                ->where('security_company_id', $companyId)
                ->pluck('name')
                ->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => true])
                ->all(),
            'types' => CompanyCollaboratorType::query()
                ->where('security_company_id', $companyId)
                ->pluck('name')
                ->mapWithKeys(fn (string $name): array => [mb_strtolower($name) => true])
                ->all(),
            'documents' => $documents,
            'emails' => $emails,
            'document_types' => $documentTypes,
        ];
    }

    private function cellString(Cell $cell, string $key): string
    {
        if (in_array($key, ['birth_date', 'document_issued_at', 'hired_on', 'labor_contract_ends_on', 'left_on'], true)) {
            $value = $cell->getValue();
            if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
        }

        if ($key === 'document_number') {
            $value = $cell->getValue();
            if (is_numeric($value)) {
                $asFloat = (float) $value;
                if (abs($asFloat - round($asFloat)) < 0.0001 && $asFloat < 1e15) {
                    return (string) (int) round($asFloat);
                }
            }
        }

        return trim((string) $cell->getFormattedValue());
    }

    private function parseDateString(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }

        foreach (['d-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $raw);
                if ($date !== false && $date->format($format) === $raw) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseBoolean(string $raw): ?bool
    {
        $value = mb_strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'si', 'sí', 'yes', '1', 'true' => true,
            'no', '0', 'false' => false,
            default => null,
        };
    }

    private function nullable(string $raw): ?string
    {
        $raw = trim($raw);

        return $raw === '' ? null : $raw;
    }

    private function nullableInt(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function optionalDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return $this->parseDateString($raw);
    }

    /** @param array<string, string> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function splitRow(string $line): array
    {
        if (str_contains($line, "\t")) {
            return array_map(trim(...), explode("\t", $line));
        }

        return array_map(trim(...), str_getcsv($line, ';'));
    }
}
