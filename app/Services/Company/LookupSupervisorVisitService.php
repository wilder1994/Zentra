<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Client;
use App\Models\Employee;
use App\Models\SupervisorPost;
use App\Models\User;
use App\Support\Supervision\SupervisorClientScope;

final class LookupSupervisorVisitService
{
    public function __construct(
        private readonly ManageSupervisorShiftService $shifts,
        private readonly SupervisorClientScope $scope,
    ) {}

    /**
     * @return list<array{id: int, name: string}>
     */
    public function sites(User $user): array
    {
        $visible = $this->visibleClientIds($user);

        return Client::query()
            ->where('security_company_id', $user->security_company_id)
            ->where('has_supervision', true)
            ->where('is_active', true)
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible !== [] ? $visible : [0]))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Clientes que el supervisor puede ver ahora. Null = todos (la empresa aún no usa zonas).
     * Sin turno abierto y con zonas en uso no ve ninguno.
     *
     * @return list<int>|null
     */
    public function visibleClientIds(User $user): ?array
    {
        if (! $this->scope->companyUsesZones((int) $user->security_company_id)) {
            return null;
        }

        $shift = $this->shifts->currentFor($user);

        return $shift !== null ? ($this->scope->visibleClientIds($shift) ?? []) : [];
    }

    /**
     * @return list<array{id: int, name: string, installation_id: int, installation_name: ?string, label: string}>
     */
    public function posts(User $user, int $clientId, string $query = ''): array
    {
        $client = $this->companySupervisionClient($user, $clientId);
        if ($client === null) {
            return [];
        }

        $visible = $this->visibleClientIds($user);
        if ($visible !== null && ! in_array((int) $client->id, $visible, true)) {
            return [];
        }

        $q = SupervisorPost::query()
            ->withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->whereHas('installation', fn ($iq) => $iq->where('is_active', true))
            ->with('installation:id,name')
            ->orderBy('name');

        $term = trim($query);
        if ($term !== '') {
            $q->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', '%'.$term.'%')
                    ->orWhereHas('installation', fn ($iq) => $iq->where('name', 'like', '%'.$term.'%'));
            });
        }

        return $q->limit(30)
            ->get()
            ->map(fn (SupervisorPost $post) => [
                'id' => $post->id,
                'name' => $post->name,
                'installation_id' => $post->installation_id,
                'installation_name' => $post->installation?->name,
                'label' => trim(($post->installation?->name ?? '').' · '.$post->name, ' ·'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, document_number: string, name: string}>
     */
    public function guards(User $user, string $document): array
    {
        $term = preg_replace('/\s+/', '', $document) ?? '';
        if (strlen($term) < 3) {
            return [];
        }

        return Employee::query()
            ->where('security_company_id', $user->security_company_id)
            ->where('is_active', true)
            ->whereNull('ceased_at')
            ->where('document_number', 'like', $term.'%')
            ->whereHas('jobTitle', function ($q): void {
                $q->where('name', 'like', '%vigilante%');
            })
            ->with('jobTitle:id,name')
            ->orderBy('document_number')
            ->limit(20)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'document_number' => $employee->document_number,
                'name' => $employee->fullName(),
            ])
            ->values()
            ->all();
    }

    public function companySupervisionClient(User $user, int $clientId): ?Client
    {
        return Client::query()
            ->where('id', $clientId)
            ->where('security_company_id', $user->security_company_id)
            ->where('has_supervision', true)
            ->first();
    }
}
