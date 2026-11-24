<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CorrectSupervisorShiftOdometerRequest;
use App\Models\SupervisorShift;
use App\Services\Company\CorrectSupervisorShiftOdometerService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;

final class CorrectSupervisorShiftOdometerController extends Controller
{
    public function __construct(
        private readonly CorrectSupervisorShiftOdometerService $correct,
    ) {}

    public function update(CorrectSupervisorShiftOdometerRequest $request, SupervisorShift $shift): RedirectResponse
    {
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        abort_unless((int) $shift->security_company_id === $companyId, 404);

        $kmEnd = $request->filled('km_end') ? $request->integer('km_end') : null;
        $this->correct->execute(
            $shift,
            $request->integer('km_start'),
            $kmEnd,
            trim((string) $request->validated('reason')),
        );

        return redirect()
            ->route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $shift->id])
            ->with('success', 'Kilometraje corregido. El último del vehículo quedó con la lectura más reciente.');
    }
}
