<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\InstallationKind;
use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Observatory\StorePublicObservatoryReportRequest;
use App\Models\Client;
use App\Models\Installation;
use App\Models\ObservatoryReport;
use App\Models\ObservatoryReportType;
use App\Services\Observatory\EnsureObservatoryReportTypesService;
use App\Services\Observatory\SubmitObservatoryReportService;
use App\Support\Observatory\ObservatoryPhotoInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ObservatoryIntakeController extends Controller
{
    public function __construct(
        private readonly SubmitObservatoryReportService $submit,
    ) {}

    public function show(string $slug): View
    {
        $row = $this->client($slug);
        app(EnsureObservatoryReportTypesService::class)->execute($row);

        return view('modules.observatory.public.intake', [
            'client' => $row,
            'kinds' => ObservatoryReportType::optionsFor((int) $row->id),
            'roles' => ObservatoryReporterRole::publicOptions(),
            'maps' => [
                'api_key' => config('google-maps.api_key'),
                'center' => config('google-maps.default_center'),
            ],
        ]);
    }

    public function installations(Request $request, string $slug): JsonResponse
    {
        $row = $this->client($slug);
        $search = $request->string('q')->trim()->toString();

        $installations = Installation::query()
            ->withoutGlobalScopes()
            ->where('client_id', $row->id)
            ->where('is_active', true)
            ->where('kind', InstallationKind::Colegio->value)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('dane_code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'dane_code', 'city', 'latitude', 'longitude']);

        return response()->json([
            'installations' => $installations->map(static fn (Installation $installation): array => [
                'id' => (int) $installation->id,
                'name' => $installation->name,
                'dane_code' => $installation->dane_code,
                'city' => $installation->city,
                'lat' => $installation->latitude !== null ? (float) $installation->latitude : null,
                'lng' => $installation->longitude !== null ? (float) $installation->longitude : null,
            ])->all(),
        ]);
    }

    public function store(StorePublicObservatoryReportRequest $request, string $slug): RedirectResponse
    {
        $row = $this->client($slug);

        try {
            $report = $this->submit->execute($row, [
                'installation_id' => (int) $request->validated('installation_id'),
                'kind' => (string) $request->validated('kind'),
                'body' => (string) $request->validated('body'),
                'is_anonymous' => $request->boolean('is_anonymous'),
                'source' => ObservatoryReportSource::Comunidad,
                'reporter_role' => (string) $request->validated('reporter_role'),
                'reporter_name' => $request->validated('reporter_name'),
                'reporter_phone' => $request->validated('reporter_phone'),
                'photos' => ObservatoryPhotoInput::files($request),
                'photo' => $request->file('photo'),
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
            ], $request->ip());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('observatory.public.thanks', [$row->slug, $report]);
    }

    public function thanks(string $slug, ObservatoryReport $report): View
    {
        $row = $this->client($slug);
        abort_unless((int) $report->client_id === (int) $row->id, 404);
        $report->load('event.installation');

        return view('modules.observatory.public.thanks', [
            'client' => $row,
            'report' => $report,
        ]);
    }

    private function client(string $slug): Client
    {
        $row = Client::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless((bool) $row->has_observatory, 404);

        return $row;
    }
}
