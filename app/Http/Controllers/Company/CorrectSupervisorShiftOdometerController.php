<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CorrectSupervisorShiftOdometerRequest;
use App\Models\SupervisorShift;
use App\Models\User;
use App\Services\Company\CorrectSupervisorShiftOdometerService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            $request->user(),
        );

        return redirect()
            ->route('company.supervision.sheets.show', array_filter([
                'kind' => 'shift',
                'id' => $shift->id,
                'embed' => $request->boolean('embed') ? 1 : null,
            ]))
            ->with('success', 'Kilometraje corregido. El último del vehículo quedó con la lectura más reciente.');
    }

    public function accept(Request $request, SupervisorShift $shift): RedirectResponse
    {
        abort_unless($request->user()?->can('company.settings.manage'), 403);
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        abort_unless((int) $shift->security_company_id === $companyId, 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->correct->accept($shift, $actor);

        return redirect()
            ->route('company.supervision.sheets.show', array_filter([
                'kind' => 'shift',
                'id' => $shift->id,
                'embed' => $request->boolean('embed') ? 1 : null,
            ]))
            ->with('success', 'Listo. Este turno ya no aparece en Kilometrajes por revisar.')
            ->with('odometer_accepted', true);
    }
}
