<?php

declare(strict_types=1);

namespace App\Support\Supervision;

use App\Models\Client;
use App\Models\SupervisionReviewRadius;
use App\Models\SupervisionReviewSettings;

final class ReviewRadiusResolver
{
    /**
     * Instalación > cliente > empresa > global; siempre acotado al tope.
     *
     * @return array{meters: int, source: string, exception: ?SupervisionReviewRadius}
     */
    public function resolve(int $companyId, ?int $clientId = null, ?int $installationId = null): array
    {
        $settings = SupervisionReviewSettings::current();

        $exception = SupervisionReviewRadius::query()
            ->where('security_company_id', $companyId)
            ->where(function ($q) use ($clientId, $installationId) {
                $q->where(fn ($w) => $w->whereNull('client_id')->whereNull('installation_id'));
                if ($clientId !== null) {
                    $q->orWhere(fn ($w) => $w->where('client_id', $clientId)->whereNull('installation_id'));
                }
                if ($installationId !== null) {
                    $q->orWhere('installation_id', $installationId);
                }
            })
            ->get()
            ->sortByDesc(fn (SupervisionReviewRadius $r) => match ($r->level()) {
                'installation' => 3,
                'client' => 2,
                default => 1,
            })
            ->first();

        if ($exception === null) {
            return [
                'meters' => min($settings->default_radius_m, $settings->max_radius_m),
                'source' => 'global',
                'exception' => null,
            ];
        }

        return [
            'meters' => min($exception->radius_m, $settings->max_radius_m),
            'source' => $exception->level(),
            'exception' => $exception,
        ];
    }

    public function forClient(Client $client, ?int $installationId = null): int
    {
        return $this->resolve((int) $client->security_company_id, (int) $client->id, $installationId)['meters'];
    }
}
