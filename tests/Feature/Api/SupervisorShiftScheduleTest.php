<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SupervisionPackageSku;
use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftTemplate;
use App\Services\Company\AutoCloseExpiredSupervisorShiftsService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Turnos del supervisor: relevo anticipado, plantilla fuera de horario y cierre guardado en el teléfono. */
final class SupervisorShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_night_shift_opened_early_is_not_closed_by_the_system(): void
    {
        $this->at('2026-10-12 17:30');
        $token = $this->supervisorToken();

        $this->withToken($token)
            ->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload(['shift_template_id' => $this->template('Noche')->id]))
            ->assertCreated();

        $this->at('2026-10-12 18:05');
        app(AutoCloseExpiredSupervisorShiftsService::class)->execute();
        $this->assertSame(SupervisorShiftStatus::Open, SupervisorShift::query()->firstOrFail()->status);

        $this->at('2026-10-13 06:35');
        app(AutoCloseExpiredSupervisorShiftsService::class)->execute();
        $shift = SupervisorShift::query()->firstOrFail();
        $this->assertSame(SupervisorShiftStatus::Closed, $shift->status);
        $this->assertSame('2026-10-13 06:30', $shift->ended_at->format('Y-m-d H:i'));
    }

    public function test_day_shift_cannot_be_opened_at_night(): void
    {
        $this->at('2026-10-14 20:10');
        $token = $this->supervisorToken();

        $this->withToken($token)
            ->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload(['shift_template_id' => $this->template('Día')->id]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift_template_id')
            ->assertSee('Ahora corresponde Noche', false);

        $this->assertSame(0, SupervisorShift::query()->count());
    }

    public function test_queued_close_of_an_old_shift_does_not_close_the_new_one(): void
    {
        $this->at('2026-10-14 08:00');
        $token = $this->supervisorToken();

        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertCreated();
        $old = SupervisorShift::query()->firstOrFail();
        $this->withToken($token)->post('/api/supervision/shifts/close', $this->supervisorShiftClosePayload(['km_end' => 1010]))->assertOk();

        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload(['km_start' => 1010]))->assertCreated();
        $new = SupervisorShift::query()->latest('id')->firstOrFail();

        $this->withToken($token)
            ->post('/api/supervision/shifts/close', $this->supervisorShiftClosePayload([
                'km_end' => 1010,
                'shift_id' => $old->id,
                'client_event_id' => '7d6f1b0c-1111-4a2b-9c3d-0123456789ab',
            ]))
            ->assertOk()
            ->assertJsonPath('shift.id', $old->id);

        $this->assertSame(SupervisorShiftStatus::Open, $new->fresh()->status);
    }

    private function supervisorToken(): string
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute($user->securityCompany, SupervisionPackageSku::fromSize(1));

        return $this->loginCompanySupervisor();
    }

    private function template(string $name): SupervisorShiftTemplate
    {
        return SupervisorShiftTemplate::query()
            ->where('security_company_id', $this->companySupervisor()->security_company_id)
            ->where('name', $name)
            ->firstOrFail();
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, (string) config('app.timezone')));
    }
}
