<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\SupervisorShiftStatus;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftTemplate;
use App\Services\Company\AutoCloseExpiredSupervisorShiftsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutoCloseExpiredSupervisorShiftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_at_template_end_plus_thirty_minutes_without_photos(): void
    {
        $this->seedWithPilot();
        $supervisor = $this->companySupervisor();
        $template = SupervisorShiftTemplate::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'name' => 'Día corto',
            'starts_at' => '06:00',
            'ends_at' => '14:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'supervisor_shift_template_id' => $template->id,
            'started_at' => CarbonImmutable::parse('2026-09-05 06:05:00'),
            'km_start' => 100,
        ]);

        $service = app(AutoCloseExpiredSupervisorShiftsService::class);

        $this->assertSame(0, $service->execute(CarbonImmutable::parse('2026-09-05 14:29:00')));
        $this->assertTrue($shift->fresh()->isOpen());

        $this->assertSame(1, $service->execute(CarbonImmutable::parse('2026-09-05 14:30:00')));
        $closed = $shift->fresh();
        $this->assertSame(SupervisorShiftStatus::Closed, $closed->status);
        $this->assertNull($closed->km_end_photo_path);
        $this->assertStringContainsString('Cierre automático: fin de turno 14:00 + 30 min.', (string) $closed->notes);
        $this->assertTrue($closed->closed_by_system);
        $this->assertSame(0, $service->execute(CarbonImmutable::parse('2026-09-05 15:00:00')));
        $this->assertIsArray($closed->sheet_snapshot);
        $this->assertStringContainsString('-T', (string) ($closed->sheet_snapshot['folio'] ?? ''));
    }

    public function test_auto_close_uses_gps_km_when_odometer_missing(): void
    {
        $this->seedWithPilot();
        $supervisor = $this->companySupervisor();
        $template = SupervisorShiftTemplate::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'name' => 'Día GPS',
            'starts_at' => '06:00',
            'ends_at' => '14:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'supervisor_shift_template_id' => $template->id,
            'started_at' => CarbonImmutable::parse('2026-09-05 06:05:00'),
            'km_start' => 100,
        ]);
        $shift->locations()->create([
            'recorded_at' => CarbonImmutable::parse('2026-09-05 07:00:00'),
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'source' => 'gps',
        ]);
        $shift->locations()->create([
            'recorded_at' => CarbonImmutable::parse('2026-09-05 07:20:00'),
            'latitude' => 3.4900,
            'longitude' => -76.5320,
            'source' => 'gps',
        ]);

        app(AutoCloseExpiredSupervisorShiftsService::class)->execute(CarbonImmutable::parse('2026-09-05 14:30:00'));

        $this->assertGreaterThanOrEqual(4, (int) $shift->fresh()->km_traveled);
    }

    public function test_auto_close_records_pending_outbox_in_notes_and_history_label(): void
    {
        $this->seedWithPilot();
        $supervisor = $this->companySupervisor();
        $template = SupervisorShiftTemplate::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'name' => 'Tarde',
            'starts_at' => '06:00',
            'ends_at' => '14:00',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'supervisor_shift_template_id' => $template->id,
            'started_at' => CarbonImmutable::parse('2026-09-05 06:05:00'),
            'pending_outbox_count' => 4,
        ]);

        app(AutoCloseExpiredSupervisorShiftsService::class)
            ->execute(CarbonImmutable::parse('2026-09-05 14:30:00'));

        $closed = $shift->fresh();
        $this->assertTrue($closed->closed_by_system);
        $this->assertStringContainsString('Pendiente en cola: 4 registros.', (string) $closed->notes);
    }

    public function test_overnight_template_closes_next_morning_plus_grace(): void
    {
        $this->seedWithPilot();
        $supervisor = $this->companySupervisor();
        $template = SupervisorShiftTemplate::query()
            ->where('security_company_id', $supervisor->security_company_id)
            ->where('name', 'Noche')
            ->firstOrFail();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'supervisor_shift_template_id' => $template->id,
            'started_at' => CarbonImmutable::parse('2026-09-05 18:10:00'),
        ]);

        $service = app(AutoCloseExpiredSupervisorShiftsService::class);

        $this->assertSame(0, $service->execute(CarbonImmutable::parse('2026-09-06 06:29:00')));
        $this->assertSame(1, $service->execute(CarbonImmutable::parse('2026-09-06 06:30:00')));
        $this->assertFalse($shift->fresh()->isOpen());
    }

    public function test_without_template_falls_back_to_three_idle_hours(): void
    {
        $this->seedWithPilot();
        $supervisor = $this->companySupervisor();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => CarbonImmutable::parse('2026-09-05 10:00:00'),
        ]);
        $shift->locations()->create([
            'recorded_at' => CarbonImmutable::parse('2026-09-05 11:00:00'),
            'latitude' => 3.45,
            'longitude' => -76.53,
            'source' => 'gps',
        ]);

        $service = app(AutoCloseExpiredSupervisorShiftsService::class);

        $this->assertSame(0, $service->execute(CarbonImmutable::parse('2026-09-05 13:59:00')));
        $this->assertSame(1, $service->execute(CarbonImmutable::parse('2026-09-05 14:00:00')));
        $this->assertStringContainsString('3 h desde último GPS', (string) $shift->fresh()->notes);
    }
}
