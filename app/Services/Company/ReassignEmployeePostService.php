<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\SupervisorPost;
use App\Services\Ops\RecordOperationalAlertService;
use App\Support\Catalog\PostQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReassignEmployeePostService
{
    public function execute(Employee $employee, int $clientId, int $installationId, int $postId): SupervisorPost
    {
        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'employee' => 'Solo puedes asignar empleados activos.',
            ]);
        }

        $client = Client::query()
            ->whereKey($clientId)
            ->where('security_company_id', $employee->security_company_id)
            ->first();

        if ($client === null || (! $client->has_access && ! $client->has_supervision)) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente no pertenece a esta empresa o no tiene línea operativa.',
            ]);
        }

        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->whereKey($installationId)
            ->where('client_id', $client->id)
            ->first();

        if ($installation === null) {
            throw ValidationException::withMessages([
                'installation_id' => 'La instalación no pertenece a este cliente.',
            ]);
        }

        $post = SupervisorPost::query()
            ->withoutGlobalScopes()
            ->whereKey($postId)
            ->where('client_id', $client->id)
            ->where('installation_id', $installation->id)
            ->first();

        if ($post === null) {
            throw ValidationException::withMessages([
                'supervisor_post_id' => 'El puesto no pertenece a esa instalación.',
            ]);
        }

        if (! $post->is_active) {
            throw ValidationException::withMessages([
                'supervisor_post_id' => 'El puesto «'.$post->name.'» está archivado. Restáuralo primero.',
            ]);
        }

        PostQuota::assertEmployeesFit(
            $post,
            PostQuota::assignedEmployees($post, (int) $employee->id) + 1,
            'No puedes asignar a '.$employee->fullName().':',
        );

        DB::transaction(function () use ($employee, $post): void {
            $employee->supervisorPosts()->sync([$post->id]);
        });

        $post->load(['installation', 'client']);
        if ($post->client instanceof Client && $post->installation instanceof Installation) {
            app(RecordOperationalAlertService::class)->serviceChange(
                $post->client,
                $employee->fullName().' asignado a «'.$post->name.'» · '.$post->installation->name,
                $post->installation,
                $post,
                auth()->user(),
            );
        }

        return $post->refresh();
    }
}
