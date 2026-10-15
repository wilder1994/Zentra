<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\InstallationGeoRequestStatus;
use App\Enums\SupervisionPackageSku;
use App\Models\Client;
use App\Models\Installation;
use App\Models\InstallationGeoRequest;
use App\Models\User;
use App\Services\Tenant\AssignCompanySupervisionPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupervisorGeoRequestTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Installation $installation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWithPilot();
        app(AssignCompanySupervisionPackageService::class)->execute(
            $this->companySupervisor()->securityCompany,
            SupervisionPackageSku::fromSize(1),
        );
        $this->client = Client::query()->where('slug', 'palmas-del-ingenio')->firstOrFail();
        $this->installation = Installation::query()->withoutGlobalScopes()
            ->where('client_id', $this->client->id)->where('is_active', true)->orderBy('id')->firstOrFail();
        $this->installation->forceFill(['latitude' => 3.4516, 'longitude' => -76.5320])->save();
    }

    private function openShift(): string
    {
        $token = $this->loginCompanySupervisor();
        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertSuccessful();

        return $token;
    }

    private function send(string $token, array $overrides = [])
    {
        return $this->withToken($token)->postJson('/api/supervision/geo/requests', $overrides + [
            'client_id' => $this->client->id,
            'installation_id' => $this->installation->id,
            'latitude' => 3.4530,
            'longitude' => -76.5320,
            'accuracy' => 12.4,
        ]);
    }

    public function test_supervisor_lists_installations_and_sends_request_without_changing_coordinates(): void
    {
        $token = $this->openShift();

        $this->withToken($token)->getJson('/api/supervision/geo/installations?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $this->installation->id, 'pending' => false])
            ->assertJsonPath('max_accuracy_m', 30);

        $this->send($token)->assertCreated();

        $row = InstallationGeoRequest::query()->firstOrFail();
        $this->assertSame(InstallationGeoRequestStatus::Pending, $row->status);
        $this->assertSame(13, $row->accuracy_m);
        $this->assertEqualsWithDelta(156, $row->distance_m, 3);
        $this->assertSame('3.4516000', (string) $this->installation->fresh()->latitude);

        $this->withToken($token)->getJson('/api/supervision/geo/installations?client_id='.$this->client->id)
            ->assertJsonFragment(['id' => $this->installation->id, 'pending' => true]);
    }

    public function test_requires_open_shift_and_good_accuracy(): void
    {
        $token = $this->loginCompanySupervisor();
        $this->send($token)->assertStatus(422)->assertJsonValidationErrors('shift');

        $this->withToken($token)->post('/api/supervision/shifts/open', $this->supervisorShiftOpenPayload())->assertSuccessful();
        $this->send($token, ['accuracy' => 45])->assertStatus(422)->assertJsonValidationErrors('accuracy');

        $this->assertSame(0, InstallationGeoRequest::query()->count());
    }

    public function test_new_request_supersedes_pending_one(): void
    {
        $token = $this->openShift();
        $this->send($token)->assertCreated();
        $this->send($token, ['latitude' => 3.4520])->assertCreated();

        $this->assertSame(1, InstallationGeoRequest::query()->pending()->count());
        $this->assertSame(1, InstallationGeoRequest::query()->where('status', 'superseded')->count());
    }

    public function test_cannot_send_for_client_without_supervision(): void
    {
        $token = $this->openShift();
        $this->client->forceFill(['has_supervision' => false])->saveQuietly();

        $this->send($token)->assertStatus(422)->assertJsonValidationErrors('installation_id');
        $this->withToken($token)->getJson('/api/supervision/geo/installations?client_id='.$this->client->id)
            ->assertOk()->assertJsonCount(0, 'installations');
    }

    public function test_company_admin_approves_and_installation_moves(): void
    {
        $this->send($this->openShift())->assertCreated();
        $row = InstallationGeoRequest::query()->firstOrFail();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($admin)->getJson(route('company.supervision.geo.pending'))
            ->assertOk()
            ->assertJsonFragment(['id' => $row->id, 'installation' => $this->installation->name]);

        $this->actingAs($admin)->get(route('company.supervision.geo.index'))
            ->assertOk()
            ->assertSee('Georreferenciaciones')
            ->assertSee($this->installation->name);

        $this->actingAs($admin)->postJson(route('company.supervision.geo.approve', $row))->assertOk();

        $this->assertSame('3.4530000', (string) $this->installation->fresh()->latitude);
        $this->assertSame(InstallationGeoRequestStatus::Approved, $row->fresh()->status);
        $this->assertSame((int) $admin->id, (int) $row->fresh()->reviewed_by);

        $this->actingAs($admin)->postJson(route('company.supervision.geo.reject', $row))->assertStatus(409);
    }

    public function test_reject_keeps_coordinates_and_saves_note(): void
    {
        $this->send($this->openShift())->assertCreated();
        $row = InstallationGeoRequest::query()->firstOrFail();
        $admin = User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();

        $this->actingAs($admin)->postJson(route('company.supervision.geo.reject', $row), ['note' => 'Punto en la vía'])->assertOk();

        $this->assertSame('3.4516000', (string) $this->installation->fresh()->latitude);
        $this->assertSame('Punto en la vía', $row->fresh()->review_note);
    }

    public function test_only_users_with_permission_can_review(): void
    {
        $this->send($this->openShift())->assertCreated();
        $row = InstallationGeoRequest::query()->firstOrFail();
        $operator = User::factory()->create([
            'security_company_id' => $this->client->security_company_id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $operator->assignRole('colaborador');

        $this->actingAs($operator)->postJson(route('company.supervision.geo.approve', $row))->assertForbidden();
        $this->actingAs($operator)->getJson(route('company.supervision.geo.pending'))->assertOk()->assertJsonCount(0, 'requests');

        $operator->givePermissionTo('company.geo.approve');
        $operator->refresh();
        $this->actingAs($operator)->getJson(route('company.supervision.geo.pending'))->assertOk()->assertJsonCount(1, 'requests');
        $this->actingAs($operator)->postJson(route('company.supervision.geo.approve', $row))->assertOk();
    }
}
