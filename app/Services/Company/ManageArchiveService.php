<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\ClientLifecycle;
use App\Models\Client;
use App\Models\Installation;
use App\Models\SecurityCompany;
use App\Models\SupervisorPost;
use App\Services\Platform\ReleaseClientService;
use App\Services\Tenant\AssertClientServiceSeats;
use App\Services\Tenant\AssertInstallationSeats;
use App\Support\Catalog\InstallationQuota;
use App\Support\Catalog\PostQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Activo → Archivado → Dado de baja, igual para clientes, instalaciones y puestos.
 * Archivar libera cupo y conserva los datos; los hijos se archivan en cascada y vuelven con su padre.
 */
final class ManageArchiveService
{
    public function archiveClient(Client $client): void
    {
        if (! $client->is_active) {
            return;
        }

        DB::transaction(function () use ($client): void {
            $now = now();
            $client->update(['is_active' => false, 'archived_at' => $now, 'archived_with_parent' => false]);

            $installationIds = $client->installations()->where('is_active', true)->pluck('id');
            Installation::query()->withoutGlobalScopes()->whereIn('id', $installationIds)
                ->update(['is_active' => false, 'archived_at' => $now, 'archived_with_parent' => true]);

            SupervisorPost::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'archived_at' => $now, 'archived_with_parent' => true]);
        });
    }

    public function restoreClient(Client $client): void
    {
        if ($client->is_active) {
            return;
        }

        if ($client->lifecycle !== ClientLifecycle::Active) {
            throw ValidationException::withMessages([
                'client' => 'Este cliente está dado de baja o su empresa está archivada: no se puede restaurar.',
            ]);
        }

        $company = SecurityCompany::query()->findOrFail($client->security_company_id);
        $returning = $client->installations()->where('archived_with_parent', true)->count();
        $count = max(1, $returning);

        $company->installationQuota()->assertRoom(
            InstallationQuota::linesOf($client),
            $count,
            'No puedes restaurar «'.$client->name.'» ('.$count.' '.($count === 1 ? 'instalación' : 'instalaciones').'):',
            exceptClientId: $client->id,
        );

        DB::transaction(function () use ($client): void {
            $client->update(['is_active' => true, 'archived_at' => null, 'archived_with_parent' => false]);

            $installationIds = $client->installations()->where('archived_with_parent', true)->pluck('id');
            Installation::query()->withoutGlobalScopes()->whereIn('id', $installationIds)
                ->update(['is_active' => true, 'archived_at' => null, 'archived_with_parent' => false]);

            SupervisorPost::query()->withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('archived_with_parent', true)
                ->whereIn('installation_id', $client->installations()->where('is_active', true)->pluck('id'))
                ->update(['is_active' => true, 'archived_at' => null, 'archived_with_parent' => false]);
        });
    }

    /** Baja definitiva: retiro del cliente con acta y conservación legal de datos. */
    public function releaseClient(Client $client): void
    {
        if ($client->is_active) {
            throw ValidationException::withMessages([
                'client' => 'Archiva el cliente antes de darlo de baja.',
            ]);
        }

        if ($client->lifecycle !== ClientLifecycle::Active) {
            return;
        }

        app(ReleaseClientService::class)->execute($client);
    }

    public function archiveInstallation(Installation $installation): void
    {
        if (! $installation->is_active) {
            return;
        }

        DB::transaction(function () use ($installation): void {
            $now = now();
            $installation->update(['is_active' => false, 'archived_at' => $now, 'archived_with_parent' => false]);

            SupervisorPost::query()->withoutGlobalScopes()
                ->where('installation_id', $installation->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'archived_at' => $now, 'archived_with_parent' => true]);
        });
    }

    public function restoreInstallation(Installation $installation): void
    {
        if ($installation->is_active) {
            return;
        }

        $client = Client::query()->findOrFail($installation->client_id);
        if (! $client->is_active) {
            throw ValidationException::withMessages([
                'installation' => 'El cliente «'.$client->name.'» está archivado. Restaura primero el cliente.',
            ]);
        }

        app(AssertInstallationSeats::class)->forClient($client, AssertInstallationSeats::ACTION_REACTIVATE, (int) $installation->id);

        DB::transaction(function () use ($installation): void {
            $installation->update(['is_active' => true, 'archived_at' => null, 'archived_with_parent' => false]);

            SupervisorPost::query()->withoutGlobalScopes()
                ->where('installation_id', $installation->id)
                ->where('archived_with_parent', true)
                ->update(['is_active' => true, 'archived_at' => null, 'archived_with_parent' => false]);
        });
    }

    public function removeInstallation(Installation $installation): void
    {
        if ($installation->is_active) {
            throw ValidationException::withMessages([
                'installation' => 'Archiva la instalación antes de darla de baja.',
            ]);
        }

        if ($installation->locations()->exists()) {
            throw ValidationException::withMessages([
                'installation' => 'No se puede dar de baja: tiene puntos de acceso. Dalos de baja o muévelos antes.',
            ]);
        }

        if ($installation->supervisorPosts()->exists()) {
            throw ValidationException::withMessages([
                'installation' => 'No se puede dar de baja: tiene puestos. Dalos de baja antes.',
            ]);
        }

        $installation->delete();
    }

    public function archivePost(SupervisorPost $post): void
    {
        if (! $post->is_active) {
            return;
        }

        $post->update(['is_active' => false, 'archived_at' => now(), 'archived_with_parent' => false]);
    }

    public function restorePost(SupervisorPost $post): void
    {
        if ($post->is_active) {
            return;
        }

        $installation = Installation::query()->withoutGlobalScopes()->findOrFail($post->installation_id);
        PostQuota::assertPostRoom($installation, 'No puedes restaurar este puesto:', (int) $post->id);
        PostQuota::assertEmployeesFit($post, PostQuota::assignedEmployees($post), 'No puedes restaurar este puesto:');

        $post->update(['is_active' => true, 'archived_at' => null, 'archived_with_parent' => false]);
    }

    /** @param array{access?: bool, supervision?: bool, observatory?: bool} $lines */
    public function assertLines(Client $client, array $lines): void
    {
        if (! $client->is_active) {
            return;
        }

        $company = SecurityCompany::query()->findOrFail($client->security_company_id);
        app(AssertClientServiceSeats::class)->execute($company, $lines, $client);
    }
}
