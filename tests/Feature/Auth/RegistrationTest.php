<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_to_plans_when_public_register_is_disabled(): void
    {
        config(['billing.allow_public_register' => false]);

        $this->get('/register')->assertRedirect(route('planes.index'));
    }

    public function test_registration_post_is_forbidden_when_public_register_is_disabled(): void
    {
        config(['billing.allow_public_register' => false]);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();

        $this->assertGuest();
    }

    public function test_registration_screen_can_be_rendered_when_enabled(): void
    {
        config(['billing.allow_public_register' => true]);

        $this->get('/register')->assertOk();
    }

    public function test_new_users_can_register_when_enabled(): void
    {
        config(['billing.allow_public_register' => true]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect();
    }
}
