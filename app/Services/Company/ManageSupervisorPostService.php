<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Installation;
use App\Models\SupervisorPost;
use App\Models\SupervisorPostModality;
use App\Support\Supervision\SupervisorReviewGeofence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageSupervisorPostService
{
    /**
     * @param  array{installation_id: int, name: string, modality?: int, is_active?: bool, employee_ids?: list<int>, observations?: ?string, latitude?: ?float, longitude?: ?float}  $data
     */
    public function create(Client $client, array $data): SupervisorPost
    {
        $this->assertClientCanHavePosts($client);

        $installation = $this->installationOfClient($client, (int) $data['installation_id']);
        $name = trim($data['name']);
        $this->assertUniqueName($installation, $name);
        $hours = $this->hoursForCompany((int) $client->security_company_id, (int) ($data['modality'] ?? 12));

        $post = SupervisorPost::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'name' => $name,
            'modality' => $hours,
            'service_days' => $this->validDays((int) ($data['service_days'] ?? SupervisorPost::ALL_DAYS)),
            'serves_holidays' => (bool) ($data['serves_holidays'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        $this->syncEmployees($client, $post, $data['employee_ids'] ?? []);

        $post->load(['installation', 'client', 'employees']);
        app(SupervisorReviewGeofence::class)->annotateIfPostFarFromInstallation($post);
        $installation = $post->installation;
        if ($installation instanceof Installation) {
            $this->recordChange(
                $client,
                $installation,
                $post,
                'Alta de puesto «'.$post->name.'» · '.$post->modalityLabel().' · '.$post->serviceDaysLabel().' · '.$installation->name,
                $data['observations'] ?? null,
                ['action' => 'created'],
            );
        }

        return $post->refresh()->load('employees');
    }

    /**
     * @param  array{installation_id?: int, name?: string, modality?: int, is_active?: bool, employee_ids?: list<int>, observations?: ?string, latitude?: ?float, longitude?: ?float}  $data
     */
    public function update(SupervisorPost $post, array $data): SupervisorPost
    {
        $client = $post->client;
        abort_unless($client instanceof Client, 404);
        $this->assertClientCanHavePosts($client);

        return DB::transaction(function () use ($post, $data, $client) {
        $post->load(['employees', 'installation', 'client']);
        $oldName = $post->name;
        $oldHours = (int) $post->modality;
        $oldActive = (bool) $post->is_active;
        $oldSchedule = $post->serviceDaysLabel();
        $oldInstallationId = (int) $post->installation_id;
        $oldInstallationName = $post->installation?->name ?? '—';
        $oldStaff = $post->employees->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $hadStaff = $oldStaff !== [];

        if (isset($data['installation_id'])) {
            $installation = $this->installationOfClient($client, (int) $data['installation_id']);
            $post->installation_id = $installation->id;
        }

        if (isset($data['name'])) {
            $name = trim($data['name']);
            $installation = $post->installation;
            abort_unless($installation instanceof Installation, 404);
            $this->assertUniqueName($installation, $name, $post->id);
            $post->name = $name;
        }

        if (isset($data['modality'])) {
            $post->modality = $this->hoursForCompany(
                (int) $client->security_company_id,
                (int) $data['modality'],
                (int) $post->modality,
            );
        }

        if (array_key_exists('is_active', $data)) {
            $post->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('service_days', $data)) {
            $post->service_days = $this->validDays((int) $data['service_days']);
        }

        if (array_key_exists('serves_holidays', $data)) {
            $post->serves_holidays = (bool) $data['serves_holidays'];
        }

        if (array_key_exists('latitude', $data) || array_key_exists('longitude', $data)) {
            $post->latitude = $data['latitude'] ?? null;
            $post->longitude = $data['longitude'] ?? null;
        }

        $post->save();
        $post->load('installation');
        app(SupervisorReviewGeofence::class)->annotateIfPostFarFromInstallation($post);

        $newStaff = $oldStaff;
        if (array_key_exists('employee_ids', $data)) {
            $this->syncEmployees($client, $post, $data['employee_ids'] ?? []);
            $newStaff = array_values(array_unique(array_map('intval', $data['employee_ids'] ?? [])));
            sort($newStaff);
        }

        $parts = [];
        if (isset($data['name']) && trim((string) $data['name']) !== $oldName) {
            $parts[] = 'Nombre «'.$oldName.'» → «'.$post->name.'»';
        }
        if (isset($data['modality']) && (int) $post->modality !== $oldHours) {
            $parts[] = 'Modalidad '.$oldHours.' h → '.$post->modality.' h';
        }
        if (isset($data['installation_id']) && (int) $post->installation_id !== $oldInstallationId) {
            $parts[] = 'Instalación '.$oldInstallationName.' → '.($post->installation?->name ?? '—');
        }
        if ($post->serviceDaysLabel() !== $oldSchedule) {
            $parts[] = 'Días '.$oldSchedule.' → '.$post->serviceDaysLabel();
        }
        if (array_key_exists('is_active', $data) && (bool) $post->is_active !== $oldActive) {
            $parts[] = $post->is_active ? 'Reactivado' : 'Inactivado';
        }
        $staffLine = $this->staffDeltaLine($oldStaff, $newStaff);
        if ($staffLine !== null) {
            $parts[] = $staffLine;
        }

        if ($parts === []) {
            return $post->refresh()->load('employees');
        }

        $firstStaffOnly = ! $hadStaff && $staffLine !== null && count($parts) === 1;
        $this->assertObservations($data['observations'] ?? null, required: ! $firstStaffOnly);

        $installation = $post->installation;
        if ($installation instanceof Installation) {
            $this->recordChange(
                $client,
                $installation,
                $post,
                'Cambio en «'.$post->name.'» · '.$installation->name.': '.implode('; ', $parts),
                $data['observations'] ?? null,
                ['action' => 'updated', 'parts' => $parts],
            );
        }

        return $post->refresh()->load('employees');
        });
    }

    public function delete(SupervisorPost $post, ?string $observations = null): void
    {
        if ($post->reviews()->exists()) {
            throw ValidationException::withMessages([
                'post' => 'No se puede eliminar: hay revistas de Supervisión en este puesto.',
            ]);
        }

        $this->assertObservations($observations, required: true);

        $client = $post->client;
        $installation = $post->installation;
        $label = 'Baja de puesto «'.$post->name.'»'.($installation?->name ? ' · '.$installation->name : '');

        $post->employees()->detach();
        $post->delete();

        if ($client instanceof Client && $installation instanceof Installation) {
            $this->recordChange($client, $installation, $post, $label, $observations, ['action' => 'deleted']);
        }
    }

    private function validDays(int $mask): int
    {
        $mask &= SupervisorPost::ALL_DAYS;
        if ($mask === 0) {
            throw ValidationException::withMessages([
                'service_days' => 'Elige al menos un día de servicio.',
            ]);
        }

        return $mask;
    }

    private function assertClientCanHavePosts(Client $client): void
    {
        if (! $client->has_access && ! $client->has_supervision) {
            abort(403);
        }
    }

    private function hoursForCompany(int $companyId, int $hours, ?int $currentHours = null): int
    {
        app(SeedSupervisorIntakeDefaultsService::class)->execute($companyId);

        $row = SupervisorPostModality::query()
            ->where('security_company_id', $companyId)
            ->where('hours', $hours)
            ->first();

        if ($row === null || (! $row->is_active && $currentHours !== $hours)) {
            throw ValidationException::withMessages([
                'modality' => 'Elige una modalidad activa del catálogo (Ajustes → Modalidades).',
            ]);
        }

        return $hours;
    }

    /** @param list<int|string> $employeeIds */
    private function syncEmployees(Client $client, SupervisorPost $post, array $employeeIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        if ($ids === []) {
            $post->employees()->sync([]);

            return;
        }

        $valid = Employee::query()
            ->where('security_company_id', $client->security_company_id)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Solo puedes asignar empleados activos de esta empresa.',
            ]);
        }

        $taken = Employee::query()
            ->whereIn('id', $valid)
            ->whereHas('supervisorPosts', fn ($q) => $q->whereKeyNot($post->id))
            ->with(['jobTitle', 'supervisorPosts.client'])
            ->get();

        if ($taken->isNotEmpty()) {
            $first = $taken->first();
            $other = $first->supervisorPosts->first();
            $job = $first->jobTitle?->name ?: 'empleado';
            $where = $other instanceof SupervisorPost
                ? $other->name.($other->client?->name ? ' · '.$other->client->name : '')
                : 'otro puesto';

            throw ValidationException::withMessages([
                'employee_ids' => $first->fullName().' ('.$job.') ya está en '.$where.'. Reasígnalo desde su ficha.',
            ]);
        }

        $post->employees()->sync($valid);
    }

    private function installationOfClient(Client $client, int $installationId): Installation
    {
        $installation = Installation::query()
            ->withoutGlobalScopes()
            ->where('id', $installationId)
            ->where('client_id', $client->id)
            ->first();

        if ($installation === null) {
            throw ValidationException::withMessages([
                'installation_id' => 'La instalación no pertenece a este cliente.',
            ]);
        }

        return $installation;
    }

    private function assertUniqueName(Installation $installation, string $name, ?int $ignoreId = null): void
    {
        $exists = SupervisorPost::query()
            ->withoutGlobalScopes()
            ->where('installation_id', $installation->id)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un puesto con ese nombre en esta instalación.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordChange(
        Client $client,
        Installation $installation,
        SupervisorPost $post,
        string $body,
        mixed $observations,
        array $payload,
    ): void {
        $note = is_string($observations) ? trim($observations) : '';
        app(\App\Services\Ops\RecordOperationalAlertService::class)->serviceChange(
            $client,
            $body,
            $installation,
            $post,
            auth()->user(),
            $payload + ['observations' => $note !== '' ? $note : null],
        );
    }

    private function assertObservations(mixed $observations, bool $required): void
    {
        if (! $required) {
            return;
        }

        $note = is_string($observations) ? trim($observations) : '';
        if ($note === '') {
            throw ValidationException::withMessages([
                'observations' => 'Indica por qué haces este cambio en el servicio.',
            ]);
        }
    }

    /**
     * @param  list<int>  $oldIds
     * @param  list<int>  $newIds
     */
    private function staffDeltaLine(array $oldIds, array $newIds): ?string
    {
        sort($oldIds);
        sort($newIds);
        if ($oldIds === $newIds) {
            return null;
        }

        $left = array_values(array_diff($oldIds, $newIds));
        $joined = array_values(array_diff($newIds, $oldIds));
        $people = Employee::query()
            ->whereIn('id', array_merge($left, $joined))
            ->get()
            ->keyBy('id');

        $bits = [];
        if ($left !== []) {
            $bits[] = 'Sale '.$this->names($people, $left);
        }
        if ($joined !== []) {
            $bits[] = ($oldIds === [] ? 'Asigna ' : 'Entra ').$this->names($people, $joined);
        }

        return $bits !== [] ? implode('; ', $bits) : 'Cambio de vigilantes';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $people
     * @param  list<int>  $ids
     */
    private function names($people, array $ids): string
    {
        return collect($ids)
            ->map(fn (int $id) => $people->get($id)?->fullName() ?? '#'.$id)
            ->implode(', ');
    }
}
