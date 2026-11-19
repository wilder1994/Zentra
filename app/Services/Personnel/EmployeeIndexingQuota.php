<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Employee;
use App\Models\SecurityCompany;
use Illuminate\Validation\ValidationException;

final class EmployeeIndexingQuota
{
    public function used(int $companyId): int
    {
        return Employee::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->where('indexing_enabled', true)
            ->count();
    }

    public function seats(int $companyId): int
    {
        return (int) SecurityCompany::query()->whereKey($companyId)->value('indexing_seats');
    }

    public function canEnable(int $companyId): bool
    {
        $seats = $this->seats($companyId);

        return $seats > 0 && $this->used($companyId) < $seats;
    }

    public function enable(Employee $employee): void
    {
        if ($employee->indexing_enabled) {
            return;
        }

        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'indexing' => 'Solo se puede habilitar la indexación de un empleado activo.',
            ]);
        }

        if (! $this->canEnable((int) $employee->security_company_id)) {
            throw ValidationException::withMessages([
                'indexing' => 'El cupo de indexación está lleno. Desactiva a otro empleado o amplía el plan.',
            ]);
        }

        $employee->update(['indexing_enabled' => true]);
    }

    public function disable(Employee $employee): void
    {
        if (! $employee->indexing_enabled) {
            return;
        }

        $employee->update(['indexing_enabled' => false]);
    }
}
