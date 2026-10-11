<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Enums\DeviceChangeStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceChangeRequest;
use App\Services\Auth\BindSupervisorDevice;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeviceChangeController extends Controller
{
    public function __construct(
        private readonly BindSupervisorDevice $binding,
        private readonly ActingCompanyResolver $actingCompany,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $this->companyId($request);
        $this->binding->expireStale(companyId: $companyId);
        $status = DeviceChangeStatus::tryFrom((string) $request->query('status', ''));

        $rows = DeviceChangeRequest::query()
            ->where('security_company_id', $companyId)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->with(['user', 'resolver'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('modules.company.devices.index', [
            'requests' => $rows,
            'status' => $status?->value ?? '',
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        if (! $this->binding->canApprove($request->user())) {
            return response()->json(['requests' => []]);
        }

        $companyId = $this->actingCompany->id($request->user());
        if ($companyId === null) {
            return response()->json(['requests' => []]);
        }

        $rows = DeviceChangeRequest::query()
            ->where('security_company_id', $companyId)
            ->open()
            ->with('user')
            ->oldest('id')
            ->get()
            ->map(fn (DeviceChangeRequest $row) => [
                'id' => $row->id,
                'supervisor' => $row->user?->name ?? '—',
                'username' => $row->user?->username,
                'device' => $row->device_label ?: 'Celular nuevo',
                'previous_device' => $row->previous_device_label ?: 'Celular anterior',
                'requested_at' => $row->created_at?->format('H:i'),
                'expires_in' => max(0, now()->diffInSeconds($row->expires_at, false)),
                'approve_url' => route('company.devices.approve', $row),
                'reject_url' => route('company.devices.reject', $row),
            ])
            ->values();

        return response()->json(['requests' => $rows]);
    }

    public function approve(Request $request, DeviceChangeRequest $deviceRequest): JsonResponse|RedirectResponse
    {
        return $this->resolve($request, $deviceRequest, true);
    }

    public function reject(Request $request, DeviceChangeRequest $deviceRequest): JsonResponse|RedirectResponse
    {
        return $this->resolve($request, $deviceRequest, false);
    }

    private function resolve(Request $request, DeviceChangeRequest $deviceRequest, bool $approve): JsonResponse|RedirectResponse
    {
        abort_unless((int) $deviceRequest->security_company_id === $this->companyId($request), 404);

        try {
            $approve
                ? $this->binding->approve($deviceRequest, $request->user())
                : $this->binding->reject($deviceRequest, $request->user());
        } catch (ConflictHttpException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = $approve
            ? 'Cambio aprobado. El supervisor ya puede entrar desde el celular nuevo.'
            : 'Cambio rechazado.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    private function companyId(Request $request): int
    {
        abort_unless($this->binding->canApprove($request->user()), 403);

        return $this->actingCompany->requireId($request->user());
    }
}
