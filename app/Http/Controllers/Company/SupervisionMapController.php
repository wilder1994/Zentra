<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisorFieldSheetKind;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SecurityCompany;
use App\Models\SupervisorShift;
use App\Models\SupervisorZone;
use App\Models\User;
use App\Services\Company\BuildSupervisionMapService;
use App\Services\Company\BuildSupervisionSummaryService;
use App\Services\Company\EnsureSnappedSupervisorRouteService;
use App\Services\Company\ExportSupervisionExecutiveReportService;
use App\Services\Company\ListSupervisorFieldSheetsService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SupervisionMapController extends Controller
{
    public function __construct(
        private readonly BuildSupervisionMapService $buildSupervisionMapService,
        private readonly BuildSupervisionSummaryService $buildSupervisionSummaryService,
        private readonly ExportSupervisionExecutiveReportService $exportReport,
        private readonly ListSupervisorFieldSheetsService $listSheets,
        private readonly EnsureSnappedSupervisorRouteService $ensureSnappedRoute,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('company.supervision.view'), 403);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $company = SecurityCompany::query()->findOrFail($companyId);
        $filter = $this->queryFilter($request, $company);

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['live', 'history', 'summary', 'sheets'], true)) {
            $tab = 'live';
        }

        $map = $tab === 'sheets'
            ? [
                'live' => [],
                'history' => [],
                'reviews' => [],
                'clients' => [],
                'from' => $filter->from,
                'to' => $filter->to,
                'google_maps' => [
                    'api_key' => null,
                    'center' => null,
                    'zoom' => 6,
                ],
            ]
            : $this->buildSupervisionMapService->execute($company, $filter, $tab);

        $summary = $this->buildSupervisionSummaryService->execute(
            $company,
            $filter->withDates(
                $filter->from ?? ($map['from'] ?? null),
                $filter->to ?? ($map['to'] ?? null),
            ),
        );

        $sheets = $tab === 'sheets'
            ? $this->listSheets->execute(
                $companyId,
                $filter->withDates($summary->from, $summary->to),
                max(1, $request->integer('page', 1)),
            )
            : null;

        return view('modules.company.supervision.index', [
            'company' => $company,
            'map' => $map,
            'summary' => $summary,
            'tab' => $tab,
            'filter' => $filter,
            'sheets' => $sheets,
            'clients' => Client::query()
                ->where('security_company_id', $companyId)
                ->where('has_supervision', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'zones' => SupervisorZone::query()
                ->where('security_company_id', $companyId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'supervisors' => User::query()
                ->where('security_company_id', $companyId)
                ->role('supervisor')
                ->orderBy('name')
                ->get(['id', 'name']),
            'canManageZones' => (bool) $request->user()?->can('company.settings.manage'),
        ]);
    }

    public function report(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('company.supervision.view'), 403);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $company = SecurityCompany::query()->findOrFail($companyId);
        $filter = $this->queryFilter($request, $company);

        $snapshot = $this->buildSupervisionSummaryService->execute($company, $filter);
        $file = $this->exportReport->execute($snapshot, $company->logo_path);

        return response()
            ->download($file['path'], $file['filename'])
            ->deleteFileAfterSend();
    }

    public function liveFeed(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('company.supervision.view'), 403);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        $company = SecurityCompany::query()->findOrFail($companyId);

        return response()->json($this->buildSupervisionMapService->liveFeed($company));
    }

    public function snappedRoute(Request $request, SupervisorShift $shift): JsonResponse
    {
        abort_unless($request->user()?->can('company.supervision.view'), 403);

        $companyId = app(ActingCompanyResolver::class)->requireId($request->user());
        abort_unless((int) $shift->security_company_id === $companyId, 404);

        return response()->json($this->ensureSnappedRoute->execute($shift));
    }

    private function queryFilter(Request $request, SecurityCompany $company): SupervisionQueryFilter
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $zoneId = $request->integer('zone_id');
        $supervisorId = $request->integer('supervisor_id');
        $clientId = $request->integer('client_id');
        $sheetKind = $request->string('kind')->toString();
        $novelty = $request->string('novelty')->toString();

        if ($zoneId > 0) {
            $owned = SupervisorZone::query()
                ->where('security_company_id', $company->id)
                ->whereKey($zoneId)
                ->exists();
            $zoneId = $owned ? $zoneId : 0;
        }

        if ($supervisorId > 0) {
            $owned = User::query()
                ->where('security_company_id', $company->id)
                ->role('supervisor')
                ->whereKey($supervisorId)
                ->exists();
            $supervisorId = $owned ? $supervisorId : 0;
        }

        if ($clientId > 0) {
            $owned = Client::query()
                ->where('security_company_id', $company->id)
                ->whereKey($clientId)
                ->exists();
            $clientId = $owned ? $clientId : 0;
        }

        $kind = SupervisorFieldSheetKind::tryFrom($sheetKind);

        return new SupervisionQueryFilter(
            from: $from !== '' ? $from : null,
            to: $to !== '' ? $to : null,
            zoneId: $zoneId > 0 ? $zoneId : null,
            supervisorId: $supervisorId > 0 ? $supervisorId : null,
            sheetKind: $kind?->value,
            clientId: $clientId > 0 ? $clientId : null,
            hasNovelty: $novelty === '1' ? true : ($novelty === '0' ? false : null),
        );
    }
}
