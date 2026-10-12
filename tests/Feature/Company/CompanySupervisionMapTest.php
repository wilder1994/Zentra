<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\SupervisionPackageSku;
use App\Enums\SupervisorFieldModule;
use App\Enums\SupervisorFieldOutcome;
use App\Enums\SupervisorShiftStatus;
use App\Models\Client;
use App\Models\SupervisorFieldLog;
use App\Models\SupervisorShift;
use App\Models\SupervisorZone;
use App\Models\User;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CompanySupervisionMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_open_supervision_map(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $supervisor = $this->companySupervisor();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $user->securityCompany,
            SupervisionPackageSku::Sit1,
        );

        $response = $this->actingAs($user)->get(route('company.supervision.index'));

        $response->assertOk();
        $response->assertSee('Supervisión');
        $response->assertSee('En vivo');
        $response->assertSee('Historial');
        $response->assertSee('Resumen');
        $response->assertSee('Fichas');
        $response->assertSee('Supervisores en turno');
        $response->assertSee('Inicio de turno');
        $response->assertSee('Apoyo');
        $response->assertSee('Alarma');
        $response->assertSee('Convenciones');
        $response->assertSee('se actualizan solos cada 10 s');
        $response->assertSee('Pantalla apagada');
        $response->assertSee('Sin señal');
        $response->assertSee('Satélite');
        $response->assertSee('Terreno');
        $response->assertSee('Palmas');
        $response->assertDontSee('Nueve módulos');
        $response->assertDontSee('Hoy');
        $response->assertDontSee('Descargar PPTX');

        $history = $this->actingAs($user)->get(route('company.supervision.index', ['tab' => 'history']));
        $history->assertOk();
        $history->assertSee('Turnos del periodo');
        $history->assertSee('Inicio de turno');
        $history->assertSee('una ruta a la vez');
        $history->assertSee('Desde');
        $history->assertSee('Hasta');
        $history->assertSee('Supervisor');
        $history->assertSee($supervisor->name);
        $history->assertDontSee('Replay');
        $history->assertDontSee('Reproducir');
        $history->assertDontSee('>Zona<', false);
        $history->assertDontSee('Descargar PPTX');

        SupervisorShift::query()->create([
            'security_company_id' => $user->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHours(8),
            'ended_at' => now()->subHours(2),
            'closed_by_system' => true,
            'pending_outbox_count' => 2,
        ]);
        $historyClosed = $this->actingAs($user)->get(route('company.supervision.index', ['tab' => 'history']));
        $historyClosed->assertOk();
        $historyClosed->assertSee('Cierre por el sistema');
        $historyClosed->assertSee('2 registros en cola');

        $summary = $this->actingAs($user)->get(route('company.supervision.index', ['tab' => 'summary']));
        $summary->assertOk();
        $summary->assertSee('Cumplimiento por puesto');
        $summary->assertSee('Revistas');
        $summary->assertSee('Descargar PPTX');
        $summary->assertSee('Hoy');
        $summary->assertSee('Zona');
        $summary->assertDontSee('Nueve módulos');
    }

    public function test_live_map_ignores_zone_filter_and_history_filters_supervisor(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );

        $supervisor = $this->companySupervisor();
        $other = $this->companySupervisor('Otro', 'Filtro', '1199005500', 'otro.filtro.5500');

        $norte = SupervisorZone::query()
            ->where('security_company_id', $supervisor->security_company_id)
            ->where('name', 'Norte')
            ->firstOrFail();
        $sur = SupervisorZone::query()
            ->where('security_company_id', $supervisor->security_company_id)
            ->where('name', 'Sur')
            ->firstOrFail();

        SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'supervisor_zone_id' => $norte->id,
            'started_at' => now(),
        ]);
        SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $other->id,
            'status' => SupervisorShiftStatus::Open,
            'supervisor_zone_id' => $sur->id,
            'started_at' => now(),
        ]);
        SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Closed,
            'supervisor_zone_id' => $norte->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
        ]);
        SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $other->id,
            'status' => SupervisorShiftStatus::Closed,
            'supervisor_zone_id' => $sur->id,
            'started_at' => now()->subHours(4),
            'ended_at' => now()->subHours(2),
        ]);

        $live = $this->actingAs($admin)->get(route('company.supervision.index', [
            'zone_id' => $norte->id,
            'supervisor_id' => $supervisor->id,
        ]));
        $live->assertOk();
        $live->assertSee('"user":"'.$supervisor->name.'"', false);
        $live->assertSee('"user":"'.$other->name.'"', false);

        $history = $this->actingAs($admin)->get(route('company.supervision.index', [
            'tab' => 'history',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'supervisor_id' => $supervisor->id,
        ]));
        $history->assertOk();
        $history->assertSee('"user":"'.$supervisor->name.'"', false);
        $history->assertDontSee('"user":"'.$other->name.'"', false);
    }

    public function test_live_map_embeds_client_pins_and_shift_path(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );

        $supervisor = $this->companySupervisor();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);
        $shift->locations()->create([
            'recorded_at' => now()->subMinutes(4),
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'source' => 'gps',
        ]);
        $shift->locations()->create([
            'recorded_at' => now()->subMinute(),
            'latitude' => 3.4530,
            'longitude' => -76.5340,
            'source' => 'gps',
        ]);

        $response = $this->actingAs($admin)->get(route('company.supervision.index'));
        $response->assertOk();
        $response->assertSee('"name":"Palmas del Ingenio"', false);
        $response->assertSee('"path":', false);
        $response->assertSee('en ruta');
        $response->assertSee('>Ver<', false);
    }

    public function test_live_feed_distinguishes_screen_off_from_no_signal(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );

        $supervisor = $this->companySupervisor();
        $other = $this->companySupervisor('Otro', 'Senal', '1199006600', 'otro.senal.6600');

        $screenOff = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);
        $screenOff->locations()->create([
            'recorded_at' => now()->subSeconds(20),
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'source' => 'apk',
            'screen_on' => false,
        ]);

        $lost = SupervisorShift::query()->create([
            'security_company_id' => $other->security_company_id,
            'user_id' => $other->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);
        $lost->locations()->create([
            'recorded_at' => now()->subMinutes(5),
            'latitude' => 3.4600,
            'longitude' => -76.5400,
            'source' => 'app',
            'screen_on' => true,
        ]);

        $feed = $this->actingAs($admin)->getJson(route('company.supervision.live-feed'));
        $feed->assertOk();
        $rows = collect($feed->json('live'));
        $off = $rows->firstWhere('user', $supervisor->name);
        $none = $rows->firstWhere('user', $other->name);

        $this->assertSame('screen_off', $off['signal'] ?? null);
        $this->assertSame('Pantalla apagada', $off['online_label'] ?? null);
        $this->assertSame('no_signal', $none['signal'] ?? null);
        $this->assertSame('Sin señal', $none['online_label'] ?? null);
    }

    public function test_company_admin_can_download_supervision_pptx(): void
    {
        $this->seedWithPilot();

        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $response = $this->actingAs($user)->get(route('company.supervision.report', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'Informe_Supervision_',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            now()->startOfMonth()->toDateString(),
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_live_feed_json_lists_open_shifts(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );
        $supervisor = $this->companySupervisor();
        SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('company.supervision.live-feed'));
        $response->assertOk();
        $response->assertJsonPath('live.0.user', $supervisor->name);
        $response->assertJsonStructure(['live', 'reviews', 'events']);
    }

    public function test_live_feed_includes_support_and_alarm_pins(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );
        $supervisor = $this->companySupervisor();
        $client = Client::query()
            ->where('security_company_id', $supervisor->security_company_id)
            ->where('name', 'Palmas del Ingenio')
            ->firstOrFail();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);
        SupervisorFieldLog::query()->create([
            'supervisor_shift_id' => $shift->id,
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'client_id' => $client->id,
            'module' => SupervisorFieldModule::Supports,
            'outcome' => SupervisorFieldOutcome::Ok,
            'payload' => ['support_type' => 'Refuerzo', 'reason' => 'Apoyo de prueba'],
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'recorded_at' => now()->subMinutes(5),
        ]);
        SupervisorFieldLog::query()->create([
            'supervisor_shift_id' => $shift->id,
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'client_id' => $client->id,
            'module' => SupervisorFieldModule::Alarms,
            'outcome' => SupervisorFieldOutcome::Attention,
            'payload' => ['alarm_type' => 'Intrusión'],
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'recorded_at' => now()->subMinutes(3),
        ]);

        $response = $this->actingAs($admin)->getJson(route('company.supervision.live-feed'));
        $response->assertOk();
        $response->assertJsonCount(2, 'events');
        $kinds = collect($response->json('events'))->pluck('kind')->sort()->values()->all();
        $this->assertSame(['alarm', 'support'], $kinds);
    }

    public function test_snapped_route_uses_roads_once_for_closed_shift(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );
        $supervisor = $this->companySupervisor();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
        $shift->locations()->create([
            'recorded_at' => now()->subMinutes(20),
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'source' => 'gps',
        ]);
        $shift->locations()->create([
            'recorded_at' => now()->subMinutes(10),
            'latitude' => 3.4600,
            'longitude' => -76.5400,
            'source' => 'gps',
        ]);

        Http::fake([
            'roads.googleapis.com/*' => Http::response([
                'snappedPoints' => [
                    ['location' => ['latitude' => 3.4517, 'longitude' => -76.5321]],
                    ['location' => ['latitude' => 3.4601, 'longitude' => -76.5401]],
                ],
            ], 200),
        ]);
        config(['google-maps.server_api_key' => 'test-roads-key']);

        $first = $this->actingAs($admin)->getJson(route('company.supervision.snapped-route', $shift));
        $first->assertOk();
        $first->assertJsonPath('snapped', true);
        $first->assertJsonPath('path.0.lat', 3.4517);
        $first->assertJsonStructure(['start', 'end', 'stops', 'km']);
        $this->assertGreaterThan(0, $first->json('km'));

        $second = $this->actingAs($admin)->getJson(route('company.supervision.snapped-route', $shift));
        $second->assertOk();
        $second->assertJsonPath('snapped', true);
        Http::assertSentCount(1);
    }

    public function test_history_lists_only_closed_shifts(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );
        $supervisor = $this->companySupervisor();
        SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
        ]);
        $closed = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Closed,
            'started_at' => now()->subHours(5),
            'ended_at' => now()->subHours(1),
        ]);

        $history = $this->actingAs($admin)->get(route('company.supervision.index', [
            'tab' => 'history',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $history->assertOk();
        $history->assertSee('"shift_id":'.$closed->id, false);
        $history->assertSee('sheet_url', false);
        $open = SupervisorShift::query()
            ->where('security_company_id', $supervisor->security_company_id)
            ->where('status', SupervisorShiftStatus::Open)
            ->firstOrFail();
        $history->assertDontSee('"shift_id":'.$open->id.',', false);
    }

    public function test_company_opens_shift_sheet_as_draft_until_close(): void
    {
        $this->seedWithPilot();

        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $admin->securityCompany,
            SupervisionPackageSku::Sit1,
        );
        $supervisor = $this->companySupervisor();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $supervisor->security_company_id,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHour(),
            'km_start' => 100,
        ]);
        $shift->locations()->create([
            'recorded_at' => now()->subMinutes(10),
            'latitude' => 3.4516,
            'longitude' => -76.5320,
            'source' => 'gps',
        ]);

        $draft = $this->actingAs($admin)->get(route('company.supervision.sheets.show', [
            'kind' => 'shift',
            'id' => $shift->id,
        ]));
        $draft->assertOk();
        $draft->assertSee('Ficha de turno de supervisión');
        $draft->assertSee('En curso — no guardada');
        $draft->assertSee('3.451600');
        $this->assertNull($shift->fresh()->sheet_snapshot);

        $shift->update([
            'status' => SupervisorShiftStatus::Closed,
            'ended_at' => now(),
            'km_end' => 120,
        ]);
        app(\App\Services\Company\BuildSupervisorShiftSheetService::class)->freeze($shift->fresh());

        $closed = $this->actingAs($admin)->get(route('company.supervision.sheets.show', [
            'kind' => 'shift',
            'id' => $shift->id,
        ]));
        $closed->assertOk();
        $closed->assertSee('Ficha de turno de supervisión');
        $closed->assertDontSee('En curso — no guardada');
        $this->assertNotNull($shift->fresh()->sheet_snapshot);
    }
}
