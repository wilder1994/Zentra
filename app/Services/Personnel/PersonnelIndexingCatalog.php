<?php

declare(strict_types=1);

namespace App\Services\Personnel;

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
use App\Models\PersonnelFolder;
use App\Models\PersonnelFolderType;
use App\Support\Personnel\IndexedFolder;
use Illuminate\Support\Str;

final class PersonnelIndexingCatalog
{
    public function __construct(
        private readonly EnsurePersonnelCatalogService $ensure,
    ) {}

    /**
     * @return list<array{value: string, label: string, course_fields: bool, other_fields: bool, types: list<array{value: string, label: string, name: string, req: string, other: bool}>}>
     */
    public function forEmployee(Employee $employee): array
    {
        $companyId = (int) $employee->security_company_id;
        $this->ensure->ensure($companyId);

        $folders = PersonnelFolder::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->where('behavior', '!=', PersonnelFolder::PARAFISCAL)
            ->with(['types' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $folders->map(function (PersonnelFolder $folder) use ($employee): array {
            return [
                'value' => $folder->slug,
                'label' => $folder->name,
                'course_fields' => $folder->behavior === PersonnelFolder::COURSE,
                'other_fields' => $folder->behavior === PersonnelFolder::OTHER,
                'types' => $folder->types->map(fn (PersonnelFolderType $type): array => [
                    'value' => $type->slug,
                    'label' => $type->name,
                    'name' => $this->suggestedName($folder, $type, $employee),
                    'req' => DocumentRequirement::tryFrom($type->requirement)?->label() ?? 'Opcional',
                    'other' => $type->is_repeatable,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    public function suggestedName(PersonnelFolder $folder, PersonnelFolderType $type, Employee $employee): string
    {
        $enumFolder = DocumentFolder::tryFrom($folder->slug);
        if ($enumFolder !== null) {
            try {
                $resolved = IndexedFolder::resolve($enumFolder, $type->slug);

                return $resolved->suggestedName($employee);
            } catch (\InvalidArgumentException) {
                // Tipo creado en el panel: no está en el enum.
            }
        }

        $who = Str::of($employee->full_name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $stem = Str::of($type->name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->limit(60, '')
            ->toString();

        return $stem.'_'.$employee->document_number.'_'.$who;
    }

    public function isKnownEnumType(string $folderSlug, string $typeSlug): LaborHistoryDocumentType|AffiliationDocumentType|CertificateDocumentType|ContractingDocumentType|CourseDocumentType|OtherDocumentType|ParafiscalDocumentType|null
    {
        $folder = DocumentFolder::tryFrom($folderSlug);
        if ($folder === null) {
            return null;
        }

        try {
            return IndexedFolder::resolve($folder, $typeSlug);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
