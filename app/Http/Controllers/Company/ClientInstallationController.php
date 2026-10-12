<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Domain\Geo\GeoAddressData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreClientInstallationRequest;
use App\Models\Client;
use App\Models\Installation;
use App\Services\Company\ManageArchiveService;
use App\Services\Company\ManageClientInstallationService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ClientInstallationController extends Controller
{
    public function __construct(
        private readonly ManageClientInstallationService $installations,
        private readonly ManageArchiveService $archives,
    ) {}

    public function store(StoreClientInstallationRequest $request, Client $client): RedirectResponse
    {
        $this->assertCompany($request, $client);

        try {
            $this->installations->create($client, [
                'name' => $request->validated('name'),
                'code' => $request->validated('code'),
                'kind' => $request->validated('kind'),
                'dane_code' => $request->validated('dane_code'),
                'commune' => $request->validated('commune'),
                'rector_user_id' => $request->validated('rector_user_id'),
                'is_client_site' => $request->boolean('is_client_site'),
                'is_active' => $request->boolean('is_active', true),
                'geo' => $request->boolean('is_client_site')
                    ? null
                    : GeoAddressData::fromValidated($request->validated()),
            ]);
        } catch (ValidationException $e) {
            return $this->backToClient(
                $client,
                $request,
                $e->validator->errors()->first() ?: 'No se pudo crear.',
                error: true,
            );
        }

        return $this->backToClient($client, $request, 'Instalación creada.');
    }

    public function update(StoreClientInstallationRequest $request, Client $client, Installation $installation): RedirectResponse
    {
        $this->assertTree($request, $client, $installation);

        try {
            $this->installations->update($installation, [
                'name' => $request->validated('name'),
                'code' => $request->validated('code'),
                'kind' => $request->validated('kind'),
                'dane_code' => $request->validated('dane_code'),
                'commune' => $request->validated('commune'),
                'rector_user_id' => $request->validated('rector_user_id'),
                'is_client_site' => $request->boolean('is_client_site'),
                'is_active' => $request->boolean('is_active'),
                'geo' => $request->boolean('is_client_site')
                    ? null
                    : GeoAddressData::fromValidated($request->validated()),
            ]);
        } catch (ValidationException $e) {
            return $this->backToClient(
                $client,
                $request,
                $e->validator->errors()->first() ?: 'No se pudo actualizar.',
                error: true,
            );
        }

        return $this->backToClient($client, $request, 'Instalación actualizada.');
    }

    public function archive(Request $request, Client $client, Installation $installation): RedirectResponse
    {
        return $this->lifecycle($request, $client, $installation, fn () => $this->archives->archiveInstallation($installation), 'Instalación archivada: liberó un cupo. Sus puestos quedaron archivados con ella.');
    }

    public function restore(Request $request, Client $client, Installation $installation): RedirectResponse
    {
        return $this->lifecycle($request, $client, $installation, fn () => $this->archives->restoreInstallation($installation), 'Instalación restaurada con sus puestos.');
    }

    public function destroy(Request $request, Client $client, Installation $installation): RedirectResponse
    {
        return $this->lifecycle($request, $client, $installation, fn () => $this->archives->removeInstallation($installation), 'Instalación dada de baja.');
    }

    private function lifecycle(Request $request, Client $client, Installation $installation, \Closure $action, string $message): RedirectResponse
    {
        $this->assertTree($request, $client, $installation);
        $this->authorize('update', $client);

        try {
            $action();
        } catch (ValidationException $e) {
            return $this->backToClient(
                $client,
                $request,
                $e->validator->errors()->first() ?: 'No se pudo completar.',
                error: true,
            );
        }

        return $this->backToClient($client, $request, $message);
    }

    private function assertTree(Request $request, Client $client, Installation $installation): void
    {
        $this->assertCompany($request, $client);
        abort_unless((int) $installation->client_id === (int) $client->id, 404);
    }

    private function assertCompany(Request $request, Client $client): void
    {
        if ($request->user()?->hasRole('super-admin')) {
            return;
        }

        abort_unless(
            (int) $request->user()?->security_company_id === (int) $client->security_company_id
            || app(ActingCompanyResolver::class)->id($request->user()) === (int) $client->security_company_id,
            403
        );
    }

    private function backToClient(Client $client, Request $request, string $message, bool $error = false): RedirectResponse
    {
        $vista = $client->isCatalogOnly() ? 'cliente' : 'sitio';

        $installation = $request->route('installation');
        if ($request->input('return_to') === 'installation' && $installation instanceof Installation && ! $installation->trashed()) {
            return redirect()
                ->route('company.installations.show', $installation)
                ->with($error ? 'error' : 'success', $message);
        }

        return redirect()
            ->route('company.clients.show', [$client, 'vista' => $vista])
            ->with($error ? 'error' : 'success', $message);
    }
}
