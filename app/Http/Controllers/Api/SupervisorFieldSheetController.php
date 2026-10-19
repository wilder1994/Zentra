<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisorFieldSheetKind;
use App\Http\Controllers\Controller;
use App\Services\Company\BuildSupervisorFieldSheetService;
use App\Services\Company\ListSupervisorFieldSheetsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SupervisorFieldSheetController extends Controller
{
    public function __construct(
        private readonly ListSupervisorFieldSheetsService $listSheets,
        private readonly BuildSupervisorFieldSheetService $buildSheet,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->security_company_id;
        abort_unless($companyId > 0, 403);

        $kind = $request->string('kind')->toString();
        $novelty = $request->string('novelty')->toString();

        $filter = new SupervisionQueryFilter(
            from: $request->string('from')->toString() ?: null,
            to: $request->string('to')->toString() ?: null,
            sheetKind: SupervisorFieldSheetKind::tryFrom($kind)?->value,
            clientId: $request->integer('client_id') > 0 ? $request->integer('client_id') : null,
            hasNovelty: $novelty === '1' ? true : ($novelty === '0' ? false : null),
        );

        $page = $this->listSheets->execute(
            $companyId,
            $filter,
            max(1, $request->integer('page', 1)),
            30,
            (int) $user->id,
        );

        return response()->json([
            'sheets' => collect($page->items())->map(fn ($item) => [
                'kind' => $item->kind->value,
                'id' => $item->id,
                'folio' => $item->folio,
                'type' => $item->typeLabel,
                'client' => $item->clientName,
                'novelty' => $item->hasNovelty,
                'recorded_at' => $item->recordedAt->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }

    public function show(Request $request, string $kind, int $id): View
    {
        $sheetKind = SupervisorFieldSheetKind::tryFrom($kind);
        abort_if($sheetKind === null, 404);

        $user = $request->user();
        $companyId = (int) $user->security_company_id;
        abort_unless($companyId > 0, 403);

        $sheet = $this->buildSheet->forSupervisor($companyId, (int) $user->id, $sheetKind, $id);
        abort_if($sheet === null, 404);

        return view('modules.company.supervision.ficha', ['sheet' => $sheet, 'mode' => 'app']);
    }
}
