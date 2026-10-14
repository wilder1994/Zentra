<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use App\Models\Installation;
use App\Models\SupervisorPost;
use App\Services\Access\GeoService;
use App\Services\Ops\RecordOperationalAlertService;
use Illuminate\Validation\ValidationException;

final class SupervisorReviewGeofence
{
    public const FAR_FROM_INSTALLATION_METERS = 200;

    /**
     * @return array{0: float, 1: float}
     */
    public function anchor(SupervisorPost $post): array
    {
        $post->loadMissing('installation');
        $installation = $post->installation;
        if (! $installation instanceof Installation || ! $installation->hasCoordinates()) {
            throw ValidationException::withMessages([
                'latitude' => 'Esta instalación no tiene ubicación. No se puede hacer la revista hasta que la empresa la fije en el mapa.',
            ]);
        }

        $lat = $post->latitude !== null ? (float) $post->latitude : (float) $installation->latitude;
        $lng = $post->longitude !== null ? (float) $post->longitude : (float) $installation->longitude;

        return [$lat, $lng];
    }

    public function radiusFor(SupervisorPost $post): int
    {
        $post->loadMissing('client');
        $companyId = (int) ($post->client?->security_company_id ?? 0);

        return app(ReviewRadiusResolver::class)->resolve(
            $companyId,
            $post->client_id !== null ? (int) $post->client_id : null,
            $post->installation_id !== null ? (int) $post->installation_id : null,
        )['meters'];
    }

    public function assertWithinRadius(SupervisorPost $post, float $lat, float $lng): void
    {
        [$anchorLat, $anchorLng] = $this->anchor($post);
        $radius = $this->radiusFor($post);
        $distance = app(GeoService::class)->distanceMeters($lat, $lng, $anchorLat, $anchorLng);
        if ($distance === null || $distance <= $radius) {
            return;
        }

        $meters = (int) round($distance);

        throw ValidationException::withMessages([
            'latitude' => 'Debes estar a menos de '.$radius.' m del puesto (ahora estás a '.$meters.' m). Acércate para registrar la revista.',
        ]);
    }

    public function annotateIfPostFarFromInstallation(SupervisorPost $post): void
    {
        if ($post->latitude === null || $post->longitude === null) {
            return;
        }

        $post->loadMissing(['installation', 'client']);
        $installation = $post->installation;
        if (! $installation instanceof Installation || ! $installation->hasCoordinates()) {
            return;
        }

        $distance = app(GeoService::class)->distanceMeters(
            (float) $post->latitude,
            (float) $post->longitude,
            (float) $installation->latitude,
            (float) $installation->longitude,
        );
        if ($distance === null || $distance <= self::FAR_FROM_INSTALLATION_METERS) {
            return;
        }

        $client = $post->client;
        if ($client === null) {
            return;
        }

        $meters = (int) round($distance);
        app(RecordOperationalAlertService::class)->serviceChange(
            $client,
            'El puesto «'.$post->name.'» quedó a '.$meters.' m de la instalación «'.$installation->name.'».',
            $installation,
            $post,
            auth()->user(),
            ['action' => 'post_far_from_installation', 'meters' => $meters],
        );
    }
}
