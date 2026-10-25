<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Empleados activos en el mismo formato que la plantilla de importación (sirve también para Arzen).
 */
final class EmployeeDataExport implements WithMultipleSheets
{
    public function __construct(private readonly int $companyId) {}

    public function sheets(): array
    {
        return [
            new EmployeeDataSheet($this->companyId),
            new EmployeeImportTemplateInstructionsSheet,
        ];
    }
}
