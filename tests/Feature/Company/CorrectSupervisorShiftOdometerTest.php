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

        $mileage = ['tab' => 'sheets', 'vista' => 'supervisores', 'sup' => 'kilometraje'];
        $this->actingAs($admin)
            ->get(route('company.supervision.index', $mileage))
            ->assertOk()
            ->assertSee('Kilometrajes por revisar')
            ->assertSee('SJQ123')
            ->assertSee('Editar');

        $this->actingAs($admin)
            ->get(route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $bad->id, 'embed' => 1]))
            ->assertOk()
            ->assertSee('Corregir kilometraje')
            ->assertSee('Cierre anterior de la placa')
            ->assertSee('Inicio → cierre de este turno', false)
            ->assertSee('name="embed"', false);

        $this->actingAs($admin)
            ->post(route('company.supervision.shifts.odometer', $bad), [
                'km_start' => 15000,
                'km_end' => 15200,
                'reason' => 'Cero de más en el odómetro, según la foto.',
            ])
            ->assertRedirect(route('company.supervision.sheets.show', ['kind' => 'shift', 'id' => $bad->id]));

        $this->actingAs($admin)
            ->get(route('company.supervision.index', $mileage))
            ->assertOk()
            ->assertDontSee('Kilometrajes por revisar')
            ->assertSee('SJQ123')
            ->assertSee('Editar');

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

    public function test_accepting_a_real_gap_removes_it_and_the_period_filter_applies(): void
    {
        $admin = $this->admin();
        $supervisor = $this->companySupervisor();
        $vehicle = SupervisorFleetVehicle::query()->create([
            'security_company_id' => $admin->security_company_id,
            'plate' => 'GAP001',
            'brand' => 'Chevrolet',
            'last_km' => 69410,
        ]);
        SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $vehicle->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHours(6),
            'ended_at' => now()->subHours(5),
            'km_start' => 66914,
            'km_end' => 66914,
            'km_traveled' => 0,
        ]);
        $current = SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $vehicle->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(30),
            'km_start' => 69410,
            'km_end' => 69417,
            'km_traveled' => 7,
        ]);
        $outside = SupervisorFleetVehicle::query()->create([
            'security_company_id' => $admin->security_company_id,
            'plate' => 'OLD999',
            'brand' => 'Chevrolet',
            'last_km' => 200000,
        ]);
        SupervisorShift::query()->create([
            'security_company_id' => $admin->security_company_id,
            'user_id' => $supervisor->id,
            'supervisor_fleet_vehicle_id' => $outside->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subMonths(2),
            'ended_at' => now()->subMonths(2)->addHours(8),
            'km_start' => 10000,
            'km_end' => 200000,
            'km_traveled' => 190000,
        ]);

        $today = now()->toDateString();
        $mileageToday = ['tab' => 'sheets', 'vista' => 'supervisores', 'sup' => 'kilometraje', 'from' => $today, 'to' => $today];
        $this->actingAs($admin)
            ->get(route('company.supervision.index', $mileageToday))
            ->assertOk()
            ->assertSee('Cierre anterior → inicio: 2.496 km', false)
            ->assertDontSee('OLD999');

        $this->actingAs($admin)
            ->post(route('company.supervision.shifts.odometer.accept', $current), ['embed' => 1])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('company.supervision.index', $mileageToday))
            ->assertOk()
            ->assertDontSee('El recorrido del turno pasa de 1.500 km', false)
            ->assertSee('GAP001')
            ->assertSee('Editar');
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

    public function test_mileage_table_lists_shifts_without_a_flag_and_filters_by_difference(): void
    {
        $admin = $this->admin();
        $supervisor = $this->companySupervisor();
        $small = SupervisorFleetVehicle::query()->create([
            'security_company_id' => $admin->security_company_id,
            'plate' => 'LOW010',
            'brand' => 'Chevrolet',
            'last_km' => 110,
        ]);
        $large = SupervisorFleetVehicle::query()->create([
            'security_company_id' => $admin->security_company_id,
            'plate' => 'BIG300',
            'brand' => 'Chevrolet',
            'last_km' => 400,
        ]);
        foreach ([$small, $large] as $vehicle) {
            SupervisorShift::query()->create([
                'security_company_id' => $admin->security_company_id,
                'user_id' => $supervisor->id,
                'supervisor_fleet_vehicle_id' => $vehicle->id,
                'status' => SupervisorShiftStatus::Closed,
                'started_at' => now()->subHours(2),
                'ended_at' => now()->subHour(),
                'km_start' => 100,
                'km_end' => $vehicle->last_km,
                'km_traveled' => $vehicle->last_km - 100,
            ]);
        }

        $base = ['tab' => 'sheets', 'vista' => 'supervisores', 'sup' => 'kilometraje'];
        $this->actingAs($admin)
            ->get(route('company.supervision.index', ['tab' => 'sheets']))
            ->assertOk()
            ->assertSee('Fichas de guardas')
            ->assertDontSee('Diferencia desde')
            ->assertDontSee('LOW010');

        $this->actingAs($admin)
            ->get(route('company.supervision.index', $base))
            ->assertOk()
            ->assertSee('Diferencia desde')
            ->assertSee('Km anterior')
            ->assertSee('LOW010')
            ->assertSee('BIG300')
            ->assertSee('Editar')
            ->assertDontSee('Kilometrajes por revisar');

        $this->actingAs($admin)
            ->get(route('company.supervision.index', $base + ['km_diff' => 100]))
            ->assertOk()
            ->assertDontSee('LOW010')
            ->assertSee('BIG300');

        $this->actingAs($admin)
            ->get(route('company.supervision.index', ['tab' => 'sheets', 'vista' => 'supervisores', 'sup' => 'fichas']))
            ->assertOk()
            ->assertSee('Fichas de supervisión')
            ->assertSee('BIG300')
            ->assertDontSee('Diferencia desde');
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
