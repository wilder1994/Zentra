<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\SupervisorFieldSheetKind;
use App\Http\Controllers\Controller;
use App\Services\Company\BuildSupervisorFieldSheetService;
use Illuminate\View\View;

/** Ficha de campo desde el enlace firmado del correo de novedades (sin sesión). */
final class SignedSupervisionSheetController extends Controller
{
    public function __construct(
        private readonly BuildSupervisorFieldSheetService $sheets,
    ) {}

    public function __invoke(int $company, string $kind, int $id): View
    {
        $sheetKind = SupervisorFieldSheetKind::tryFrom($kind);
        abort_if($sheetKind === null || $sheetKind === SupervisorFieldSheetKind::Shift, 404);

        $sheet = $this->sheets->forCompany($company, $sheetKind, $id);
        abort_if($sheet === null, 404);

        return view('modules.company.supervision.ficha', ['sheet' => $sheet]);
    }
}
