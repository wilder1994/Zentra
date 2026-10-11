<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\ClaimSingleUserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_from_other_device_asks_and_confirm_needs_no_password(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['single_session_token' => 'ocupada', 'single_session_device' => 'otro-equipo'])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('session_takeover');
        $this->assertGuest();

        $this->post('/login/takeover')->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('ocupada', $user->fresh()->single_session_token);
    }

    public function test_same_device_does_not_ask_again(): void
    {
        $user = User::factory()->create();

        $first = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $first->assertRedirect()->assertSessionHasNoErrors();
        $device = $first->getCookie(ClaimSingleUserSession::DEVICE_COOKIE)?->getValue();
        $this->assertNotEmpty($device);
        $this->assertNotNull($user->fresh()->single_session_token);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withCookie(ClaimSingleUserSession::DEVICE_COOKIE, $device)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_takeover_without_pending_confirmation_goes_back_to_login(): void
    {
        $this->post('/login/takeover')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_web_login_does_not_revoke_field_app_token(): void
    {
        $this->seedWithPilot();
        $token = $this->loginCompanySupervisor();
        $user = $this->companySupervisor();

        app(ClaimSingleUserSession::class)->claim($user, 'equipo');

        $this->assertSame(1, $user->tokens()->count());
        $this->withToken($token)->getJson('/api/supervision/shifts/current')->assertOk();
    }
}
