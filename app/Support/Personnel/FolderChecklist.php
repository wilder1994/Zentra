<?php

namespace App\Support\Personnel;

use App\Enums\AffiliationDocumentType;
use App\Enums\CertificateDocumentType;
use App\Enums\ContractingDocumentType;
use App\Enums\CourseDocumentType;
use App\Enums\DocumentFolder;
use App\Enums\DocumentRequirement;
use App\Enums\LaborHistoryDocumentType;
use App\Enums\OtherDocumentType;
use App\Enums\ParafiscalDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;

final class FolderChecklist
{
    /** @return list<array{type: LaborHistoryDocumentType|AffiliationDocumentType|CertificateDocumentType|ContractingDocumentType|CourseDocumentType|OtherDocumentType|ParafiscalDocumentType, document: ?EmployeeDocument, status: string}> */
    public static function for(Employee $employee, DocumentFolder $folder): array
    {
        $files = $employee->documents
            ->filter(fn (EmployeeDocument $doc) => (string) $doc->folder === $folder->value)
            ->values();

        if ($folder === DocumentFolder::Otros || $folder === DocumentFolder::Parafiscales) {
            $rows = [];
            foreach ($files as $file) {
                if (! $file->hasFile()) {
                    continue;
                }
                $rows[] = [
                    'type' => $folder === DocumentFolder::Parafiscales
                        ? ParafiscalDocumentType::Planilla
                        : OtherDocumentType::Otro,
                    'document' => $file,
                    'status' => 'loaded',
                ];
            }

            return $rows;
        }

        $rows = [];
        foreach (IndexedFolder::types($folder) as $type) {
            if ($type instanceof CourseDocumentType && $type->isRepeatable()) {
                continue;
            }

            $match = $files->first(fn (EmployeeDocument $doc) => $doc->document_type === $type->value);

            if ($match === null && $folder === DocumentFolder::Certificados) {
                $match = $employee->documents->first(
                    fn (EmployeeDocument $doc) => (string) $doc->folder === DocumentFolder::HojaVida->value
                        && $doc->document_type === $type->value,
                );
            }

            if ($match === null) {
                $match = self::legacyMatch($folder, $type, $files);
            }

            $status = 'missing';
            if ($match?->not_applicable) {
                $status = 'na';
            } elseif ($match?->hasFile()) {
                $status = 'loaded';
            }

            $rows[] = [
                'type' => $type,
                'document' => $match,
                'status' => $status,
            ];
        }

        if ($folder === DocumentFolder::Cursos) {
            foreach ($files as $file) {
                if ($file->document_type !== CourseDocumentType::Otro->value || ! $file->hasFile()) {
                    continue;
                }
                $rows[] = [
                    'type' => CourseDocumentType::Otro,
                    'document' => $file,
                    'status' => 'loaded',
                ];
            }
        }

        return $rows;
    }

    /** @return array{total: int, loaded: int, required: int, required_loaded: int} */
    public static function summary(Employee $employee, DocumentFolder $folder): array
    {
        $rows = self::for($employee, $folder);
        if ($folder === DocumentFolder::Otros) {
            $loaded = count(array_filter($rows, fn (array $row) => $row['status'] === 'loaded'));

            return [
                'total' => OtherDocumentType::MAX,
                'loaded' => $loaded,
                'required' => 0,
                'required_loaded' => 0,
            ];
        }

        if ($folder === DocumentFolder::Parafiscales) {
            $loaded = count(array_filter($rows, fn (array $row) => $row['status'] === 'loaded'));

            return [
                'total' => $loaded,
                'loaded' => $loaded,
                'required' => 0,
                'required_loaded' => 0,
            ];
        }

        $catalog = array_filter($rows, fn (array $row) => ! ($row['type'] instanceof CourseDocumentType && $row['type']->isRepeatable()));
        $loaded = count(array_filter($rows, fn (array $row) => $row['status'] === 'loaded'));
        $required = count(array_filter($catalog, fn (array $row) => $row['type']->requirement() === DocumentRequirement::Required));
        $requiredLoaded = count(array_filter(
            $catalog,
            fn (array $row) => $row['type']->requirement() === DocumentRequirement::Required && $row['status'] === 'loaded',
        ));

        return [
            'total' => count($catalog),
            'loaded' => $loaded,
            'required' => $required,
            'required_loaded' => $requiredLoaded,
        ];
    }

    /** @param array{total: int, loaded: int} $summary */
    public static function countLabel(array $summary): string
    {
        return $summary['loaded'].' de '.$summary['total'];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EmployeeDocument>  $files
     */
    private static function legacyMatch(
        DocumentFolder $folder,
        LaborHistoryDocumentType|AffiliationDocumentType|CertificateDocumentType|ContractingDocumentType|CourseDocumentType|OtherDocumentType|ParafiscalDocumentType $type,
        $files,
    ): ?EmployeeDocument {
        if ($folder === DocumentFolder::HojaVida && $type === LaborHistoryDocumentType::HojaVida) {
            return $files->first(fn (EmployeeDocument $doc) => $doc->document_type === null && $doc->hasFile());
        }

        if ($folder === DocumentFolder::Certificados && $type instanceof CertificateDocumentType) {
            $needle = match ($type) {
                CertificateDocumentType::ExamenMedicoIngreso => 'aptitud',
                CertificateDocumentType::ExamenPsicofisico => 'psicofis',
                CertificateDocumentType::ExamenPsicosensometrico => 'psicosenso',
            };

            return $files->first(function (EmployeeDocument $doc) use ($needle): bool {
                return $doc->document_type === null
                    && $doc->hasFile()
                    && str_contains(strtolower($doc->original_name), $needle);
            });
        }

        if ($folder === DocumentFolder::Cursos && $type === CourseDocumentType::EspecializacionManejoDefensivo) {
            return $files->first(function (EmployeeDocument $doc): bool {
                $name = strtolower($doc->original_name);

                return $doc->document_type === null
                    && $doc->hasFile()
                    && str_contains($name, 'defens');
            });
        }

        if ($folder !== DocumentFolder::Afiliaciones || ! $type instanceof AffiliationDocumentType) {
            return null;
        }

        $needle = match ($type) {
            AffiliationDocumentType::Estado => 'estado',
            AffiliationDocumentType::Eps => 'eps',
            AffiliationDocumentType::Cesantias => 'cesant',
            AffiliationDocumentType::Afp => 'pension',
            AffiliationDocumentType::Arl => 'arl',
            AffiliationDocumentType::Caja => 'caja',
            AffiliationDocumentType::SeguroVida => 'vida',
            AffiliationDocumentType::Exequial => 'exequial',
        };

        return $files->first(function (EmployeeDocument $doc) use ($needle): bool {
            return $doc->document_type === null
                && $doc->hasFile()
                && str_contains(strtolower($doc->original_name), $needle);
        });
    }
}
