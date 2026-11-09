<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreClientAccessPointRequest;
use App\Models\Client;
use App\Models\Location;
use App\Services\Company\ManageClientAccessPointService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ClientAccessPointController extends Controller
{
    public function __construct(
        private readonly ManageClientAccessPointService $accessPoints,
    ) {}

    public function store(StoreClientAccessPointRequest $request, Client $client): RedirectResponse
    {
        $this->assertCompany($request, $client);
        abort_unless($client->has_access, 403);

        $payload = [
            'installation_id' => (int) $request->validated('installation_id'),
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ];
        if ($request->filled('code')) {
            $payload['code'] = (string) $request->validated('code');
        }

        $this->accessPoints->create($client, $payload);

        return $this->backToClient($client, 'Puerta creada.');
    }

    public function update(StoreClientAccessPointRequest $request, Client $client, Location $location): RedirectResponse
    {
        $this->assertPoint($request, $client, $location);

        $payload = [
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
        ];
        if ($request->filled('installation_id')) {
            $payload['installation_id'] = (int) $request->validated('installation_id');
        }

        $this->accessPoints->update($location, $payload);

        return $this->backToClient($client, 'Puerta actualizada.');
    }

    public function archive(Request $request, Client $client, Location $location): RedirectResponse
    {
        $this->assertPoint($request, $client, $location);
        $this->authorize('update', $client);
        $this->accessPoints->archive($location);

        return $this->backToClient($client, 'Puerta archivada.');
    }

    public function restore(Request $request, Client $client, Location $location): RedirectResponse
    {
        $this->assertPoint($request, $client, $location);
        $this->authorize('update', $client);
        $this->accessPoints->restore($location);

        return $this->backToClient($client, 'Puerta restaurada.');
    }

    public function destroy(Request $request, Client $client, Location $location): RedirectResponse
    {
        $this->assertPoint($request, $client, $location);
        $this->authorize('update', $client);

        $this->accessPoints->delete($location);

        return $this->backToClient($client, 'Puerta eliminada.');
    }

    private function assertPoint(Request $request, Client $client, Location $location): void
    {
        $this->assertCompany($request, $client);
        abort_unless($client->has_access, 403);
        abort_unless((int) $location->client_id === (int) $client->id, 404);
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

    private function backToClient(Client $client, string $message): RedirectResponse
    {
        return redirect()
            ->route('company.clients.show', [$client, 'vista' => 'sitio'])
            ->with('success', $message);
    }
}
