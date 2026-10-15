<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Enums\InstallationGeoRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\InstallationGeoRequest;
use App\Services\Supervision\InstallationGeoRequestService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class InstallationGeoRequestController extends Controller
{
    public function __construct(
        private readonly InstallationGeoRequestService $service,
        private readonly ActingCompanyResolver $actingCompany,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyId($request);
        $tab = $request->query('tab') === 'history' ? 'history' : 'pending';

        $rows = InstallationGeoRequest::query()
            ->where('security_company_id', $companyId)
            ->when(
                $tab === 'pending',
                fn ($q) => $q->pending()->oldest('id'),
                fn ($q) => $q->where('status', '!=', InstallationGeoRequestStatus::Pending->value)->latest('id'),
            )
            ->with(['client:id,name', 'installation:id,name', 'requester:id,name', 'reviewer:id,name'])
            ->paginate(25)
            ->withQueryString();

        return view('modules.company.supervision.geo', [
            'tab' => $tab,
            'rows' => $rows,
            'pendingCount' => InstallationGeoRequest::query()->where('security_company_id', $companyId)->pending()->count(),
            'mapsKey' => (string) config('google-maps.api_key', ''),
            'openId' => $request->integer('open') ?: null,
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $companyId = $this->service->canApprove($request->user()) ? $this->actingCompany->id($request->user()) : null;
        if ($companyId === null) {
            return response()->json(['requests' => []]);
        }

        $rows = InstallationGeoRequest::query()
            ->where('security_company_id', $companyId)
            ->pending()
            ->with(['client:id,name', 'installation:id,name', 'requester:id,name'])
            ->oldest('id')
            ->limit(10)
            ->get()
            ->map(fn (InstallationGeoRequest $row) => [
                'id' => $row->id,
                'client' => $row->client?->name ?? '—',
                'installation' => $row->installation?->name ?? '—',
                'supervisor' => $row->requester?->name ?? '—',
                'distance_m' => $row->distance_m,
                'requested_at' => $row->created_at?->format('H:i'),
                'review_url' => route('company.supervision.geo.index', ['open' => $row->id]),
            ])
            ->values();

        return response()->json(['requests' => $rows]);
    }

    public function approve(Request $request, InstallationGeoRequest $geoRequest): JsonResponse|RedirectResponse
    {
        return $this->resolve($request, $geoRequest, true);
    }

    public function reject(Request $request, InstallationGeoRequest $geoRequest): JsonResponse|RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        return $this->resolve($request, $geoRequest, false);
    }

    private function resolve(Request $request, InstallationGeoRequest $geoRequest, bool $approve): JsonResponse|RedirectResponse
    {
        abort_unless((int) $geoRequest->security_company_id === $this->companyId($request), 404);

        try {
            $approve
                ? $this->service->approve($geoRequest, $request->user())
                : $this->service->reject($geoRequest, $request->user(), $request->input('note'));
        } catch (ConflictHttpException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = $approve
            ? 'Ubicación aprobada. La instalación ya usa el nuevo punto para las revistas.'
            : 'Solicitud rechazada. La instalación conserva su ubicación.';

        if ($request->expectsJson()) {
            $request->session()->flash('success', $message);

            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('company.supervision.geo.index')->with('success', $message);
    }

    private function companyId(Request $request): int
    {
        abort_unless($this->service->canApprove($request->user()), 403);

        return $this->actingCompany->requireId($request->user());
    }
}
