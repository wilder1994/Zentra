<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domain\Supervision\Data\SupervisionQueryFilter;
use App\Enums\SupervisionPackageSku;
use App\Enums\SupervisorShiftStatus;
use App\Models\Client;
use App\Models\Employee;
use App\Models\SupervisorPost;
use App\Models\SupervisorShift;
use App\Models\SupervisorShiftReview;
use App\Models\User;
use App\Services\Company\BuildPostComplianceService;
use App\Services\Company\BuildSupervisionSummaryService;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use App\Support\Supervision\ColombiaHolidays;
use App\Support\Supervision\SupervisionOperationalDay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class PostComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_colombia_holidays_2026(): void
    {
        foreach (['2026-01-01', '2026-01-12', '2026-04-02', '2026-04-03', '2026-05-18', '2026-06-08', '2026-06-15', '2026-10-12', '2026-12-08'] as $day) {
            $this->assertTrue(ColombiaHolidays::isHoliday(CarbonImmutable::parse($day)), $day);
        }
        $this->assertFalse(ColombiaHolidays::isHoliday(CarbonImmutable::parse('2026-01-06')));
        $this->assertFalse(ColombiaHolidays::isHoliday(CarbonImmutable::parse('2026-10-14')));
    }

    public function test_operational_day_starts_at_six(): void
    {
        $tz = (string) config('app.timezone');
        $this->assertSame('2026-10-11', SupervisionOperationalDay::dateFor(CarbonImmutable::parse('2026-10-12 05:59', $tz))->toDateString());
        $this->assertSame('2026-10-12', SupervisionOperationalDay::dateFor(CarbonImmutable::parse('2026-10-12 06:00', $tz))->toDateString());
    }

    public function test_post_form_saves_service_days_and_holidays(): void
    {
        $this->seedWithPilot();
        $user = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $installation = $client->installations()->firstOrFail();

        $this->actingAs($user)->post(route('company.clients.posts.store', $client), [
            'installation_id' => $installation->id,
            'name' => 'Recepción semana',
            'modality' => 12,
            'service_days' => [1, 2, 3, 4, 5],
            'serves_holidays' => '0',
            'vista' => 'sitio',
        ])->assertRedirect();

        $post = SupervisorPost::query()->where('name', 'Recepción semana')->firstOrFail();
        $this->assertSame(SupervisorPost::WEEKDAYS, $post->service_days);
        $this->assertFalse($post->serves_holidays);
        $this->assertSame('Lun a Vie · sin festivos', $post->serviceDaysLabel());
    }

    public function test_compliance_respects_service_days_holidays_and_reviews(): void
    {
        $this->seedWithPilot();
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00', $tz));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 08:00', $tz));

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $installation = $client->installations()->firstOrFail();
        $post = SupervisorPost::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'name' => 'Lun a Vie sin festivos',
            'modality' => 12,
            'service_days' => SupervisorPost::WEEKDAYS,
            'serves_holidays' => false,
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00', $tz));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 10:00', $tz));
        $service = app(BuildPostComplianceService::class);
        $companyId = (int) $client->security_company_id;
        $row = fn (string $date) => collect($service->day($companyId, CarbonImmutable::parse($date, $tz)))
            ->first(fn (array $r) => (int) $r['post']->id === (int) $post->id);

        $this->assertFalse($row('2026-10-12')['expected'], 'festivo');
        $this->assertFalse($row('2026-10-17')['expected'], 'sábado');
        $this->assertTrue($row('2026-10-14')['expected']);
        $this->assertFalse($row('2026-10-14')['done']);

        $supervisor = $this->companySupervisor();
        $shift = SupervisorShift::query()->create([
            'security_company_id' => $companyId,
            'user_id' => $supervisor->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHours(2),
        ]);
        $employee = Employee::query()->where('security_company_id', $companyId)->firstOrFail();
        SupervisorShiftReview::query()->create([
            'supervisor_shift_id' => $shift->id,
            'client_id' => $client->id,
            'supervisor_post_id' => $post->id,
            'employee_id' => $employee->id,
            'latitude' => 3.45,
            'longitude' => -76.53,
            'recorded_at' => now()->subHour(),
        ]);

        $this->assertTrue($row('2026-10-14')['done']);

        $range = $service->range($companyId, CarbonImmutable::parse('2026-10-12', $tz), CarbonImmutable::parse('2026-10-14', $tz));
        $this->assertSame(2, $range['posts'][$post->id]['expected']);
        $this->assertSame(1, $range['posts'][$post->id]['done']);
    }

    public function test_summary_clients_sorted_by_semaphore_and_reviews_count_once_per_post(): void
    {
        $this->seedWithPilot();
        $tz = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00', $tz));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 10:00', $tz));

        $client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $installation = $client->installations()->firstOrFail();
        $companyId = (int) $client->security_company_id;
        $posts = collect(['Puesto A', 'Puesto B'])->map(fn (string $name) => SupervisorPost::query()->create([
            'client_id' => $client->id,
            'installation_id' => $installation->id,
            'name' => $name,
            'modality' => 12,
            'service_days' => SupervisorPost::ALL_DAYS,
            'serves_holidays' => true,
            'is_active' => true,
            'created_at' => now()->subDays(3),
        ]));

        $shift = SupervisorShift::query()->create([
            'security_company_id' => $companyId,
            'user_id' => $this->companySupervisor()->id,
            'status' => SupervisorShiftStatus::Open,
            'started_at' => now()->subHours(3),
        ]);
        $employee = Employee::query()->where('security_company_id', $companyId)->firstOrFail();
        foreach ([1, 2] as $i) {
            SupervisorShiftReview::query()->create([
                'supervisor_shift_id' => $shift->id,
                'client_id' => $client->id,
                'supervisor_post_id' => $posts[0]->id,
                'employee_id' => $employee->id,
                'has_novelty' => $i === 2,
                'latitude' => 3.45,
                'longitude' => -76.53,
                'recorded_at' => now()->subHours($i),
            ]);
        }

        $snapshot = app(BuildSupervisionSummaryService::class)->execute(
            $client->securityCompany,
            new SupervisionQueryFilter(from: '2026-10-14', to: '2026-10-14'),
        );

        $rank = ['red' => 0, 'amber' => 1, 'green' => 2];
        $keys = array_map(fn (array $r) => [$rank[$r['status']], mb_strtolower($r['name'])], $snapshot->byClient);
        $sorted = $keys;
        sort($sorted);
        $this->assertSame($sorted, $keys);

        $palmas = collect($snapshot->byClient)->firstWhere('name', $client->name);
        $this->assertSame('amber', $palmas['status']);
        $this->assertSame(2, $palmas['reviews']);
        $this->assertSame(1, $palmas['novelty']);
        $this->assertGreaterThanOrEqual(1, $palmas['posts_done']);
        $this->assertContains('Puesto B', array_column($palmas['pending'], 'post'));
        $this->assertSame(1, $snapshot->reviewsWithNovelty);
    }

    public function test_live_feed_includes_post_coverage(): void
    {
        $this->seedWithPilot();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
        app(AssignCompanySupervisionPackageService::class)->execute($admin->securityCompany, SupervisionPackageSku::fromSize(1));

        $this->actingAs($admin)
            ->getJson(route('company.supervision.live-feed'))
            ->assertOk()
            ->assertJsonStructure(['coverage' => ['posts', 'done', 'window_label', 'clients', 'installations', 'missing_location']]);
    }
}
