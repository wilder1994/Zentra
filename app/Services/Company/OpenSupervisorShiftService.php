<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Domain\Supervision\Data\OpenSupervisorShiftInput;
use App\Enums\SupervisorChecklistKind;
use App\Enums\SupervisorShiftSlot;
use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorChecklistItem;
use App\Models\SupervisorFleetVehicle;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftTemplate;
use App\Models\SupervisorZone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OpenSupervisorShiftService
{
    public function __construct(
        private readonly SeedSupervisorIntakeDefaultsService $defaults,
        private readonly ManageSupervisorShiftService $shifts,
        private readonly ResolveSupervisorShiftDeadlineService $deadlines,
    ) {}

    public function execute(User $user, OpenSupervisorShiftInput $input): SupervisorShift
    {
        $this->shifts->assertCanOperate($user);

        if ($this->shifts->currentFor($user) !== null) {
            throw ValidationException::withMessages([
                'shift' => 'Ya tiene un turno abierto. Ciérrelo antes de iniciar otro.',
            ]);
        }

        $companyId = (int) $user->security_company_id;
        $this->defaults->execute($companyId);

        $template = SupervisorShiftTemplate::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->find($input->shiftTemplateId);

        if ($template === null) {
            throw ValidationException::withMessages([
                'shift_template_id' => 'El turno no pertenece a esta empresa o está inactivo.',
            ]);
        }

        $this->assertWithinSchedule($companyId, $template);

        $zone = SupervisorZone::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->find($input->zoneId);

        if ($zone === null) {
            throw ValidationException::withMessages([
                'zone_id' => 'La zona no pertenece a esta empresa o está inactiva.',
            ]);
        }

        $this->assertChecklist(
            SupervisorChecklistItem::keyedLabels($companyId, SupervisorChecklistKind::Ppe),
            $input->ppeChecklist,
            'ppe_checklist',
        );
        $this->assertChecklist(
            SupervisorChecklistItem::keyedLabels($companyId, SupervisorChecklistKind::Vehicle),
            $input->vehicleChecklist,
            'vehicle_checklist',
        );

        return DB::transaction(function () use ($user, $input, $companyId, $template, $zone) {
            $vehicle = $this->resolveVehicle($user, $input);

            if ($input->kmStart < (int) $vehicle->last_km) {
                throw ValidationException::withMessages([
                    'km_start' => 'El kilometraje de inicio no puede ser menor que el último cierre de este vehículo ('.$vehicle->last_km.' km).',
                ]);
            }

            $shift = SupervisorShift::query()->create([
                'security_company_id' => $companyId,
                'user_id' => $user->id,
                'supervisor_fleet_vehicle_id' => $vehicle->id,
                'supervisor_zone_id' => $zone->id,
                'supervisor_shift_template_id' => $template->id,
                'status' => SupervisorShiftStatus::Open,
                'shift_slot' => $this->slotFromTemplate($template),
                'schedule_label' => $template->scheduleLabel(),
                'route_zone' => $zone->name,
                'km_start' => $input->kmStart,
                'ppe_checklist' => $input->ppeChecklist,
                'vehicle_checklist' => $input->vehicleChecklist,
                'started_at' => CarbonImmutable::now(),
            ]);

            $dir = 'supervision/'.$companyId.'/'.$shift->id;
            $odoPath = $this->storePhoto($input->odometerPhoto, $dir, 'start_odometer.jpg');
            $selfiePath = $this->storePhoto($input->selfiePhoto, $dir, 'start_selfie.jpg');

            $shift->update([
                'km_start_photo_path' => $odoPath,
                'km_start_selfie_path' => $selfiePath,
            ]);

            $vehicle->update(['last_km' => $input->kmStart]);

            return $shift->fresh(['fleetVehicle', 'zone', 'shiftTemplate']);
        });
    }

    /**
     * Sin esto, un turno Día abierto a las 20:00 (o Noche a las 15:00) nacía vencido
     * y el cierre automático lo cerraba a los pocos minutos.
     */
    private function assertWithinSchedule(int $companyId, SupervisorShiftTemplate $template): void
    {
        if (! $template->starts_at || ! $template->ends_at) {
            return;
        }

        $now = CarbonImmutable::now();
        if ($this->deadlines->window($now, (string) $template->starts_at, (string) $template->ends_at) !== null) {
            return;
        }

        $fits = SupervisorShiftTemplate::query()
            ->where('security_company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->get()
            ->first(fn (SupervisorShiftTemplate $other) => $this->deadlines->window($now, (string) $other->starts_at, (string) $other->ends_at) !== null);

        throw ValidationException::withMessages([
            'shift_template_id' => 'El turno '.$template->name.' es de '.substr((string) $template->starts_at, 0, 5).' a '.substr((string) $template->ends_at, 0, 5)
                .' y a esta hora no está en su horario (se puede abrir hasta '.(ResolveSupervisorShiftDeadlineService::EARLY_START_MINUTES / 60).' h antes).'
                .($fits !== null ? ' Ahora corresponde '.$fits->name.' ('.$fits->scheduleLabel().').' : ' Elija el turno que corresponde a esta hora.'),
        ]);
    }

    private function slotFromTemplate(SupervisorShiftTemplate $template): SupervisorShiftSlot
    {
        $name = Str::lower($template->name);

        return match (true) {
            str_contains($name, 'noche') => SupervisorShiftSlot::Night,
            str_contains($name, 'mixto') => SupervisorShiftSlot::Mixed,
            default => SupervisorShiftSlot::Day,
        };
    }

    private function resolveVehicle(User $user, OpenSupervisorShiftInput $input): SupervisorFleetVehicle
    {
        $companyId = (int) $user->security_company_id;

        if ($input->vehicleId !== null) {
            $vehicle = SupervisorFleetVehicle::query()
                ->where('security_company_id', $companyId)
                ->find($input->vehicleId);

            if ($vehicle === null) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'El vehículo no pertenece a esta empresa.',
                ]);
            }

            return $vehicle;
        }

        $plate = SupervisorFleetVehicle::normalizePlate((string) $input->plate);
        if ($plate === '' || $input->brand === null || $input->brand === '') {
            throw ValidationException::withMessages([
                'vehicle' => 'Primera vez: diligencie placa y marca del vehículo de flota.',
            ]);
        }

        $existing = SupervisorFleetVehicle::query()
            ->where('security_company_id', $companyId)
            ->where('plate', $plate)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return SupervisorFleetVehicle::query()->create([
            'security_company_id' => $companyId,
            'registered_by_user_id' => $user->id,
            'plate' => $plate,
            'brand' => $input->brand,
            'line' => $input->line,
            'model' => $input->model,
            'color' => $input->color,
            'type' => $input->type,
            'soat_expires_at' => $input->soatExpiresAt,
            'technical_review_expires_at' => $input->technicalReviewExpiresAt,
            'last_km' => 0,
        ]);
    }

    /**
     * @param  array<string, string>  $definitions
     * @param  array<string, bool>  $payload
     */
    private function assertChecklist(array $definitions, array $payload, string $field): void
    {
        foreach ($definitions as $key => $label) {
            if (! ($payload[$key] ?? false)) {
                throw ValidationException::withMessages([
                    $field.'.'.$key => 'Debe confirmar: '.$label,
                ]);
            }
        }
    }

    private function storePhoto(UploadedFile $file, string $directory, string $name): string
    {
        $path = $file->storeAs($directory, $name, 'local');
        if ($path === false) {
            throw ValidationException::withMessages([
                'photos' => 'No se pudo guardar la evidencia fotográfica.',
            ]);
        }

        return $path;
    }
}
