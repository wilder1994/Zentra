<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreClientSupervisorPostRequest;
use App\Models\Client;
use App\Models\SupervisorPost;
use App\Services\Company\ManageSupervisorPostService;
use App\Support\Platform\ActingCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ClientSupervisorPostController extends Controller
{
    public function __construct(
        private readonly ManageSupervisorPostService $posts,
    ) {}

    public function store(StoreClientSupervisorPostRequest $request, Client $client): RedirectResponse
    {
        $this->assertCompany($request, $client);

        try {
            $this->posts->create($client, [
                'installation_id' => (int) $request->validated('installation_id'),
                'name' => $request->validated('name'),
                'modality' => (int) $request->validated('modality'),
                ...$this->schedule($request),
                'is_active' => $request->boolean('is_active', true),
                'employee_ids' => $request->validated('employee_ids') ?? [],
                'observations' => $request->validated('observations'),
                'latitude' => $this->optionalCoord($request, 'latitude'),
                'longitude' => $this->optionalCoord($request, 'longitude'),
            ]);
        } catch (ValidationException $e) {
            return $this->backToClient(
                $client,
                $request,
                $e->validator->errors()->first() ?: 'No se pudo crear.',
                error: true,
            );
        }

        return $this->backToClient($client, $request, 'Puesto creado.');
    }

    public function update(StoreClientSupervisorPostRequest $request, Client $client, SupervisorPost $post): RedirectResponse
    {
        $this->assertPost($request, $client, $post);

        try {
            $this->posts->update($post, [
                'installation_id' => (int) $request->validated('installation_id'),
                'name' => $request->validated('name'),
                'modality' => (int) $request->validated('modality'),
                ...$this->schedule($request),
                'is_active' => $request->boolean('is_active'),
                'employee_ids' => $request->validated('employee_ids') ?? [],
                'observations' => $request->validated('observations'),
                'latitude' => $this->optionalCoord($request, 'latitude'),
                'longitude' => $this->optionalCoord($request, 'longitude'),
            ]);
        } catch (ValidationException $e) {
            return $this->backToClient(
                $client,
                $request,
                $e->validator->errors()->first() ?: 'No se pudo actualizar.',
                error: true,
            );
        }

        return $this->backToClient($client, $request, 'Puesto actualizado.');
    }

    public function archive(Request $request, Client $client, SupervisorPost $post): RedirectResponse
    {
        return $this->lifecycle($request, $client, $post, fn () => $this->posts->archive($post, $request->input('observations')), 'Puesto archivado: liberó un cupo de la instalación.');
    }

    public function restore(Request $request, Client $client, SupervisorPost $post): RedirectResponse
    {
        return $this->lifecycle($request, $client, $post, fn () => $this->posts->restore($post, $request->input('observations')), 'Puesto restaurado.');
    }

    public function destroy(Request $request, Client $client, SupervisorPost $post): RedirectResponse
    {
        return $this->lifecycle($request, $client, $post, fn () => $this->posts->delete($post, $request->input('observations')), 'Puesto dado de baja.');
    }

    private function lifecycle(Request $request, Client $client, SupervisorPost $post, \Closure $action, string $message): RedirectResponse
    {
        $this->assertPost($request, $client, $post);
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

    /** @return array{service_days?: int, serves_holidays?: bool, reviews_per_day?: int} */
    private function schedule(StoreClientSupervisorPostRequest $request): array
    {
        $out = [];
        if ($request->has('service_days')) {
            $out['service_days'] = SupervisorPost::daysMask((array) $request->validated('service_days'));
        }
        if ($request->has('serves_holidays')) {
            $out['serves_holidays'] = $request->boolean('serves_holidays');
        }
        if ($request->has('reviews_per_day')) {
            $out['reviews_per_day'] = (int) $request->validated('reviews_per_day');
        }

        return $out;
    }

    private function optionalCoord(StoreClientSupervisorPostRequest $request, string $key): ?float
    {
        $value = $request->validated($key);

        return $value === null || $value === '' ? null : (float) $value;
    }

    private function assertPost(Request $request, Client $client, SupervisorPost $post): void
    {
        $this->assertCompany($request, $client);
        abort_unless($client->has_access || $client->has_supervision, 403);
        abort_unless((int) $post->client_id === (int) $client->id, 404);
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

        if ($request->input('return_to') === 'installation') {
            $installationId = (int) ($request->input('installation_id') ?: $request->route('post')?->installation_id);

            if ($installationId > 0) {
                return redirect()
                    ->route('company.installations.show', $installationId)
                    ->with($error ? 'error' : 'success', $message);
            }
        }

        return redirect()
            ->route('company.clients.show', [$client, 'vista' => $vista])
            ->with($error ? 'error' : 'success', $message);
    }
}
