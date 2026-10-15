<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Supervision\InstallationGeoRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupervisorGeoController extends Controller
{
    public function __construct(private readonly InstallationGeoRequestService $service) {}

    public function installations(Request $request): JsonResponse
    {
        $request->validate(['client_id' => ['required', 'integer']]);

        return response()->json([
            'installations' => $this->service->installationsFor($request->user(), $request->integer('client_id')),
            'max_accuracy_m' => InstallationGeoRequestService::MAX_ACCURACY_METERS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'installation_id' => ['required', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [
            'latitude.required' => 'Active la ubicación del dispositivo.',
            'longitude.required' => 'Active la ubicación del dispositivo.',
            'accuracy.required' => 'El GPS no informó la precisión. Intenta de nuevo.',
        ]);

        $geoRequest = $this->service->submit(
            $request->user(),
            (int) $data['client_id'],
            (int) $data['installation_id'],
            (float) $data['latitude'],
            (float) $data['longitude'],
            (int) ceil((float) $data['accuracy']),
        );

        return response()->json([
            'id' => $geoRequest->id,
            'message' => 'Solicitud enviada. La empresa debe aprobarla para cambiar la ubicación.',
        ], 201);
    }
}
