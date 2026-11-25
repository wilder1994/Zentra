<?php

declare(strict_types=1);

namespace Tests\Feature\Observatory;

use App\Enums\ObservatoryReporterRole;
use App\Enums\ObservatoryReportSource;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorShiftStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ObservatoryEvent;
use App\Models\ObservatoryReport;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorPost;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Services\Ops\BuildActivityBubblesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObservatoryActivityBubbleTest extends TestCase
{
    use RefreshDatabase;

    public function test_observatory_bubbles_skip_clients_without_the_module(): void
    {
        $this->seedWithPilot();
        $companyId = $this->pilotCompanyId();
        $with = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $without = Client::query()->where('slug', 'torres-loma')->firstOrFail();
        $without->update(['has_observatory' => false, 'has_supervision' => true]);
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $companyId,
            'user_id' => $this->companySupervisor()->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);
        $employee = Employee::query()->where('security_company_id', $companyId)->firstOrFail();
        $this->review($shift->id, $with, $employee, 'Palmas visible');
        $this->review($shift->id, $without, $employee, 'Torres oculta');
        SupervisorFieldLog::query()->create([
            'supervisor_shift_id' => $shift->id,
            'security_company_id' => $companyId,
            'user_id' => $shift->user_id,
            'client_id' => $without->id,
            'module' => SupervisorFieldModule::Alarms,
            'outcome' => SupervisorFieldOutcome::Ok,
            'payload' => ['kind' => 'response', 'result' => 'false_alarm'],
            'recorded_at' => now(),
        ]);
        $site = $without->installations()->firstOrFail();
        $event = ObservatoryEvent::query()->create([
            'client_id' => $without->id,
            'installation_id' => $site->id,
            'status' => 'nuevo',
            'title' => 'Folio oculto',
            'opened_at' => now(),
        ]);
        ObservatoryReport::query()->create([
            'event_id' => $event->id,
            'client_id' => $without->id,
            'installation_id' => $site->id,
            'source' => ObservatoryReportSource::Whatsapp,
            'reporter_role' => ObservatoryReporterRole::Padre,
            'kind' => 'hurto',
            'body' => 'Reporte de un cliente sin observatorio.',
            'is_anonymous' => true,
        ]);

        $texts = collect(app(BuildActivityBubblesService::class)->forCompany($companyId, null, true))
            ->pluck('text')
            ->implode(' ');

        $this->assertStringContainsString('Palmas visible', $texts);
        $this->assertStringContainsString('revisó', $texts);
        $this->assertStringNotContainsString('Torres', $texts);
        $this->assertStringNotContainsString('sin observatorio', $texts);

        $company = collect(app(BuildActivityBubblesService::class)->forCompany($companyId))
            ->pluck('text')
            ->implode(' ');
        $this->assertStringContainsString('Torres oculta', $company);
    }

    private function review(int $shiftId, Client $client, Employee $employee, string $postName): void
    {
        $installation = $client->installations()->firstOrFail();
        $post = SupervisorPost::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'name' => $postName,
            'modality' => 24,
            'service_days' => SupervisorPost::ALL_DAYS,
            'serves_holidays' => true,
            'reviews_per_day' => 1,
            'is_active' => true,
        ]);
        SupervisorShiftReview::query()->create([
            'supervisor_shift_id' => $shiftId,
            'client_id' => $client->id,
            'supervisor_post_id' => $post->id,
            'employee_id' => $employee->id,
            'latitude' => 3.45,
            'longitude' => -76.53,
            'recorded_at' => now(),
        ]);
    }
}
