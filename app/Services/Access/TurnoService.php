<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Client;
use App\Models\GuardLog;
use App\Models\GuardShift;
use App\Models\SupervisorShiftTemplate;
use App\Models\User;
use App\Support\Company\CompanyOperateContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TurnoService
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function currentFor(User $user): ?GuardShift
    {
        return GuardShift::query()
            ->with(['location', 'shiftTemplate'])
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    public function hasOpenShiftFor(User $user): bool
    {
        return $this->currentFor($user) !== null;
    }

    /** Solo guarda real: Operar portería (súper/empresa) no exige turno. */
    public function isShiftOptionalFor(User $user): bool
    {
        if (CompanyOperateContext::mode() !== null) {
            return true;
        }

        return ! $user->hasRole('guardia');
    }

    public function mustCloseShiftBeforeLogout(User $user): bool
    {
        return ! $this->isShiftOptionalFor($user) && $this->hasOpenShiftFor($user);
    }

    /** @return Collection<int, SupervisorShiftTemplate> */
    public function templatesForCurrentClient(): Collection
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId < 1) {
            return collect();
        }

        return SupervisorShiftTemplate::query()
            ->where('security_company_id', $companyId)
            ->active()
            ->get();
    }

    public function open(
        User $user,
        int $locationId,
        SupervisorShiftTemplate $template,
        ?string $notes = null,
    ): GuardShift {
        if ($this->hasOpenShiftFor($user)) {
            throw ValidationException::withMessages([
                'shift_template_id' => 'Ya tienes un turno abierto. Ciérralo antes de abrir otro.',
            ]);
        }

        $now = now();
        $clientId = (int) ($this->tenant->clientId() ?? $user->primary_client_id);
        if ($clientId < 1) {
            throw ValidationException::withMessages([
                'location_id' => 'No hay cliente activo para abrir el turno.',
            ]);
        }

        $shift = GuardShift::query()->create([
            'client_id' => $clientId,
            'user_id' => $user->id,
            'location_id' => $locationId,
            'shift_template_id' => $template->id,
            'started_at' => $now,
            'start_notes' => $notes,
        ]);

        $this->writeMinutaLine(
            user: $user,
            locationId: $locationId,
            clientId: $clientId,
            at: $now,
            template: $template,
            opening: true,
            notes: $notes,
        );

        return $shift->load(['location', 'shiftTemplate']);
    }

    public function close(User $user, ?string $notes = null): ?GuardShift
    {
        $shift = $this->currentFor($user);

        if ($shift === null) {
            return null;
        }

        $now = now();
        $shift->update([
            'ended_at' => $now,
            'end_notes' => $notes,
        ]);

        if ($shift->location_id) {
            $template = $shift->shiftTemplate;
            $this->writeMinutaLine(
                user: $user,
                locationId: (int) $shift->location_id,
                clientId: (int) $shift->client_id,
                at: $now,
                template: $template,
                opening: false,
                notes: $notes,
            );
        }

        return $shift->fresh(['location', 'shiftTemplate']);
    }

    public function dailyMinutes(User $user, ?Carbon $date = null): int
    {
        $date ??= today();

        return (int) GuardShift::query()
            ->where('user_id', $user->id)
            ->whereDate('started_at', $date)
            ->whereNotNull('ended_at')
            ->get()
            ->sum(fn (GuardShift $shift) => (int) $shift->started_at->diffInMinutes($shift->ended_at));
    }

    private function writeMinutaLine(
        User $user,
        int $locationId,
        int $clientId,
        Carbon $at,
        ?SupervisorShiftTemplate $template,
        bool $opening,
        ?string $notes,
    ): void {
        $label = $template
            ? $template->name.' ('.$template->scheduleLabel().')'
            : 'Sin plantilla';
        $verb = $opening ? 'Inicio de turno' : 'Cierre de turno';
        $description = $verb.' · '.$label;
        if (filled($notes)) {
            $description .= '. Nota: '.trim((string) $notes);
        }

        GuardLog::query()->create([
            'client_id' => $clientId,
            'user_id' => $user->id,
            'location_id' => $locationId,
            'log_time' => $at,
            'type' => 'turno',
            'shift_type' => $this->shiftTypeLabel($template, $at),
            'description' => $description,
            'signed_at' => $at,
        ]);
    }

    private function shiftTypeLabel(?SupervisorShiftTemplate $template, Carbon $at): string
    {
        if ($template !== null) {
            $name = mb_strtolower($template->name);
            if (str_contains($name, 'noche') || str_contains($name, 'noct')) {
                return 'nocturno';
            }
            if (str_contains($name, 'día') || str_contains($name, 'dia') || str_contains($name, 'diurn')) {
                return 'diurno';
            }
        }

        return $at->hour >= 6 && $at->hour < 18 ? 'diurno' : 'nocturno';
    }

    private function resolveCompanyId(): int
    {
        $fromTenant = (int) ($this->tenant->companyId() ?? 0);
        if ($fromTenant > 0) {
            return $fromTenant;
        }

        $clientId = (int) ($this->tenant->clientId() ?? 0);
        if ($clientId > 0) {
            return (int) (Client::query()->whereKey($clientId)->value('security_company_id') ?? 0);
        }

        return 0;
    }
}
