<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\SupervisorShift;
use App\Services\Company\ManageShiftZonesService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupervisionShiftZoneController extends Controller
{
    public function __construct(
        private readonly ManageShiftZonesService $zones,
    ) {}

    public function show(Request $request, SupervisorShift $shift): JsonResponse
    {
        $this->assertOwned($request, $shift);

        return response()->json($this->zones->state($shift));
    }

    public function update(Request $request, SupervisorShift $shift): JsonResponse
    {
        $this->assertOwned($request, $shift);
        abort_unless($request->user()?->can('company.settings.manage'), 403);

        $data = $request->validate([
            'zones' => ['present', 'array'],
            'zones.*' => ['integer'],
        ]);

        $this->zones->sync($shift, array_map('intval', $data['zones']), $request->user());

        return response()->json($this->zones->state($shift->fresh()) + ['message' => 'Zonas del turno actualizadas.']);
    }

    private function assertOwned(Request $request, SupervisorShift $shift): void
    {
        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        abort_unless((int) $shift->security_company_id === $companyId, 404);
    }
}
