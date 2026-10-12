<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InstallationKind;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSupervisorObservatoryReportRequest;
use App\Models\Installation;
use App\Services\Company\ManageSupervisorShiftService;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\SubmitObservatoryReportService;
use App\Support\Supervision\SupervisorCaptureTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SupervisorObservatoryController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorShiftService $shifts,
        private readonly SubmitObservatoryReportService $submit,
    ) {}

    public function sites(Request $request): JsonResponse
    {
        $search = $request->string('q')->trim()->toString();
        $companyId = (int) $request->user()->security_company_id;

        $sites = Installation::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where('kind', InstallationKind::Colegio->value)
            ->whereHas('client', function ($q) use ($companyId): void {
                $q->where('security_company_id', $companyId)
                    ->where('is_active', true);
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('dane_code', 'like', '%'.$search.'%');
                });
            })
            ->with('client:id,name')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'client_id', 'name', 'dane_code', 'city', 'latitude', 'longitude']);

        $kindsByClient = app(EnsureObservatoryReportTypesService::class)
            ->optionsByClient($sites->pluck('client_id'));
        $kinds = [];
        foreach ($kindsByClient as $map) {
            $kinds = $kinds + $map;
        }

        return response()->json([
            'sites' => $sites->map(static fn (Installation $site): array => [
                'id' => (int) $site->id,
                'client_id' => (int) $site->client_id,
                'name' => $site->name,
                'client' => $site->client?->name,
                'dane_code' => $site->dane_code,
                'city' => $site->city,
                'lat' => $site->latitude !== null ? (float) $site->latitude : null,
                'lng' => $site->longitude !== null ? (float) $site->longitude : null,
            ])->all(),
            'kinds' => $kinds,
            'kinds_by_client' => $kindsByClient,
            'google_maps' => [
                'api_key' => (string) config('google-maps.api_key', ''),
                'center' => config('google-maps.default_center'),
            ],
        ]);
    }

    public function store(StoreSupervisorObservatoryReportRequest $request): JsonResponse
    {
        $at = SupervisorCaptureTime::parse($request->input('recorded_at'));
        abort_if($this->shifts->shiftForCapture($request->user(), $at) === null, 422, 'No hay turno abierto.');

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->with('client')
            ->whereKey((int) $request->validated('installation_id'))
            ->first();

        if ($installation === null || (int) $installation->client?->security_company_id !== (int) $request->user()->security_company_id) {
            throw ValidationException::withMessages([
                'installation_id' => 'Elige un colegio de tu empresa.',
            ]);
        }

        $anonymous = $request->boolean('is_anonymous');
        $user = $request->user();

        $report = $this->submit->execute($installation->client, [
            'installation_id' => (int) $installation->id,
            'kind' => (string) $request->validated('kind'),
            'body' => (string) $request->validated('body'),
            'is_anonymous' => $anonymous,
            'source' => ObservatoryReportSource::Campo,
            'reporter_role' => ObservatoryReporterRole::Supervisor,
            'reporter_name' => $anonymous ? null : $user->name,
            'reported_by' => $user,
            'photos' => $this->photoFiles($request),
            'photo' => $request->file('photo'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
        ], $request->ip());

        $report->load('event');

        return response()->json([
            'report' => [
                'id' => $report->id,
                'event_id' => $report->event_id,
                'folio' => $report->event?->folio(),
            ],
        ], 201);
    }

    /** @return list<\Illuminate\Http\UploadedFile> */
    private function photoFiles(StoreSupervisorObservatoryReportRequest $request): array
    {
        $files = $request->file('photos', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        return array_values(array_filter($files, fn ($file) => $file instanceof \Illuminate\Http\UploadedFile));
    }
}
