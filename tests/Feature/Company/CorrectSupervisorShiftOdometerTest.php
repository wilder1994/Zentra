<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\SupervisionPackageSku;
use App\Enums\SupervisorShiftStatus;
use App\Models\AuditLog;
use App\Models\SupervisorFleetVehicle;
use App\Models\SupervisorShift;
use App\Models\User;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CorrectSupervisorShiftOdometerTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_corrects_a_closed_shift_and_the_vehicle_last_km(): void
    {
        $admin = $this->admin();
        $supervisor = $this->companySupervisor();
        $vehicle = SupervisorFleetVehicle::query()->create([
            'security_company_id' => $admin->security_company_id,
            'plate' => 'SJQ123',
            'brand' => 'Chevrolet',
            'last_km' => 150200,
        ]);

        SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $vehicle->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addHours(8),
            'km_start' => 10000,
            'km_end' => 10100,
            'km_traveled' => 100,
        ]);
        $bad = SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $vehicle->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHours(8),
            'ended_at' => now()->subHour(),
            'km_start' => 150000,
            'km_end' => 150200,
            'km_traveled' => 200,
        ]);

        $this->actingAs($admin)
            ->get(route('company.supervision.index', ['tab' => 'sheets']))
            ->assertOk()
            ->assertSee('Kilometrajes por revisar')
            ->assertSee('SJQ123');

        $this->actingAs($admin)
            ->get(route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $bad->id]))
            ->assertOk()
            ->assertSee('Corregir kilometraje');

        $this->actingAs($admin)
            ->post(route('company.supervision.shifts.odometer', $bad), [
                'km_start' => 15000,
                'km_end' => 15200,
                'reason' => 'Cero de más en el odómetro, según la foto.',
            ])
            ->assertRedirect(route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $bad->id]));

        $bad->refresh();
        $this->assertSame(15000, $bad->km_start);
        $this->assertSame(15200, $bad->km_end);
        $this->assertSame(200, $bad->km_traveled);
        $this->assertSame(15200, $vehicle->fresh()->last_km);
        $this->assertSame('Km inicio: 15000', $bad->sheet_snapshot['sections'][0]['rows'][2] ?? null);
        $this->assertTrue(
            AuditLog::query()->where('action', 'supervision.odometer.corrected')->where('auditable_id', $bad->id)->exists(),
        );
    }

    public function test_a_lower_close_is_rejected_and_an_open_shift_only_changes_the_start(): void
    {
        $admin = $this->admin();
        $supervisor = $this->companySupervisor();
        $vehicle = SupervisorFleetVehicle::query()->create([
            'security_company_id' => $admin->security_company_id,
            'plate' => 'SJQ124',
            'brand' => 'Chevrolet',
            'last_km' => 90000,
        ]);
        $closed = SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $vehicle->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
            'km_start' => 90000,
            'km_end' => 90100,
            'km_traveled' => 100,
        ]);
        $open = SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $vehicle->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
            'km_start' => 90000,
        ]);

        $this->actingAs($admin)
            ->from(route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $closed->id]))
            ->post(route('company.supervision.shifts.odometer', $closed), [
                'km_start' => 12000,
                'km_end' => 11000,
                'reason' => 'El cierre no puede quedar debajo.',
            ])
            ->assertSessionHasErrors('km_end');
        $this->assertSame(90000, $closed->fresh()->km_start);

        $this->actingAs($admin)
            ->post(route('company.supervision.shifts.odometer', $open), [
                'km_start' => 12000,
                'reason' => 'El inicio se digitó con un cero de más.',
            ])
            ->assertRedirect();

        $this->assertSame(12000, $open->fresh()->km_start);
        $this->assertNull($open->fresh()->km_end);
        $this->assertSame(12000, $vehicle->fresh()->last_km);
    }

    private function admin(): User
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::fromSize(1),
        );

        return $admin;
    }
}
