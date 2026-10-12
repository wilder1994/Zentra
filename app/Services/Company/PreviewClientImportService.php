<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\PartyType;
use App\Models\Client;
use App\Models\IdentityDocumentType;
use App\Models\SecurityCompany;
use App\Models\StructureType;
use App\Support\Client\ClientExcelSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class PreviewClientImportService
{
    public const CACHE_TTL_SECONDS = 1800;

    public const MAX_ROWS = 2000;

    public function cacheKey(int $companyId, int $userId): string
    {
        return "client-import.{$companyId}.{$userId}";
    }

    /** @return array<string, mixed> */
    public function previewFile(UploadedFile $file, int $companyId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName(ClientExcelSchema::DATA_SHEET)
            ?? $spreadsheet->getSheet(0);

        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $headerCount = max($highestColumnIndex, count(ClientExcelSchema::headers()));
        for ($column = 1; $column <= $headerCount; $column++) {
            $headers[] = trim((string) $sheet->getCell(ClientExcelSchema::cellAddress($column, 1))->getFormattedValue());
        }

        $map = ClientExcelSchema::mapHeaderIndexes($headers);
        $highestRow = (int) $sheet->getHighestDataRow();
        $lookup = $this->companyLookup($companyId);
        $rows = [];

        for ($line = 2; $line <= $highestRow; $line++) {
            $values = [];
            foreach ($map as $key => $index) {
                $values[$key] = trim((string) $sheet->getCell(ClientExcelSchema::cellAddress($index + 1, $line))->getFormattedValue());
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
        $map = ClientExcelSchema::mapHeaderIndexes($headers);
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
            throw new InvalidArgumentException('El archivo no tiene filas de clientes.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Máximo '.self::MAX_ROWS.' filas por carga.');
        }

        $this->flagInternalDuplicates($rows);
        $this->flagSeatOverflow($rows, $companyId);

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
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function validateRow(array $values, int $line, array $lookup): array
    {
        $errors = [];
        $warnings = [];

        $partyType = $this->parsePartyType($values['party_type']);
        if ($partyType === null) {
            $errors[] = 'Tipo de cliente: jurídica o natural.';
        }

        $name = trim($values['name']);
        if ($name === '') {
            $errors[] = 'Falta el nombre comercial.';
        }

        $legalName = trim($values['legal_name']);
        if ($partyType === PartyType::LegalEntity && $legalName === '') {
            $errors[] = 'Falta la razón social.';
        }

        $documentType = IdentityDocumentType::resolveActiveCode($values['document_type']);
        if ($documentType === null) {
            $errors[] = 'Tipo de documento no válido.';
        }

        $taxId = trim($values['tax_id']);
        if ($taxId === '') {
            $errors[] = 'Falta el número de documento.';
        } elseif (isset($lookup['tax_ids'][mb_strtolower($taxId)])) {
            $errors[] = 'Ya existe un cliente con ese documento.';
        }

        $email = trim($values['email']);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email de contacto inválido.';
        }

        $representativeName = trim($values['representative_name']);
        $representativeEmail = trim($values['representative_email']);
        if ($partyType === PartyType::LegalEntity) {
            if ($representativeName === '') {
                $errors[] = 'Falta el representante.';
            }
            if ($representativeEmail === '' || ! filter_var($representativeEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email del representante inválido.';
            }
        } elseif ($representativeEmail !== '' && ! filter_var($representativeEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email del representante inválido.';
        }

        $structureTypeId = $lookup['structure_types'][mb_strtolower(trim($values['structure_type']))] ?? null;
        if ($structureTypeId === null) {
            $errors[] = 'Tipo de estructura no existe en el catálogo.';
        }

        $hasAccess = $this->parseFlag($values['has_access']);
        $hasSupervision = $this->parseFlag($values['has_supervision']);
        if ($hasAccess === null) {
            $errors[] = 'Accesos debe ser SI o NO.';
        }
        if ($hasSupervision === null) {
            $errors[] = 'Supervisión debe ser SI o NO.';
        }

        $status = $errors === [] ? ($warnings === [] ? 'ok' : 'warning') : 'error';

        return [
            'line' => $line,
            'status' => $status,
            'name' => $name,
            'document' => trim(($documentType ?? '').' '.$taxId),
            'messages' => array_merge($errors, $warnings),
            'payload' => $errors === [] ? [
                'party_type' => $partyType?->value,
                'name' => $name,
                'legal_name' => $legalName !== '' ? $legalName : $name,
                'document_type' => $documentType,
                'tax_id' => $taxId,
                'email' => $email,
                'phone' => trim($values['phone']) ?: null,
                'representative_name' => $representativeName !== '' ? $representativeName : null,
                'representative_email' => $representativeEmail !== '' ? $representativeEmail : null,
                'structure_type_id' => $structureTypeId,
                'address' => trim($values['address']) ?: null,
                'city' => trim($values['city']) ?: null,
                'department' => trim($values['department']) ?: null,
                'has_access' => (bool) $hasAccess,
                'has_supervision' => (bool) $hasSupervision,
            ] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flagInternalDuplicates(array &$rows): void
    {
        $seen = [];
        foreach ($rows as $index => $row) {
            $payload = $row['payload'] ?? null;
            if (! is_array($payload)) {
                continue;
            }
            $key = mb_strtolower((string) $payload['tax_id']);
            if (isset($seen[$key])) {
                $rows[$index]['status'] = 'error';
                $rows[$index]['messages'][] = 'Documento duplicado en el archivo.';
                $rows[$index]['payload'] = null;
            }
            $seen[$key] = true;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function flagSeatOverflow(array &$rows, int $companyId): void
    {
        $company = SecurityCompany::query()->findOrFail($companyId);
        $accessLeft = $company->accessSeatsRemaining();
        $proLeft = $company->supervisionSeatsRemaining();
        $installationsLeft = $company->installationSeatsRemaining();

        foreach ($rows as $index => $row) {
            $payload = $row['payload'] ?? null;
            if (! is_array($payload) || ($row['status'] ?? '') === 'error') {
                continue;
            }

            if ($installationsLeft < 1) {
                $rows[$index]['status'] = 'error';
                $rows[$index]['messages'][] = 'No hay cupo de instalaciones ('.$company->installationSeatsCount().'/'.$company->installationQuota()->capacity().'). Archiva una instalación o un cliente, o amplía el plan en Facturación.';
                $rows[$index]['payload'] = null;

                continue;
            }
            $installationsLeft--;

            if (! empty($payload['has_access'])) {
                if ($accessLeft < 1) {
                    $rows[$index]['status'] = 'error';
                    $rows[$index]['messages'][] = 'Sin cupo de Accesos. Deja Accesos en NO o amplía el paquete.';
                    $rows[$index]['payload'] = null;

                    continue;
                }
                $accessLeft--;
            }

            if (! empty($payload['has_supervision'])) {
                if ($proLeft < 1) {
                    $rows[$index]['status'] = 'error';
                    $rows[$index]['messages'][] = 'Sin cupo de Supervisión. Deja Supervisión en NO o contrata el paquete.';
                    $rows[$index]['payload'] = null;

                    continue;
                }
                $proLeft--;
            }
        }
    }

    /** @return array<string, mixed> */
    private function companyLookup(int $companyId): array
    {
        $taxIds = Client::query()
            ->where('security_company_id', $companyId)
            ->pluck('tax_id')
            ->filter()
            ->mapWithKeys(fn ($taxId) => [mb_strtolower((string) $taxId) => true])
            ->all();

        $structureTypes = [];
        foreach (StructureType::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'code', 'name']) as $type) {
            $structureTypes[mb_strtolower((string) $type->code)] = $type->id;
            $structureTypes[mb_strtolower((string) $type->name)] = $type->id;
        }

        return [
            'tax_ids' => $taxIds,
            'structure_types' => $structureTypes,
        ];
    }

    private function parsePartyType(string $raw): ?PartyType
    {
        $key = mb_strtolower(trim($raw));
        $key = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $key);

        return match ($key) {
            'legal_entity', 'juridica', 'persona juridica', 'pj' => PartyType::LegalEntity,
            'natural_person', 'natural', 'persona natural', 'pn' => PartyType::NaturalPerson,
            default => PartyType::tryFrom($key),
        };
    }

    private function parseFlag(string $raw): ?bool
    {
        $key = mb_strtolower(trim($raw));
        if ($key === '') {
            return false;
        }

        return match ($key) {
            'si', 'sí', 'yes', '1', 'x', 'true' => true,
            'no', '0', 'false' => false,
            default => null,
        };
    }

    /** @param  array<string, string>  $values */
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
            return explode("\t", $line);
        }

        return str_getcsv($line, ';');
    }
}
