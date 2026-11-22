<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Client;
use App\Models\SecurityCompany;

final class AssertInstallationSeats
{
    public const ACTION_CREATE_CLIENT = 'create_client';

    public const ACTION_CREATE_INSTALLATION = 'create_installation';

    public const ACTION_REACTIVATE = 'reactivate';

    private const ACTIONS = [
        self::ACTION_CREATE_CLIENT => 'No puedes crear este cliente:',
        self::ACTION_CREATE_INSTALLATION => 'No puedes crear esta instalación:',
        self::ACTION_REACTIVATE => 'No puedes restaurar esta instalación:',
    ];

    /**
     * Alta y restauración de instalaciones: solo la bolsa (el plan más alto).
     * Chulear una línea se valida aparte, con el cupo de esa línea.
     */
    public function execute(SecurityCompany $company, string $action, ?int $exceptInstallationId = null): void
    {
        $company->installationQuota()->assertRoom(
            [],
            1,
            self::ACTIONS[$action] ?? self::ACTIONS[self::ACTION_CREATE_INSTALLATION],
            $exceptInstallationId,
        );
    }

    public function forClient(Client $client, string $action, ?int $exceptInstallationId = null): void
    {
        $company = SecurityCompany::query()->findOrFail($client->security_company_id);

        $this->execute($company, $action, $exceptInstallationId);
    }
}
