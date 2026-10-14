<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Client;
use App\Models\Installation;
use App\Models\SupervisionReviewRadius;
use App\Models\SupervisionReviewSettings;
use App\Models\User;
use App\Services\Access\AuditLogger;
use Illuminate\Validation\ValidationException;

final class ManageReviewRadiusService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function updateSettings(User $actor, int $defaultRadius, int $maxRadius): SupervisionReviewSettings
    {
        if ($defaultRadius > $maxRadius) {
            throw ValidationException::withMessages([
                'default_radius_m' => 'El radio por defecto no puede superar el tope.',
            ]);
        }

        $settings = SupervisionReviewSettings::current();
        $old = $settings->only(['default_radius_m', 'max_radius_m']);
        $settings->update([
            'default_radius_m' => $defaultRadius,
            'max_radius_m' => $maxRadius,
            'updated_by' => $actor->id,
        ]);

        $this->auditLogger->action('platform.review_radius.settings', $settings, $old, $settings->only(['default_radius_m', 'max_radius_m']));

        return $settings;
    }

    /**
     * @param  array{security_company_id: int, client_id: ?int, installation_id: ?int, radius_m: int, reason: string}  $data
     */
    public function save(User $actor, array $data): SupervisionReviewRadius
    {
        $companyId = (int) $data['security_company_id'];
        $clientId = $data['client_id'] !== null ? (int) $data['client_id'] : null;
        $installationId = $data['installation_id'] !== null ? (int) $data['installation_id'] : null;

        if ($clientId !== null) {
            $belongs = Client::query()->withoutGlobalScopes()
                ->whereKey($clientId)
                ->where('security_company_id', $companyId)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages(['client_id' => 'El cliente no pertenece a esa empresa.']);
            }
        }

        if ($installationId !== null) {
            $belongs = $clientId !== null && Installation::query()->withoutGlobalScopes()
                ->whereKey($installationId)
                ->where('client_id', $clientId)
                ->exists();
            if (! $belongs) {
                throw ValidationException::withMessages(['installation_id' => 'La instalación no pertenece a ese cliente.']);
            }
        }

        $max = SupervisionReviewSettings::current()->max_radius_m;
        if ((int) $data['radius_m'] > $max) {
            throw ValidationException::withMessages(['radius_m' => "El radio no puede superar el tope de {$max} m."]);
        }

        $radius = SupervisionReviewRadius::query()
            ->where('security_company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('installation_id', $installationId)
            ->first() ?? new SupervisionReviewRadius([
                'security_company_id' => $companyId,
                'client_id' => $clientId,
                'installation_id' => $installationId,
            ]);

        $old = $radius->exists ? $radius->only(['radius_m', 'reason']) : null;
        $radius->fill([
            'radius_m' => (int) $data['radius_m'],
            'reason' => trim($data['reason']),
            'updated_by' => $actor->id,
        ])->save();

        $this->auditLogger->action('platform.review_radius.save', $radius, $old, $radius->only([
            'security_company_id', 'client_id', 'installation_id', 'radius_m', 'reason',
        ]));

        return $radius;
    }

    public function delete(SupervisionReviewRadius $radius): void
    {
        $old = $radius->only(['security_company_id', 'client_id', 'installation_id', 'radius_m', 'reason']);
        $radius->delete();

        $this->auditLogger->action('platform.review_radius.delete', null, $old, null);
    }
}
