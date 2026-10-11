<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\DeviceChangeStatus;
use App\Models\DeviceChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupervisorDeviceChangeTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $device): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/supervision/login', [
            'login' => $user->username,
            'password' => self::COMPANY_SUPERVISOR_PASSWORD,
            'device_id' => $device,
            'device_label' => 'Moto G · APK',
        ]);
    }

    private function companyAdmin(): User
    {
        return User::query()->where('email', 'empresa@sj-seguridad.test')->firstOrFail();
    }

    public function test_first_device_is_bound_and_same_device_logs_in_again(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();

        $first = $this->login($user, 'celular-uno-001')->assertOk();
        $this->assertSame('celular-uno-001', $user->fresh()->mobile_device_id);

        $second = $this->login($user, 'celular-uno-001')->assertOk();
        $this->assertNotSame($first->json('token'), $second->json('token'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_missing_device_id_asks_to_update_app(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();

        $this->postJson('/api/supervision/login', [
            'login' => $user->username,
            'password' => self::COMPANY_SUPERVISOR_PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors(['login']);
    }

    public function test_other_device_needs_approval_and_only_one_request_at_a_time(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        $this->login($user, 'celular-uno-001')->assertOk();

        $this->login($user, 'celular-dos-002')->assertStatus(423)->assertJsonPath('code', 'device_change_requested');
        $this->login($user, 'celular-dos-002')->assertStatus(423)->assertJsonPath('code', 'device_change_pending');
        $this->login($user, 'celular-tres-003')->assertStatus(423)->assertJsonPath('code', 'device_change_busy');

        $this->assertSame(1, DeviceChangeRequest::query()->where('user_id', $user->id)->count());
        $this->assertSame('celular-uno-001', $user->fresh()->mobile_device_id);
    }

    public function test_admin_approval_moves_session_to_new_device(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        $oldToken = $this->login($user, 'celular-uno-001')->json('token');
        $this->login($user, 'celular-dos-002')->assertStatus(423);
        $request = DeviceChangeRequest::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($this->companyAdmin())
            ->postJson(route('company.devices.approve', $request))
            ->assertOk();

        $this->assertSame(DeviceChangeStatus::Aprobada, $request->fresh()->status);
        $this->assertSame('celular-dos-002', $user->fresh()->mobile_device_id);
        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/supervision/shifts/current')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->login($user, 'celular-dos-002')->assertOk();
        $this->login($user, 'celular-uno-001')->assertStatus(423);
    }

    public function test_rejection_keeps_old_device(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        $this->login($user, 'celular-uno-001')->assertOk();
        $this->login($user, 'celular-dos-002')->assertStatus(423);
        $request = DeviceChangeRequest::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($this->companyAdmin())
            ->postJson(route('company.devices.reject', $request))
            ->assertOk();

        $this->assertSame(DeviceChangeStatus::Rechazada, $request->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->login($user, 'celular-uno-001')->assertOk();
    }

    public function test_request_expires_after_ten_minutes(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        $this->login($user, 'celular-uno-001')->assertOk();
        $this->login($user, 'celular-dos-002')->assertStatus(423);
        $request = DeviceChangeRequest::query()->where('user_id', $user->id)->firstOrFail();

        $this->travel(11)->minutes();

        $this->actingAs($this->companyAdmin())
            ->postJson(route('company.devices.approve', $request))
            ->assertStatus(409);

        $this->app['auth']->forgetGuards();
        $this->login($user, 'celular-tres-003')->assertStatus(423)->assertJsonPath('code', 'device_change_requested');
        $this->assertSame(DeviceChangeStatus::Vencida, $request->fresh()->status);
    }

    public function test_only_users_with_permission_can_approve(): void
    {
        $this->seedWithPilot();
        $user = $this->companySupervisor();
        $this->login($user, 'celular-uno-001')->assertOk();
        $this->login($user, 'celular-dos-002')->assertStatus(423);
        $request = DeviceChangeRequest::query()->where('user_id', $user->id)->firstOrFail();

        $operator = User::factory()->create([
            'security_company_id' => $this->pilotCompanyId(),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $operator->assignRole('colaborador');

        $this->actingAs($operator)
            ->postJson(route('company.devices.approve', $request))
            ->assertForbidden();
        $this->actingAs($operator)
            ->getJson(route('company.devices.pending'))
            ->assertOk()
            ->assertJsonCount(0, 'requests');

        $operator->givePermissionTo('company.devices.approve');
        $operator->refresh();

        $this->actingAs($operator)
            ->getJson(route('company.devices.pending'))
            ->assertOk()
            ->assertJsonCount(1, 'requests');
        $this->actingAs($operator)
            ->postJson(route('company.devices.approve', $request))
            ->assertOk();
    }
}
